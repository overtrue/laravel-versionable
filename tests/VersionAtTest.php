<?php

namespace Tests;

use Illuminate\Support\Carbon;

class VersionAtTest extends TestCase
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

        $this->user = User::create(['name' => 'marijoo']);
        $this->actingAs($this->user);
    }

    /**
     * @test
     */
    public function it_can_find_version_at_specific_time()
    {
        $this->travelTo(Carbon::create(2022, 10, 1, 12, 0));

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->travelTo(Carbon::create(2022, 10, 2, 14, 0));

        $post->update(['title' => 'version2']);

        $this->travelTo(Carbon::create(2022, 10, 3, 10, 0));

        $post->update(['title' => 'version3']);

        $this->assertEquals('version1', $post->versionAt('2022-10-02 10:00:00')->contents['title']);
        $this->assertEquals('version1', $post->versionAt('2022-10-02 13:59:59')->contents['title']);
        $this->assertEquals('version2', $post->versionAt(Carbon::create(2022, 10, 02, 14))->contents['title']);
        $this->assertEquals('version2', $post->versionAt('2022-10-02 15:00:00')->contents['title']);
        $this->assertEquals('version3', $post->versionAt('2022-10-03 10:00:00')->contents['title']);
    }

    /**
     * @test
     */
    public function it_returns_null_if_given_time_is_before_first_version()
    {
        $this->travelTo(Carbon::create(2022, 10, 1, 12, 0));

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->assertNull($post->versionAt('2022-10-01 11:59:59'));
        $this->assertEquals('version1', $post->versionAt('2022-10-01 12:00:00')->contents['title']);
    }

    /**
     * @test
     */
    public function it_returns_latest_version_if_given_time_is_in_future()
    {
        $this->travelTo(Carbon::create(2022, 10, 1, 12, 0));

        $post = Post::create(['title' => 'version1', 'content' => 'version1 content']);

        $this->travelTo(Carbon::create(2022, 10, 2, 14, 0));

        $post->update(['title' => 'version2']);

        $this->assertEquals('version2', $post->versionAt('2024-11-01 12:00:00')->contents['title']);
    }
}
