<?php
namespace DikeshRaj\AeroMailer\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\MailManager;
use DikeshRaj\AeroMailer\Transport\AeroMailTransport;
use GuzzleHttp\Client;

class AeroMailerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/aeromailer.php', 'aeromailer');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/aeromailer.php' => config_path('aeromailer.php'),
        ], 'aeromailer-config');

        $this->app->make(MailManager::class)->extend('aeromailer', function ($config) {
            $client = new Client(config('aeromailer.http', []));
            return new AeroMailTransport(
                $client,
                rtrim(config('aeromailer.endpoint'), '/'),
                config('aeromailer.api_key'),
                $this->app['log'] ?? null
            );
        });
    }
}
