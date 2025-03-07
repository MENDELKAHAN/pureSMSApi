<?php

namespace Puresms\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route; // <-- The correct Route facade

class PureSmsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/puresms.php', 'puresms');

        $this->app->singleton('puresms', function () {
            return new PureSmsService();
        });
    }

  	public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Publish config file
            $this->publishes([
                __DIR__.'/../config/puresms.php' => config_path('puresms.php'),
            ], 'puresms-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'puresms-migrations');
        }

        // Load routes from the package
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');


    



        Route::macro('puresmsWebhooks', function ($uri = 'puresms-webhook') {

            // return Route::post($uri, [\Mendelkahan\LaravelPuresms\Http\Controllers\WebhookController::class, 'handleWebhook'])
            return Route::post($url, 'Mendelkahan\LaravelPuresms\Http\Controllers\WebhookController@handleWebhook');
        ->name('puresms.webhook');
});

    }
}


