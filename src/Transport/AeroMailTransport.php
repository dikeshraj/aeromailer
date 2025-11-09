<?php

namespace DikeshRaj\AeroMailer\Transport;

use GuzzleHttp\ClientInterface;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportException;
use Psr\Log\LoggerInterface;

class AeroMailTransport extends AbstractTransport
{
    protected ClientInterface $client;
    protected string $endpoint;
    protected ?string $apiKey;
    protected ?LoggerInterface $logger;
    protected int $timeout;
    protected int $maxRetries;

    public function __construct(
        ClientInterface $client,
        string $endpoint,
        ?string $apiKey = null,
        ?LoggerInterface $logger = null,
        int $timeout = 10,
        int $maxRetries = 3
    ) {
        parent::__construct();
        $this->client = $client;
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        $original = $sentMessage->getOriginalMessage();

        if ($original instanceof Email) {
            // Extract addresses safely
            $to = collect($original->getTo() ?? [])
                ->map(fn($a) => $a->getAddress())
                ->filter()
                ->values()
                ->all();

            $cc = collect($original->getCc() ?? [])
                ->map(fn($a) => $a->getAddress())
                ->filter()
                ->values()
                ->all();

            $bcc = collect($original->getBcc() ?? [])
                ->map(fn($a) => $a->getAddress())
                ->filter()
                ->values()
                ->all();

            $replyTo = optional($original->getReplyTo()[0] ?? null)->getAddress();
            $fromPart = $original->getFrom()[0] ?? null;
            $from = $fromPart?->getAddress();
            $fromName = $fromPart?->getName();
            $subject = $original->getSubject();
            $html = $original->getHtmlBody();
            $text = $original->getTextBody();
            $attachments = $this->extractAttachments($original->getAttachments());
        } else {
            // Fallback for non-Symfony mailables
            $to = is_array($original->to ?? null) ? $original->to : (array)($original->to ?? []);
            $cc = is_array($original->cc ?? null) ? $original->cc : (array)($original->cc ?? []);
            $bcc = is_array($original->bcc ?? null) ? $original->bcc : (array)($original->bcc ?? []);
            $replyTo = $original->reply_to ?? null;
            $from = $original->from ?? null;
            $fromName = $original->from_name ?? null;
            $subject = $original->subject ?? null;
            $html = $original->content ?? null;
            $text = null;
            $attachments = $original->attachments ?? [];
        }

        // 🔒 Sanitize addresses to avoid nulls or invalid structure
        $to = array_filter(array_map('trim', (array)$to));
        $cc = array_filter(array_map('trim', (array)$cc));
        $bcc = array_filter(array_map('trim', (array)$bcc));

        $payload = [
            'from' => $from,
            'from_name' => $fromName,
            'to' => array_values($to),
            'cc' => array_values($cc),
            'bcc' => array_values($bcc),
            'reply_to' => $replyTo,
            'subject' => $subject,
            'body_html' => $html,
            'body_text' => $text,
            'attachments' => $attachments,
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($this->apiKey) {
            $headers['X-Api-Key'] = $this->apiKey;
        }

        try {
            $response = $this->client->request('POST', "{$this->endpoint}/api/v1/email/send", [
                'headers' => $headers,
                'json' => $payload,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            if ($status === 202 || ($status >= 200 && $status < 300)) {
                return;
            }

            $body = (string)$response->getBody();
            $this->log('error', 'AeroMailer API error', ['status' => $status, 'body' => $body]);
            throw new TransportException("AeroMailer API responded {$status}: {$body}");
        } catch (\Throwable $e) {
            $this->log('error', 'AeroMailer transport exception: ' . $e->getMessage());
            throw new TransportException('AeroMailer transport failed: ' . $e->getMessage(), $sentMessage, 0, $e);
        }
    }

    protected function mapAddresses(?array $addresses): array
    {
        if (!$addresses) return [];
        return collect($addresses)->map(fn($a) => [
            'email' => $a->getAddress(),
            'name' => $a->getName()
        ])->values()->all();
    }

    protected function extractAttachments(array $parts): array
    {
        $list = [];
        foreach ($parts as $part) {
            try {
                $body = (string) $part->getBody();
            } catch (\Throwable $e) {
                $body = '';
            }
            $list[] = [
                'filename' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($body),
                'type' => ($part->getMediaType() ?? 'application') . '/' . ($part->getMediaSubtype() ?? 'octet-stream'),
            ];
        }
        return $list;
    }

    protected function sendWithRetries(array $payload, array $headers): void
    {
        $attempt = 0;
        $url = "{$this->endpoint}/api/v1/email/send";

        while ($attempt <= $this->maxRetries) {
            try {
                $response = $this->client->request('POST', $url, [
                    'headers' => $headers,
                    'json' => $payload,
                    'http_errors' => false,
                    'timeout' => $this->timeout,
                ]);

                $status = $response->getStatusCode();

                if ($status >= 200 && $status < 300) {
                    return;
                }

                // Retry on 429 or 5xx
                if ($status === 429 || ($status >= 500 && $status < 600)) {
                    $attempt++;
                    $wait = $this->getRetryDelay($attempt, $response);
                    sleep($wait);
                    continue;
                }

                $body = (string)$response->getBody();
                $this->log('error', 'AeroMailer API error', ['status' => $status, 'body' => $body]);
                throw new TransportException("AeroMailer API responded {$status}: {$body}");
            } catch (\Throwable $e) {
                $attempt++;
                if ($attempt > $this->maxRetries) {
                    $this->log('error', 'AeroMailer transport failed after retries: ' . $e->getMessage());
                    throw new TransportException(
                        'AeroMailer transport failed: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
                sleep($this->getRetryDelay($attempt));
            }
        }
    }

    protected function getRetryDelay(int $attempt, $response = null): int
    {
        if ($response && $response->getStatusCode() === 429) {
            $retryAfter = $response->getHeaderLine('Retry-After');
            if (is_numeric($retryAfter)) return (int)$retryAfter;
        }
        // exponential backoff with jitter
        return min(30, pow(2, $attempt) + random_int(0, 5));
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) $this->logger->{$level}($message, $context);
    }

    public function __toString(): string
    {
        return 'aeromailer';
    }
}
