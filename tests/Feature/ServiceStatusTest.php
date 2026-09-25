<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Facades\Dpd;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('returns identical service availability through both method names', function (bool $facade) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => [
        ['serviceid' => 19, 'name' => 'WEB.Service', 'status' => 'Im Betrieb'],
    ]])]);

    $current = $facade ? Dpd::serviceStatus() : app(LaravelDpd::class)->serviceStatus();
    $legacy = $facade ? Dpd::status() : app(LaravelDpd::class)->status();

    expect($legacy)->toEqual($current)
        ->and($current[0]->name)->toBe('WEB.Service')
        ->and($current[0]->status)->toBe('Im Betrieb');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->data() === [
        'service' => 'PaketomatRest',
        'function' => 'getStatus',
        'data' => ['username' => 'test-user', 'password' => md5('test-secret')],
    ]);
    $recorded = Http::recorded();
    expect($recorded[0][0]->body())->toBe($recorded[1][0]->body());
})->with(['client' => false, 'facade' => true]);

it('preserves error behavior through both availability method names', function (string $method) {
    Http::fake(['*' => Http::response(['status' => 'error', 'result' => '[0] ER02-Passwort falsch'])]);

    expect(fn () => app(LaravelDpd::class)->{$method}())->toThrow(DpdApiException::class, 'ER02');
})->with(['serviceStatus', 'status']);

it('documents the deprecated status alias', function () {
    expect((new ReflectionMethod(LaravelDpd::class, 'status'))->getDocComment())->toContain('@deprecated', 'serviceStatus()');
});
