<?php

namespace Tests;

use Illuminate\Support\Carbon;
use Overtrue\LaravelVersionable\Diff;
use Overtrue\LaravelVersionable\Version;
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

        $this->assertDatabaseCount('versions', 1);

        // version2
        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame($post->only('title'), $post->lastVersion->contents);
        $this->assertDatabaseCount('versions', 2);
    }

    /**
     * @test
     */
    public function it_can_work_with_snapshot_strategy()
    {
        $post = new Post(['title' => 'Title', 'content' => 'Content']);
        $post->setVersionStrategy(VersionStrategy::SNAPSHOT);

        $post->save();
        $this->assertDatabaseCount('versions', 1);

        $this->travelTo(now()->addMinute());

        $post->setVersionable(['title']);
        $post->update(['title' => 'title changed']);
        $this->assertDatabaseCount('versions', 2);

        // content is not versionable
        $post->update(['content' => 'content changed']);
        $this->assertDatabaseCount('versions', 2);
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
        $this->assertArrayHasKey('title', $post->lastVersion->contents);
        $this->assertArrayHasKey('content', $post->lastVersion->contents);
        $this->assertArrayHasKey('created_at', $post->lastVersion->contents);
        $this->assertArrayHasKey('updated_at', $post->lastVersion->contents);
        $this->assertArrayHasKey('id', $post->lastVersion->contents);
        $this->assertArrayHasKey('user_id', $post->lastVersion->contents);
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

        // #29
        $version = $post->firstVersion;
        $post = $version->revertWithoutSaving();

        $this->assertSame('version1', $post->title);
        $this->assertSame('version1 content', $post->content);

        $post->refresh();

        // revert version 2
        $post->revertToVersion($post->firstVersion->nextVersion()->id);
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

        $this->assertInstanceOf(Diff::class, $post->lastVersion->diff());

        $post->update(['title' => 'version2']);
        $post->refresh();

        $this->assertSame(['title' => ['old' => 'version1', 'new' => 'version2']], $post->lastVersion->diff()->toArray());
    }

    /**
     * @test
     */
    public function user_can_get_previous_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3']);

        $post->refresh();

        $this->assertEquals('version3', $post->latestVersion->contents['title']);
        $this->assertEquals('version2', $post->latestVersion->previousVersion()->contents['title']);
        $this->assertEquals('version1', $post->latestVersion->previousVersion()->previousVersion()->contents['title']);
        $this->assertNull($post->latestVersion->previousVersion()->previousVersion()->previousVersion());
    }

    /**
     * @test
     */
    public function user_can_get_next_version()
    {
        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);
        $post->update(['title' => 'version3']);

        $post->refresh();

        $this->assertEquals('version1', $post->firstVersion->contents['title']);
        $this->assertEquals('version2', $post->firstVersion->nextVersion()->contents['title']);
        $this->assertEquals('version3', $post->firstVersion->nextVersion()->nextVersion()->contents['title']);
        $this->assertNull($post->firstVersion->nextVersion()->nextVersion()->nextVersion());
    }

    /**
     * @test
     */
    public function previous_versions_created_later_on_will_have_correct_order()
    {
        $this->travelTo(Carbon::create(2022, 10, 2, 14, 0));

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        $post->update(['title' => 'version2']);

        $this->travelTo(Carbon::create(2022, 10, 2, 15, 0));
        $post->update(['title' => 'version5']);

        $post->refresh();

        $post->title = 'version4';
        $post->createVersion([], Carbon::create(2022, 10, 2, 14, 30));
        $post->createVersion(['title' => 'version3'], Carbon::create(2022, 10, 2, 14, 0));

        $post->refresh();

        $this->assertEquals('version5', $post->title);
        $this->assertEquals('version5', $post->latestVersion->contents['title']);
        $this->assertEquals('version4', $post->latestVersion->previousVersion()->contents['title']);
        $this->assertEquals('version3', $post->latestVersion->previousVersion()->previousVersion()->contents['title']);
        $this->assertEquals('version2', $post->latestVersion->previousVersion()->previousVersion()->previousVersion()->contents['title']);
        $this->assertEquals('version1', $post->latestVersion->previousVersion()->previousVersion()->previousVersion()->previousVersion()->contents['title']);
        $this->assertNull($post->latestVersion->previousVersion()->previousVersion()->previousVersion()->previousVersion()->previousVersion());
    }

    /**
     * @test
     */
    public function user_can_get_ordered_history()
    {
        $post = Post::create(['title' => 'version2', 'content' => 'version2 content']);
        $post->update(['title' => 'version3']);
        $post->update(['title' => 'version4']);

        $post->createVersion(['title' => 'version1'], Carbon::now()->subDay(1));

        $this->assertEquals(
            ['version4', 'version3', 'version2', 'version1'],
            $post->history->pluck('contents.title')->toArray(),
        );
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
        $post = new Post;

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
        $post = new Post;

        Post::withoutVersion(function () use (&$post) {
            $user = User::create(['name' => 'overtrue']);
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content', 'user_id' => $user->id]);
        });

        $post->user;
        $this->assertArrayHasKey('user', $post->toArray());

        $post->update(['title' => 'version2']);

        $this->assertArrayNotHasKey('user', $post->latestVersion->contents);
    }

    /**
     * @test
     */
    public function it_creates_initial_version()
    {
        $post = new Post;

        Post::withoutVersion(function () use (&$post) {
            $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
        });

        $this->assertCount(0, $post->versions);

        $post->update(['title' => 'version2']);

        $post->refresh();

        $this->assertCount(2, $post->versions);
        $this->assertSame('version1', $post->firstVersion->contents['title']);
        $this->assertSame('version2', $post->lastVersion->contents['title']);
    }
}
