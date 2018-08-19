<?php

namespace TELstatic\Rakan;

use Illuminate\Support\ServiceProvider;

class RakanServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        require "Helper/helpers.php";

        $this->publishes([
            __DIR__ . '/config/rakan.php' => config_path('rakan.php'),
        ]);

        $this->mergeConfigFrom(__DIR__ . '/config/rakan.php', 'rakan');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('rakan', function ($app) {
            return new Rakan();
        });
    }
}
