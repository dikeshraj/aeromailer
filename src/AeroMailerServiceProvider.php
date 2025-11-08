<?php

namespace DikeshRajGiri\AeroMailer;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\MailManager;
use DikeshRajGiri\AeroMailer\Transport\AeroMailTransport;
use GuzzleHttp\Client;

class AeroMailerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/aeromailer.php', 'aeromailer');
    }

    public function boot()
    {
        // publish config
        $this->publishes([
            __DIR__ . '/../config/aeromailer.php' => config_path('aeromailer.php'),
        ], 'aeromailer-config');

        // extend the mail manager with 'aeromailer' transport
        $this->app->make(MailManager::class)->extend('aeromailer', function ($config) {
            $cfg = array_merge(config('aeromailer', []), $config ?? []);
            $apiKey = $cfg['api_key'] ?? env('AEROMAIL_API_KEY');
            $endpoint = rtrim($cfg['endpoint'] ?? '', '/');
            $guzzleOptions = $cfg['guzzle'] ?? [];
            $client = new Client($guzzleOptions);

            return new AeroMailTransport(
                $apiKey,
                $endpoint,
                $client,
                $this->app['events'],
                $this->app['log'],
                $cfg
            );
        });
    }
}
