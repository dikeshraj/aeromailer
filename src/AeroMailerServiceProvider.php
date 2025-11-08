<?php

namespace DikeshRajGiri\AeroMailer;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\MailManager;
use DikeshRajGiri\AeroMailer\Transport\AeroMailTransport;
use GuzzleHttp\Client;

class AeroMailerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/aeromailer.php' => config_path('aeromailer.php'),
        ], 'aeromailer-config');

        $this->app->make(MailManager::class)->extend('aeromailer', function ($app) {
            return new AeroMailTransport(
                new Client(),
                config('aeromailer.endpoint'),
                config('aeromailer.api_key')
            );
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/aeromailer.php', 'aeromailer');
    }
}
