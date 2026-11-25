# ServerSMTP Symfony Mailer Transport

Transport HTTP API pentru **Symfony Mailer** (funcționează cu **Symfony 6/7**, **Drupal 10/11** și **Shopware 6**), construit peste SDK-ul oficial [`serversmtp/serversmtp-php`](../php).

## Instalare

```sh
composer require serversmtp/serversmtp-symfony-mailer
```

## Configurare prin DSN

```dotenv
MAILER_DSN=serversmtp+api://em_live_...@default
```

În `config/services.yaml`:

```yaml
services:
  ServerSMTP\SymfonyMailer\ServerSMTPTransportFactory:
    tags: ['mailer.transport_factory']
```

## Idempotență și atașamente

Adaugă antetul opțional `Idempotency-Key` (8–200 caractere) pe obiectul `Email` pentru a preveni trimiterile duplicate la reîncercări din cozi:

```php
$email = (new Email())
    ->from('Facturi <facturi@exemplu.invalid>')
    ->to('client@exemplu.invalid')
    ->subject('Factura #1042')
    ->html('<p>Factura este atașată.</p>')
    ->attachFromPath(__DIR__ . '/factura-1042.pdf');
$email->getHeaders()->addTextHeader('Idempotency-Key', 'factura-1042');
$mailer->send($email);
```
