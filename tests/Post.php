<?php

/*
 * This file is part of the overtrue/laravel-like.
 *
 * (c) overtrue <anzhengchao@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Overtrue\LaravelVersionable\Versionable;

/**
 * Class Post.
 */
class Post extends Model
{
    use Versionable;

    protected $fillable = ['title', 'content', 'user_id'];

    protected $versionable = ['title', 'content'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function(Post $post){
            $post->user_id = \auth()->id();
        });
    }
}
