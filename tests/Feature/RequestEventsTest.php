<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestFailed;
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestStarted;
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestSucceeded;
use AlexanderPoellmann\LaravelDpd\Exceptions\ConfigurationException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use AlexanderPoellmann\LaravelDpd\Services\RequestEvents;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Event::fake([DpdRequestStarted::class, DpdRequestSucceeded::class, DpdRequestFailed::class]);
});

it('emits no package events by default for API requests or downloads', function () {
    expect(config('dpd.events.enabled'))->toBeFalse();
    Http::fake([
        'https://ws-etikett.paketomat.at/*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf']),
        '*' => Http::response(['status' => 'ok', 'result' => []]),
    ]);

    app(LaravelDpd::class)->serviceStatus();
    app(LaravelDpd::class)->downloadLabel('https://ws-etikett.paketomat.at/label.pdf');

    Event::assertNotDispatched(DpdRequestStarted::class);
    Event::assertNotDispatched(DpdRequestSucceeded::class);
    Event::assertNotDispatched(DpdRequestFailed::class);
});

it('emits started and succeeded events with only approved metadata', function () {
    config()->set(['dpd.events.enabled' => true, 'dpd.username' => 'credential-user', 'dpd.password_md5' => md5('credential-password')]);
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => 'OK private-recipient private-address private-token'])]);

    app(DpdTransport::class)->call(ApiFunction::PickupOrder, [
        'name' => 'private-recipient', 'address' => 'private-address', 'email' => 'private@example.com',
    ]);

    Event::assertDispatched(DpdRequestStarted::class, fn (DpdRequestStarted $event): bool => (array) $event === ['operation' => 'abholauftrag']);
    Event::assertDispatched(DpdRequestSucceeded::class, function (DpdRequestSucceeded $event): bool {
        expect(array_keys((array) $event))->toBe(['operation', 'durationMilliseconds', 'httpStatus'])
            ->and($event->durationMilliseconds)->toBeGreaterThanOrEqual(0.0)
            ->and(is_finite($event->durationMilliseconds))->toBeTrue()
            ->and(serialize($event))->not->toContain('credential-user', md5('credential-password'), 'private-recipient', 'private-address', 'private-token', 'private@example.com');

        return $event->operation === 'abholauftrag' && $event->httpStatus === 200;
    });
    Event::assertDispatchedTimes(DpdRequestStarted::class, 1);
    Event::assertDispatchedTimes(DpdRequestSucceeded::class, 1);
    Event::assertNotDispatched(DpdRequestFailed::class);
});

it('emits sanitized failures for HTTP API and parsing errors', function (mixed $body, int $status, ?string $errorCode) {
    config()->set('dpd.events.enabled', true);
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::CreateLabel))->toThrow(DpdException::class);

    Event::assertDispatched(DpdRequestFailed::class, function (DpdRequestFailed $event) use ($status, $errorCode): bool {
        expect(array_keys((array) $event))->toBe(['operation', 'durationMilliseconds', 'httpStatus', 'errorCode'])
            ->and($event->durationMilliseconds)->toBeGreaterThanOrEqual(0.0)
            ->and(serialize($event))->not->toContain('private-recipient', 'private-address', 'private-token', 'test-user', md5('test-secret'));

        return $event->operation === 'getLabel' && $event->httpStatus === $status && $event->errorCode === $errorCode;
    });
    Event::assertDispatchedTimes(DpdRequestStarted::class, 1);
    Event::assertDispatchedTimes(DpdRequestFailed::class, 1);
    Event::assertNotDispatched(DpdRequestSucceeded::class);
})->with([
    'HTTP' => ['private-recipient private-address private-token', 503, null],
    'invalid JSON' => ['private-recipient private-address private-token', 200, null],
    'malformed response' => [['status' => 'ok', 'private' => 'private-recipient'], 200, null],
    'API' => [['status' => 'error', 'result' => '[0] ER04-private-recipient private-address'], 200, 'ER04'],
    'service code' => [['status' => 'error', 'result' => '[0] PR02-hv-private-token'], 200, 'PR02-hv'],
    'unknown code' => [['status' => 'error', 'result' => '[0] ZZ99-private-token'], 200, 'ZZ99'],
    'uncoded error' => [['status' => 'error', 'result' => 'private-recipient private-token'], 200, null],
    'global error in ok envelope' => [['status' => 'ok', 'result' => ['err_code' => 'ER02-private-token']], 200, 'ER02'],
]);

it('keeps connection details out of events and preserves the exception', function () {
    config()->set('dpd.events.enabled', true);
    $cause = new ConnectionException('private-url-token private-recipient');
    Http::fake(fn () => throw $cause);

    try {
        app(DpdTransport::class)->call(ApiFunction::CreateLabel);
        test()->fail('Expected connection failure.');
    } catch (DpdTransportException $exception) {
        expect($exception->getPrevious())->toBe($cause);
    }

    Event::assertDispatched(DpdRequestFailed::class, function (DpdRequestFailed $event): bool {
        expect(serialize($event))->not->toContain('private-url-token', 'private-recipient');

        return $event->httpStatus === null && $event->errorCode === null;
    });
});

it('reports one lifecycle for a retried logical request', function (bool $recovers) {
    config()->set(['dpd.events.enabled' => true, 'dpd.retries' => 2, 'dpd.retry_delay_ms' => 0]);
    Http::fakeSequence()->pushStatus(503)->push(['status' => 'ok', 'result' => []], $recovers ? 200 : 503);

    if ($recovers) {
        app(LaravelDpd::class)->serviceStatus();
        Event::assertDispatchedTimes(DpdRequestSucceeded::class, 1);
        Event::assertDispatched(DpdRequestSucceeded::class, fn (DpdRequestSucceeded $event): bool => $event->httpStatus === 200);
        Event::assertNotDispatched(DpdRequestFailed::class);
    } else {
        expect(fn () => app(LaravelDpd::class)->serviceStatus())->toThrow(DpdTransportException::class);
        Event::assertDispatchedTimes(DpdRequestFailed::class, 1);
        Event::assertDispatched(DpdRequestFailed::class, fn (DpdRequestFailed $event): bool => $event->httpStatus === 503);
        Event::assertNotDispatched(DpdRequestSucceeded::class);
    }

    Event::assertDispatchedTimes(DpdRequestStarted::class, 1);
    Http::assertSentCount(2);
})->with([true, false]);

it('emits sanitized download events without including the temporary URL or contents', function (bool $succeeds) {
    config()->set('dpd.events.enabled', true);
    $url = 'https://ws-etikett.paketomat.at/secure/private-token.pdf';
    Http::fake([$url => Http::response($succeeds ? '%PDF-1.7 private-recipient' : 'private-recipient', $succeeds ? 200 : 410)]);

    if ($succeeds) {
        app(LaravelDpd::class)->downloadLabel($url);
        $eventClass = DpdRequestSucceeded::class;
    } else {
        expect(fn () => app(LaravelDpd::class)->downloadLabel($url))->toThrow(DpdTransportException::class);
        $eventClass = DpdRequestFailed::class;
    }

    Event::assertDispatched(DpdRequestStarted::class, fn (DpdRequestStarted $event): bool => $event->operation === 'downloadLabel');
    Event::assertDispatched($eventClass, function ($event) use ($succeeds): bool {
        expect(serialize($event))->not->toContain('private-token', 'private-recipient', 'https://', '%PDF');

        return $event->operation === 'downloadLabel' && $event->httpStatus === ($succeeds ? 200 : 410);
    });
})->with([true, false]);

it('emits no events for invalid input rejected before a request starts', function () {
    config()->set(['dpd.events.enabled' => true, 'dpd.username' => null]);
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->serviceStatus())->toThrow(ConfigurationException::class)
        ->and(fn () => app(LaravelDpd::class)->downloadLabel('https://example.com/label.pdf'))->toThrow(DpdResponseException::class);

    Event::assertNotDispatched(DpdRequestStarted::class);
    Event::assertNotDispatched(DpdRequestSucceeded::class);
    Event::assertNotDispatched(DpdRequestFailed::class);
    Http::assertNothingSent();
});

it('does not forward unchecked error strings into event metadata', function () {
    config()->set('dpd.events.enabled', true);

    (new RequestEvents('getLabel'))->failed(200, 'ER02-private-recipient@example.com');

    Event::assertDispatched(DpdRequestFailed::class, fn (DpdRequestFailed $event): bool => $event->errorCode === null);
});

it('preserves programming exceptions without putting them into events', function () {
    config()->set('dpd.events.enabled', true);
    $error = new LogicException('private-token');
    Http::fake(fn () => throw $error);

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::CreateLabel))->toThrow($error);

    Event::assertDispatched(DpdRequestFailed::class, fn (DpdRequestFailed $event): bool => $event->httpStatus === null && $event->errorCode === null);
});
