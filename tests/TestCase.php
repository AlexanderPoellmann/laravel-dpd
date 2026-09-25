<?php

namespace AlexanderPoellmann\LaravelDpd\Tests;

use AlexanderPoellmann\LaravelDpd\LaravelDpdServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [LaravelDpdServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('dpd.username', 'test-user');
        $app['config']->set('dpd.client', '1234');
        $app['config']->set('dpd.password_md5', md5('test-secret'));
        $app['config']->set('dpd.retries', 0);
    }
}
