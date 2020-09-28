<?php

namespace TELstatic\Rakan;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use TELstatic\Rakan\Plugins\Base64;
use TELstatic\Rakan\Plugins\Config;
use TELstatic\Rakan\Plugins\MultiUpload;
use TELstatic\Rakan\Plugins\Policy;
use TELstatic\Rakan\Plugins\Signature;
use TELstatic\Rakan\Plugins\Symlink;
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

        $this->app->singleton('rakan.obs', function () {
            return Rakan::obs();
        });
      
        $this->app->singleton('rakan.cos', function () {
            return Rakan::cos();
        });

        Storage::extend('oss', function () {
            $adapter = new RakanAdapter('oss');

            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new Policy());
            $filesystem->addPlugin(new Verify());
            $filesystem->addPlugin(new Base64());
            $filesystem->addPlugin(new Signature());
            $filesystem->addPlugin(new Symlink());
            $filesystem->addPlugin(new Config());
            $filesystem->addPlugin(new MultiUpload());

            return $filesystem;
        });

        Storage::extend('qiniu', function () {
            $adapter = new RakanAdapter('qiniu');

            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new Policy());
            $filesystem->addPlugin(new Verify());
            $filesystem->addPlugin(new Base64());
            $filesystem->addPlugin(new Config());
            $filesystem->addPlugin(new MultiUpload());

            return $filesystem;
        });

        Storage::extend('cos', function () {
            $adapter = new RakanAdapter('cos');

            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new Signature());
            $filesystem->addPlugin(new Policy());
            $filesystem->addPlugin(new Verify());
            $filesystem->addPlugin(new Base64());
            $filesystem->addPlugin(new Config());
            $filesystem->addPlugin(new MultiUpload());

            return $filesystem;
        });

        Storage::extend('obs', function () {
            $adapter = new RakanAdapter('obs');

            $filesystem = new Filesystem($adapter);

            $filesystem->addPlugin(new Signature());
            $filesystem->addPlugin(new Policy());
            $filesystem->addPlugin(new Verify());
            $filesystem->addPlugin(new Base64());
            $filesystem->addPlugin(new Config());
            $filesystem->addPlugin(new MultiUpload());

            return $filesystem;
        });
    }

    public function provides()
    {
        return ['rakan.oss', 'rakan.qiniu', 'rakan.obs', 'rakan.cos'];
    }
}
