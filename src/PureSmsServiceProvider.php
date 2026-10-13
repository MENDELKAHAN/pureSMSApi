<?php

namespace Puresms\Laravel;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Puresms\Laravel\Channels\PureSmsChannel;
use Puresms\Laravel\Http\Controllers\WebhookController;

class PureSmsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/puresms.php', 'puresms');
        $this->app->singleton('puresms', fn () => new PureSmsService);
    }

    public function boot()
    {
        Notification::extend('PureSmsChannel', fn () => new PureSmsChannel);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/puresms.php' => config_path('puresms.php'),
            ], 'puresms-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'puresms-migrations');
        }

        // Applications explicitly opt in to a signed webhook; no public send routes.
        Route::macro('puresmsWebhooks', function ($uri = 'puresms-webhook') {
            return Route::post($uri, [WebhookController::class, 'handleWebhook'])
                ->name('puresms.webhook');
        });
    }
}
