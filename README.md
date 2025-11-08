# AeroMailer

AeroMailer is a lightweight Laravel mail driver package scaffold you can adapt to any HTTP-based mail provider. It implements a custom transport, robust retries, attachments, and easy configuration.

## Installation

1. Update `composer.json` namespace / package name to your vendor.
2. `composer require dikeshraj/aeromailer` (or add as path repo during development)
3. Publish config: `php artisan vendor:publish --tag=aeromailer-config`
4. Set env variables:

MAIL_MAILER=aeromailer
AEROMAIL_API_KEY=sk_...
AEROMAIL_ENDPOINT=https://api.example-mail.local/v1
AEROMAIL_SEND_PATH=/send

bash
Copy code

5. Update `config/mail.php`:

```php
'mailers' => [
    'aeromailer' => [ 'transport' => 'aeromailer' ],
    // ...
],
Usage
Use Laravel mailables as usual:

php
Copy code
Mail::to('user@example.com')->send(new \App\Mail\WelcomeMail($user));
Customization
Adjust config/aeromailer.php to match your provider's auth and payload.

Modify AeroMailTransport::buildPayload to add provider-specific fields (e.g., substitution data, templates).


