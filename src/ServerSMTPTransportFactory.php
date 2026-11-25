<?php

declare(strict_types=1);

namespace ServerSMTP\SymfonyMailer;

use ServerSMTP\Client;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Creates ServerSMTPTransport from a Symfony Mailer DSN:
 *   MAILER_DSN=serversmtp+api://em_live_...@default
 */
final class ServerSMTPTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        if (!in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'serversmtp', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $host = $dsn->getHost();
        $baseUrl = $host === 'default' ? 'https://api.serversmtp.ro' : 'https://' . $host;

        return new ServerSMTPTransport(
            new Client($apiKey, $baseUrl),
            $this->dispatcher,
            $this->logger
        );
    }

    /** @return string[] */
    protected function getSupportedSchemes(): array
    {
        return ['serversmtp', 'serversmtp+api'];
    }
}
