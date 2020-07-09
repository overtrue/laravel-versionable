<?php

/*
 * This file is part of the overtrue/laravel-versionable.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Class Post.
 */
class Post extends Model
{
    use Versionable;

    protected $fillable = ['title', 'content', 'user_id'];

    protected $versionable = ['title', 'content'];

    protected $versionStrategy = VersionStrategy::DIFF;

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Post $post) {
            $post->user_id = \auth()->id();
        });
    }

    public function enableForceDeleteVersion()
    {
        $this->forceDeleteVersion = true;
    }

    public function disableForceDeleteVersion()
    {
        $this->forceDeleteVersion = false;
    }
}
