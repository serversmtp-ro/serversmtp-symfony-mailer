<?php

declare(strict_types=1);

namespace ServerSMTP\SymfonyMailer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ServerSMTP\Client;
use ServerSMTP\ServerSMTPException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Symfony Mailer transport for ServerSMTP (POST /v1/send).
 * Compatible with Symfony 6/7, Drupal 10/11 (Symfony Mailer) and Shopware 6.
 */
final class ServerSMTPTransport extends AbstractTransport
{
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function __construct(
        private readonly Client $client,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    /** @return array<string, mixed> */
    public static function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;
        if ($from === null) {
            throw new TransportException('ServerSMTP: the email has no From address.');
        }

        $payload = [
            'from' => self::mailbox($from),
            'to' => self::addresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $list) {
            if ($list !== []) {
                $payload[$field] = self::addresses($list);
            }
        }

        $html = self::body($email->getHtmlBody());
        $text = self::body($email->getTextBody());
        if ($html !== null) {
            $payload['html'] = $html;
        }
        if ($text !== null) {
            $payload['text'] = $text;
        }

        if (($replyTo = $email->getReplyTo()[0] ?? null) !== null) {
            $payload['replyTo'] = $replyTo->getAddress();
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $attachments[] = [
                'filename' => (string) ($part->getFilename() ?? 'attachment'),
                'content' => base64_encode($part->getBody()),
                'contentType' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
            ];
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $key = $email->getHeaders()->get(self::IDEMPOTENCY_HEADER)?->getBodyAsString();

        try {
            $response = $this->client->emails->send(self::payload($email), $key ?: null);
        } catch (ServerSMTPException $exception) {
            $detail = $exception->fields === [] ? '' : ' (' . implode(', ', $exception->fields) . ')';
            throw new TransportException(
                'ServerSMTP: ' . $exception->getMessage() . $detail,
                (int) $exception->statusCode,
                $exception
            );
        }

        $id = $response->data['requestId'] ?? $response->data['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $message->setMessageId($id);
        }
    }

    public function __toString(): string
    {
        return 'serversmtp+api://default';
    }

    private static function mailbox(Address $address): string
    {
        $name = trim(str_replace(['"', '<', '>', "\r", "\n"], '', $address->getName()));

        return $name === '' ? $address->getAddress() : $name . ' <' . $address->getAddress() . '>';
    }

    /**
     * @param Address[] $list
     * @return list<string>
     */
    private static function addresses(array $list): array
    {
        return array_values(array_map(static fn (Address $address): string => $address->getAddress(), $list));
    }

    /** @param resource|string|null $body */
    private static function body($body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }
}
