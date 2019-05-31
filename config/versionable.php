<?php

/*
 * This file is part of the overtrue/laravel-versionable.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled.
 */

return [
    /*
     * Keep versions, you can redefine in target model.
     * Default: 0 - Keep all versions.
     */
    'keep_versions' => 0,

    /*
     * User foreign key name of versions table.
     */
    'user_foreign_key' => 'user_id',

    /*
     * The model class for store versions.
     */
    'version_model' => \Overtrue\LaravelVersionable\Version::class,
];
