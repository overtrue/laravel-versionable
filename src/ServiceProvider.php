<?php

namespace Overtrue\LaravelVersionable;

/**
 * Class ServiceProvider
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../migrations');

        $this->publishes([
            __DIR__.'/../migrations' => \database_path('migrations'),
            __DIR__.'/../config/versionable.php' => \config_path('versionable.php'),
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/versionable.php', 'versionable'
        );
    }
}
