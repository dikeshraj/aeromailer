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

Testing
Tests use PHPUnit and mock the GuzzleHttp\ClientInterface.

bash
Copy code
composer test
License
MIT

markdown
Copy code

---

## Important notes & recommended next steps
1. **Replace vendor/namespace** — change `dikesh` (composer package name) and `DikeshRajGiri` (PHP namespace) to match your brand/client. Do a project-wide search/replace.
2. **Adapt payload** — `buildPayload()` creates a neutral JSON structure. Adjust top-level field names, headers, or nested personalization to exactly match your client's mail API contract.
3. **Authentication** — config defaults to `Bearer` or a custom header. Change as required.
4. **Testing** — expand the provided test to cover attachments, cc/bcc, 429 handling, and server errors.
5. **CI & publishing** — add a GitHub Actions workflow to run `composer install` and `phpunit` on push, and then tag releases for Packagist.

---

If you want, I can now:
- replace `DikeshRajGiri` / `DikeshRajGiri` across all files with your real vendor/client name (tell me the exact strings), **and** produce a ready-to-download ZIP,
- or create a `GitHub Actions` workflow file and a `.gitattributes`/`.gitignore` for you,
- or extend the transport to support provider-specific features (per-recipient substitution data, templates by id, webhook verification helpers).

Which of those should I do next?# aeromailer
