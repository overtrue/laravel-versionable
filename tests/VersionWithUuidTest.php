<?php

namespace Tests;

class VersionWithUuidTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('versionable.uuid', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Post::enableVersioning();

        config([
            'auth.providers.users.model' => User::class,
            'versionable.user_model' => User::class,
        ]);
    }

    public function test_uuid()
    {
        $user = User::create(['name' => 'overtrue']);
        $this->actingAs($user);

        $post = Post::create(['title' => 'Hello world!', 'content' => 'Hello world!']);
        $version = $post->versions()->first();

        $this->assertIsString($version->id);
    }
}
