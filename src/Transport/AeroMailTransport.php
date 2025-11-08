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

    public function __construct(ClientInterface $client, string $endpoint, ?string $apiKey = null, ?LoggerInterface $logger = null)
    {
        parent::__construct();
        $this->client = $client;
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        $original = $sentMessage->getOriginalMessage();

        // extract fields (supports Symfony Email and old-style Mailable)
        if ($original instanceof Email) {
            $to = collect($original->getTo() ?? [])->map(fn($a) => ['email'=>$a->getAddress(),'name'=>$a->getName()])->values()->all();
            $cc = collect($original->getCc() ?? [])->map(fn($a) => $a->getAddress())->values()->all();
            $bcc = collect($original->getBcc() ?? [])->map(fn($a) => $a->getAddress())->values()->all();
            $replyTo = optional($original->getReplyTo()[0] ?? null)->getAddress();
            $fromPart = $original->getFrom()[0] ?? null;
            $from = $fromPart?->getAddress();
            $fromName = $fromPart?->getName();
            $subject = $original->getSubject();
            $html = $original->getHtmlBody();
            $text = $original->getTextBody();
            $attachments = $this->extractAttachments($original->getAttachments());
        } else {
            // fallback - try reading public properties
            $to = $original->to ?? [];
            $cc = $original->cc ?? [];
            $bcc = $original->bcc ?? [];
            $replyTo = $original->reply_to ?? null;
            $from = $original->from ?? null;
            $fromName = $original->from_name ?? null;
            $subject = $original->subjectText ?? $original->subject ?? null;
            $html = $original->content ?? null;
            $text = null;
            $attachments = $original->attachments ?? [];
        }

        $payload = [
            'from' => $from,
            'from_name' => $fromName,
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'reply_to' => $replyTo,
            'subject' => $subject,
            'body_html' => $html,
            'body_text' => $text,
            'attachments' => $attachments,
        ];

        $headers = ['Accept'=>'application/json','Content-Type'=>'application/json'];
        if ($this->apiKey) $headers['X-Api-Key'] = $this->apiKey;

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
            $this->log('error', 'AeroMailer API error', ['status'=>$status,'body'=>$body]);
            throw new TransportException("AeroMailer API responded {$status}: {$body}");
        } catch (\Throwable $e) {
            $this->log('error', 'AeroMailer transport exception: '.$e->getMessage(), []);
            throw new TransportException('AeroMailer transport failed: '.$e->getMessage(), $sentMessage, 0, $e);
        }
    }

    protected function extractAttachments($parts): array
    {
        $list = [];
        foreach ($parts as $part) {
            try { $body = (string)$part->getBody(); } catch (\Throwable $e) { $body = ''; }
            $list[] = [
                'filename' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($body),
                'type' => ($part->getMediaType() ?? 'application').'/'.($part->getMediaSubtype() ?? 'octet-stream'),
            ];
        }
        return $list;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) $this->logger->{$level}($message, $context);
    }

    public function __toString(): string { return 'aeromailer'; }
}
