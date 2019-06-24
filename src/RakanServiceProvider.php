<?php

namespace TELstatic\Rakan;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use TELstatic\Rakan\Plugins\Base64;
use TELstatic\Rakan\Plugins\Policy;
use TELstatic\Rakan\Plugins\Signature;
use TELstatic\Rakan\Plugins\Verify;

class RakanServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/rakan.php' => config_path('rakan.php'),
        ]);

        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->mergeConfigFrom(__DIR__.'/config/rakan.php', 'rakan');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('rakan.oss', function () {
            return Rakan::oss();
        });

        $this->app->singleton('rakan.qiniu', function () {
            return Rakan::qiniu();
        });

        Storage::extend('oss', function () {
            $adapter = new RakanAdapter('oss');

            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new Policy());
            $filesystem->addPlugin(new Verify());
            $filesystem->addPlugin(new Base64());
            $filesystem->addPlugin(new Signature());

            return $filesystem;
        });

        Storage::extend('qiniu', function () {
            $adapter = new RakanAdapter('qiniu');

            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new Policy());
            $filesystem->addPlugin(new Verify());
            $filesystem->addPlugin(new Base64());

            return $filesystem;
        });
    }

    public function provides()
    {
        return ['rakan.oss', 'rakan.qiniu'];
    }
}
