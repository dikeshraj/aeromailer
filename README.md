# 🚀 AeroMailer — Custom Laravel Mail Driver

**AeroMailer** is a robust, extensible Laravel mail driver that routes your application's outgoing emails through any HTTP-based mail API (e.g., your own backend that validates emails and delivers via AWS SES).

It provides:
- ✅ Full Laravel Mailer integration (`MAIL_MAILER=aeromailer`)
- ✅ API-key authentication
- ✅ Robust retries & error handling
- ✅ Attachment & content support
- ✅ Easy configuration and environment variables

---

## 🧩 Installation

### 1. Require the package

If published on GitHub (private repo):

```bash
composer require dikeshraj/aeromailer:dev-main --ignore-platform-reqs

Or, if installed locally (as a path repo during development):

"repositories": [
    {
        "type": "path",
        "url": "../aeromailer"
    }
]

2. Publish configuration
php artisan vendor:publish --tag=aeromailer-config

3. Configure environment

Add to your .env file:

MAIL_MAILER=aeromailer
AEROMAIL_API_KEY=sk_live_1234567890
AEROMAIL_ENDPOINT=https://api.your-backend.com
AEROMAIL_SEND_PATH=/send

4. Update config/mail.php

Register the new mailer:

'mailers' => [
    'aeromailer' => [
        'transport' => 'aeromailer',
    ],
    // other mailers...
],

✉️ Usage

Use Laravel’s native Mail facade and Mailable classes — no special syntax required:

use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;

Mail::to('user@example.com')
    ->cc('manager@example.com')
    ->bcc('admin@example.com')
    ->send(new WelcomeMail($user));

    Your App\Mail\WelcomeMail might look like:

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $user) {}

    public function build()
    {
        return $this->from('noreply@yourdomain.com', 'Your App')
                    ->subject('Welcome to AeroMailer')
                    ->view('emails.welcome');
    }
}

⚙️ Configuration

Config file: config/aeromailer.php

return [
    'endpoint' => env('AEROMAIL_ENDPOINT', 'https://api.your-backend.com'),
    'path' => env('AEROMAIL_SEND_PATH', '/send'),
    'api_key' => env('AEROMAIL_API_KEY'),
    'timeout' => 10,
    'retries' => 3,
];

🛡️ Features
Feature	Description
🔐 Secure API Auth	Uses Bearer token header with configurable API key
📦 Attachments	Automatically handles Laravel attachments via base64
🔁 Retries & Backoff	Retries on 429 & 5xx responses with exponential backoff
📊 Logging	Integrates with Laravel’s logging system
🧠 Extensible	Adaptable for any HTTP email provider

🧪 Testing

Unit tests use Guzzle mocks to simulate API responses:

vendor/bin/phpunit

🧰 Customization

Modify AeroMailTransport::buildPayload() to change how the request body is built.

Extend the transport to add custom headers or tracking options.

Adjust retry logic, logging, or error exceptions as needed.


🧑‍💻 Example Backend Integration

Your backend (the API endpoint set in .env) can:

Validate email addresses (MX, disposable, typo correction)

Analyze message content for spam or phishing risk

Deliver via AWS SES or another provider

This ensures every email sent through AeroMailer is validated, clean, and compliant.


⚡ License

Proprietary © 2025 [Dikesh Raj Giri]
For internal or client distribution only. All rights reserved.

🧭 Author

Dikesh Raj Giri
📧 giridikesh03@gmail.com
