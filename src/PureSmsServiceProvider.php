<?php

namespace Puresms\Laravel;

use Illuminate\Support\ServiceProvider;

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
	}
}
