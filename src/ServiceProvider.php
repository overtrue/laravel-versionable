<?php

/*
 * This file is part of the overtrue/laravel-versionable.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\LaravelVersionable;

/**
 * Class ServiceProvider.
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../migrations');

        $this->publishes([
            __DIR__.'/../migrations' => \database_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../config/versionable.php' => \config_path('versionable.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/versionable.php', 'versionable'
        );
    }
}
