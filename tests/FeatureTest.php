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
 * Class FeatureTest.
 */
class FeatureTest extends TestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => User::class]);

        $this->user = User::create(['name' => 'overtrue']);
        $this->actingAs($this->user);
    }

    /**
     * @test
     */
    public function post_has_versions()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertSame($post->only('title', 'content'), $post->lastVersion->contents);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame($post->only('title'), $post->lastVersion->contents);
    }

    /**
     * @test
     */
    public function post_can_revert_to_target_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3', 'content' => 'version3 content']);
        $post->update(['title' => 'version4', 'content' => 'version4 content']);

        // revert version 2
        $post->revertToVersion(2);
        $post->refresh();

        // only title updated
        $this->assertSame('version2', $post->title);
        $this->assertSame('version4 content', $post->content);

        // revert version 3
        $post->revertToVersion(3);
        $post->refresh();

        // title and content are updated
        $this->assertSame('version3', $post->title);
        $this->assertSame('version3 content', $post->content);
    }

    /**
     * @test
     */
    public function user_can_get_diff_of_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertSame("--- Original\n+++ New\n", $post->lastVersion->diff($post));

        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertSame("--- Original\n+++ New\n@@ @@\n-version1\n+version2\n", $post->lastVersion->diff($post->getVersion(1)));
    }

    /**
     * @test
     */
    public function post_will_keep_versions()
    {
        \config(['versionable.keep_versions' => 3]);

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3', 'content' => 'version3 content']);
        $post->update(['title' => 'version4', 'content' => 'version4 content']);
        $post->update(['title' => 'version5', 'content' => 'version5 content']);

        $this->assertCount(3, $post->versions);

        $post->removeAllVersions();
        $post->refresh();

        $this->assertCount(0, $post->versions);
    }
}
