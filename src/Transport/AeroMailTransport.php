<?php

namespace DikeshRajGiri\AeroMailer\Transport;

use GuzzleHttp\ClientInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportException;
use Illuminate\Support\Facades\Log;

class AeroMailTransport extends AbstractTransport
{
    protected ClientInterface $client;
    protected string $endpoint;
    protected string $apiKey;

    public function __construct(ClientInterface $client, string $endpoint, string $apiKey)
    {
        parent::__construct();
        $this->client = $client;
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if ($email instanceof Email) {
            $to = collect($email->getTo())->pluck('address')->all();
            $cc = collect($email->getCc() ?? [])->pluck('address')->all();
            $bcc = collect($email->getBcc() ?? [])->pluck('address')->all();
            $replyTo = optional($email->getReplyTo()[0] ?? null)->getAddress();
            $fromAddress = $email->getFrom()[0] ?? null;
            $from = $fromAddress?->getAddress();
            $fromName = $fromAddress?->getName();
            $subject = $email->getSubject();
            $html = $email->getHtmlBody();
            $text = $email->getTextBody();
            $attachments = $this->extractAttachments($email->getAttachments());
        } else {
            $to = $email->to ?? [];
            $cc = $email->cc ?? [];
            $bcc = $email->bcc ?? [];
            $replyTo = $email->reply_to ?? null;
            $from = $email->from ?? null;
            $fromName = $email->from_name ?? null;
            $subject = $email->subjectText ?? '';
            $html = $email->content ?? '';
            $text = null;
            $attachments = $email->attachments ?? [];
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

        try {
            $response = $this->client->request('POST', "{$this->endpoint}/api/v1/email/send", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 15,
            ]);

            $status = $response->getStatusCode();
            if ($status !== 202 && $status >= 400) {
                throw new TransportException("AeroMailer API responded {$status}: " . $response->getBody());
            }
        } catch (\Throwable $e) {
            Log::error('AeroMailer Transport Error', ['error' => $e->getMessage()]);
            throw new TransportException('AeroMailer send failed: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function extractAttachments($attachments): array
    {
        $result = [];
        foreach ($attachments as $attachment) {
            $result[] = [
                'filename' => $attachment->getName(),
                'content' => base64_encode($attachment->getBody()),
                'type' => $attachment->getMediaType() . '/' . $attachment->getMediaSubtype(),
            ];
        }
        return $result;
    }

    public function __toString(): string
    {
        return 'aeromailer';
    }
}
