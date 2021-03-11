<?php

namespace Tests;

use Overtrue\LaravelVersionable\VersionStrategy;

class FeatureTest extends TestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Post::enableVersioning();

        config([
            'auth.providers.users.model' => User::class,
            'versionable.user_model' => User::class,
       ]);

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
    public function post_create_version_with_strategy()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertSame($post->only('title', 'content'), $post->lastVersion->contents);

        $post->setVersionStrategy(VersionStrategy::SNAPSHOT);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame($post->only('title', 'content'), $post->lastVersion->contents);
        $this->assertSame('version1 content', $post->lastVersion->contents['content']);
    }

    /**
     * @test
     */
    public function post_can_revert_to_target_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2', 'extends' => ['foo' => 'bar']]);
        $post->update(['title' => 'version3', 'content' => 'version3 content', 'extends' => ['name' => 'overtrue']]);
        $post->update(['title' => 'version4', 'content' => 'version4 content']);

        // revert version 2
        $post->revertToVersion(2);
        $post->refresh();

        // only title updated
        $this->assertSame('version2', $post->title);
        $this->assertSame('version4 content', $post->content);
        $this->assertSame(['foo' => 'bar'], $post->extends);

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


    /**
     * @test
     */
    public function user_can_disable_version_control()
    {
        $post = null;
        Post::withoutVersion(function () use (&$post) {
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        });

        $this->assertCount(0, $post->versions);

        // version2
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->refresh();
        $this->assertCount(1, $post->versions);

        $this->assertTrue(Post::getVersioning());

        Post::withoutVersion(function () use ($post) {
            $post->update(['title' => 'version2']);
        });

        $this->assertTrue(Post::getVersioning());
        $post->refresh();

        $this->assertCount(1, $post->versions);
        $this->assertSame(['title' => 'version1', 'content' => 'version1 content'], $post->lastVersion->contents);

        Post::disableVersioning();
        Post::withoutVersion(function () use ($post) {
            $post->update(['title' => 'version2']);
        });

        $this->assertFalse(Post::getVersioning());
        $post->refresh();
    }

    /**
     * @test
     */
    public function post_version_soft_delete_and_restore()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertSame($post->only('title', 'content'), $post->lastVersion->contents);
        $this->assertDatabaseCount('versions', 1);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame($post->only('title'), $post->lastVersion->contents);
        $this->assertDatabaseCount('versions', 2);

        // version3
        $post->update(['title' => 'version3']);
        $post->refresh();
        $this->assertDatabaseCount('versions', 3);


        // soft delete
        $post->refresh();
        // first
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 3);
        $this->assertCount(1, $post->getThrashedVersions());

        // second delete
        $post->refresh();
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 3);
        $this->assertCount(1, $post->refresh()->versions);
        $this->assertCount(2, $post->getThrashedVersions());

        // restore second deleted version
        $post->restoreTrashedVersion($lastVersion->id);
        $this->assertCount(2, $post->refresh()->versions);
    }

    /**
     * @test
     */
    public function post_version_forced_delete()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertCount(1, $post->versions);
        $this->assertSame($post->only('title', 'content'), $post->lastVersion->contents);
        $this->assertDatabaseCount('versions', 1);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame($post->only('title'), $post->lastVersion->contents);
        $this->assertDatabaseCount('versions', 2);

        // version3
        $post->update(['title' => 'version3']);
        $post->refresh();
        $this->assertDatabaseCount('versions', 3);


        // forced delete
        $post->enableForceDeleteVersion();

        // first
        $post->refresh();
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 2);

        // second delete
        $post->refresh();
        $lastVersion = $post->lastVersion;
        $post->removeVersion($lastVersion->id);
        $this->assertDatabaseCount('versions', 1);
    }

    /**
     * @test
     */
    public function relations_will_not_in_version_contents()
    {
        $post = null;
        Post::withoutVersion(function () use (&$post) {
            $user = User::create(['name' => 'overtrue']);
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content', 'user_id' => $user->id]);
        });

        $post->user;
        $this->assertArrayHasKey('user', $post->toArray());

        $post->update(['title' => 'version2']);

        $this->assertArrayNotHasKey('user', $post->latestVersion->contents);
    }
}
