<?php

namespace DikeshRajGiri\AeroMailer\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class AeroMailTransport extends AbstractTransport
{
    protected string $apiKey;
    protected string $endpoint;
    protected ClientInterface $client;
    protected array $config;

    public function __construct(
        string $apiKey,
        string $endpoint,
        ClientInterface $client,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
        array $config = []
    ) {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
        $this->client = $client;
        $this->config = $config;
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        $message = $sentMessage->getOriginalMessage();

        if (! $message instanceof Email) {
            throw new TransportException('AeroMailTransport only supports Symfony\\Mime\\Email instances', $sentMessage);
        }

        $payload = $this->buildPayload($message);

        $sendUrl = $this->endpoint . ($this->config['send_path'] ?? '/send');

        $maxAttempts = $this->config['retries']['max_attempts'] ?? 3;
        $initialDelayMs = $this->config['retries']['initial_delay_ms'] ?? 200;
        $maxDelayMs = $this->config['retries']['max_delay_ms'] ?? 5000;

        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $headers = $this->buildAuthHeaders();
                $this->logDebug('AeroMailTransport: sending attempt ' . $attempt, ['url' => $sendUrl]);

                $response = $this->client->request('POST', $sendUrl, [
                    'headers' => $headers,
                    'json' => $payload,
                    'http_errors' => false,
                ]);

                $status = $response->getStatusCode();

                if ($status >= 200 && $status < 300) {
                    // success
                    $this->logDebug('AeroMailTransport: send success', ['status' => $status]);
                    return;
                }

                // handle 429 rate limit
                if ($status == 429) {
                    $retryAfter = $this->parseRetryAfter($response->getHeaderLine('Retry-After'));
                    $delayMs = $retryAfter > 0 ? $retryAfter * 1000 : $initialDelayMs;
                    $this->logDebug('AeroMailTransport: rate limited, retrying after ' . $delayMs . 'ms', ['status' => $status]);
                    usleep(min($delayMs, $maxDelayMs) * 1000);
                    continue;
                }

                // 5xx: transient server error -> retry
                if ($status >= 500 && $status < 600) {
                    $delayMs = $this->calculateDelayMs($attempt, $initialDelayMs, $maxDelayMs);
                    $this->logDebug('AeroMailTransport: server error, retrying after ' . $delayMs . 'ms', ['status' => $status]);
                    usleep($delayMs * 1000);
                    continue;
                }

                // For 4xx other than 429, consider it a permanent failure
                $body = (string) $response->getBody();
                throw new TransportException(sprintf('AeroMail API returned status %d: %s', $status, $body), $sentMessage);

            } catch (GuzzleException $e) {
                $lastException = $e;
                $delayMs = $this->calculateDelayMs($attempt, $initialDelayMs, $maxDelayMs);
                $this->logDebug('AeroMailTransport: network error, retrying', ['exception' => $e->getMessage(), 'delay_ms' => $delayMs]);
                usleep($delayMs * 1000);
                continue;
            }
        }

        // If we reached here, all retries failed
        $message = 'AeroMailTransport: failed to send after ' . $attempt . ' attempts';
        if ($lastException) {
            throw new TransportException($message . ': ' . $lastException->getMessage(), $sentMessage, 0, $lastException);
        }

        throw new TransportException($message, $sentMessage);
    }

    protected function buildAuthHeaders(): array
    {
        $authType = $this->config['auth']['type'] ?? 'bearer';

        if ($authType === 'bearer') {
            return [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
        }

        // custom header
        $headerName = $this->config['auth']['header_name'] ?? 'X-Api-Key';
        return [
            $headerName => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function parseRetryAfter(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value; // seconds
        }

        // try parsing HTTP-date
        try {
            $ts = strtotime($value);
            if ($ts !== false) {
                $diff = $ts - time();
                return $diff > 0 ? $diff : 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }

    protected function calculateDelayMs(int $attempt, int $initial, int $max): int
    {
        // exponential backoff with jitter
        $exp = $initial * (2 ** ($attempt - 1));
        $jitter = random_int(0, (int) ($exp * 0.2));
        $delay = min($exp + $jitter, $max);
        return (int) $delay;
    }

    protected function buildPayload(Email $message): array
    {
        $payload = [];

        // merge defaults
        if (!empty($this->config['defaults'])) {
            $payload = array_merge($payload, $this->config['defaults']);
        }

        // from
        $from = $message->getFrom();
        if (!empty($from)) {
            $first = reset($from);
            $payload['from'] = [
                'email' => $first->getAddress(),
                'name' => $first->getName() ?: null,
            ];
        }

        // reply-to
        $reply = $message->getReplyTo();
        if (!empty($reply)) {
            $first = reset($reply);
            $payload['reply_to'] = $first->getAddress();
        }

        // subject
        $payload['subject'] = $message->getSubject();

        // bodies
        $html = $message->getHtmlBody();
        $text = $message->getTextBody();
        if ($html) {
            $payload['html'] = $html;
        }
        if ($text) {
            $payload['text'] = $text;
        }

        // recipients: to, cc, bcc
        $payload['recipients'] = [];
        foreach (['to' => $message->getTo(), 'cc' => $message->getCc(), 'bcc' => $message->getBcc()] as $type => $list) {
            if (empty($list)) {
                continue;
            }
            foreach ($list as $address) {
                $recipient = ['email' => $address->getAddress()];
                if ($address->getName()) {
                    $recipient['name'] = $address->getName();
                }
                if ($type !== 'to') {
                    $recipient['type'] = $type;
                }
                $payload['recipients'][] = $recipient;
            }
        }

        // custom headers to top-level (optional): scan for headers starting with X-Aero-
        foreach ($message->getHeaders()->all() as $header) {
            $name = $header->getName();
            if (stripos($name, 'X-Aero-') === 0) {
                $key = substr($name, 7);
                $payload[$key] = $header->getBodyAsString();
            }
        }

        // attachments
        $attachments = [];
        foreach ($message->getAttachments() as $part) {
            try {
                $stream = $part->getBody();
                $contents = $stream instanceof \Stringable || is_string($stream) ? (string) $stream : $stream->getContents();
            } catch (\Throwable $e) {
                continue;
            }

            $filename = $part->getFilename() ?? 'attachment';
            $attachments[] = [
                'filename' => $filename,
                'content' => base64_encode($contents),
                'content_type' => $part->getMediaType() . '/' . ($part->getMediaSubtype() ?? 'octet-stream'),
            ];
        }

        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->debug($message, $context);
        }
    }

    public function __toString(): string
    {
        return 'aeromailer';
    }
}
