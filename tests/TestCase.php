<?php

namespace AlexanderPoellmann\LaravelDpd\Tests;

use AlexanderPoellmann\LaravelDpd\LaravelDpdServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @param array<string, mixed> $data */
    protected function assertDpdPayload(string $function, array $data, bool $withClient = true): void
    {
        $expected = [
            'service' => 'PaketomatRest',
            'function' => $function,
            'data' => [
                'username' => 'test-user',
                'password' => md5('test-secret'),
                ...($withClient ? ['mandant' => '1234'] : []),
                ...$data,
            ],
        ];

        Http::assertSent(function (Request $request) use ($expected): bool {
            expect($request->method())->toBe('POST')
                ->and($request->url())->toBe(config('dpd.endpoint'))
                ->and($request->hasHeader('Content-Type', 'application/json'))->toBeTrue()
                ->and($request->data())->toBe($expected)
                ->and(json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR))->toBe($expected);

            return true;
        });

        Http::assertSentCount(1);
    }

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
