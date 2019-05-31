<?php

/*
 * This file is part of the overtrue/laravel-versionable.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

/**
 * Class User.
 */
class User extends \Illuminate\Foundation\Auth\User
{
    protected $fillable = ['name'];
}
