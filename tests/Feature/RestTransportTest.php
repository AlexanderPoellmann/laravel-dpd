<?php

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Exceptions\ConfigurationException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends documented rest envelopes and hashes a plain password when needed', function () {
    config()->set('dpd.password_md5');
    config()->set('dpd.password', 'test-secret');

    Http::fake([
        '*' => Http::response(['status' => 'ok', 'result' => 'OK']),
    ]);

    app(DpdTransport::class)->call(ApiFunction::PickupOrder, [
        'datum' => '20260925',
        'anzahl' => '1',
        'bemerkung' => '',
    ]);

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return $request->url() === config('dpd.endpoint')
            && $data['service'] === 'PaketomatRest'
            && $data['function'] === 'abholauftrag'
            && $data['data']['username'] === 'test-user'
            && $data['data']['password'] === md5('test-secret')
            && $data['data']['mandant'] === '1234'
            && $data['data']['datum'] === '20260925';
    });
});

it('omits the client number for the status operation', function () {
    Http::fake([
        '*' => Http::response(['status' => 'ok', 'result' => []]),
    ]);

    app(DpdTransport::class)->call(ApiFunction::Status);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('mandant', $request->data()['data']));
});

it('turns api failures into typed exceptions with known matchcodes', function () {
    Http::fake([
        '*' => Http::response([
            'status' => 'error',
            'result' => '[0] ER02-Passwort falsch',
        ]),
    ]);

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::Status))
        ->toThrow(DpdApiException::class, 'ER02');
});

it('retries safe operations after an HTTP failure', function (ApiFunction $function) {
    config()->set(['dpd.retries' => 2, 'dpd.retry_delay_ms' => 0]);
    Http::fakeSequence()->pushStatus(503)->push(['status' => 'ok', 'result' => []]);

    expect(app(DpdTransport::class)->call($function)->successful())->toBeTrue();

    Http::assertSentCount(2);
})->with([ApiFunction::Status, ApiFunction::CreateLabelSheet, ApiFunction::ReprintLabel, ApiFunction::SelfBookingList]);

it('never retries state-changing operations after an HTTP failure', function (ApiFunction $function) {
    config()->set(['dpd.retries' => 3, 'dpd.retry_delay_ms' => 0]);
    Http::fakeSequence()->pushStatus(503)->push(['status' => 'ok', 'result' => []]);

    expect(fn () => app(DpdTransport::class)->call($function))
        ->toThrow(DpdTransportException::class, 'HTTP 503');

    Http::assertSentCount(1);
})->with([ApiFunction::CreateLabel, ApiFunction::CancelLabel, ApiFunction::PickupOrder, ApiFunction::CollectionRequest, ApiFunction::ImportOrder]);

it('wraps exhausted HTTP failures in a transport exception', function () {
    config()->set(['dpd.retries' => 2, 'dpd.retry_delay_ms' => 0]);
    Http::fakeSequence()->pushStatus(503)->pushStatus(503);

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::Status))
        ->toThrow(DpdTransportException::class, 'HTTP 503');

    Http::assertSentCount(2);
});

it('preserves the original connection exception', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out.'));

    try {
        app(DpdTransport::class)->call(ApiFunction::CreateLabel);
        test()->fail('Expected a transport exception.');
    } catch (DpdTransportException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and($exception->getPrevious()->getMessage())->toBe('Connection timed out.')
            ->and($exception->getMessage())->toBe('Unable to communicate with DPD.');
    }
});

it('does not disguise programming errors as transport failures', function () {
    Http::fake(fn () => throw new LogicException('Invalid request middleware.'));

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::Status))
        ->toThrow(LogicException::class, 'Invalid request middleware.');
});

it('rejects invalid JSON responses', function (string $body) {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::Status))
        ->toThrow(DpdTransportException::class, 'invalid JSON response');
})->with(['<html>Unavailable</html>', 'null', '"unexpected"']);

it('validates credentials before sending a request', function (array $configuration) {
    config()->set($configuration);
    Http::fake();

    expect(fn () => app(DpdTransport::class)->call(ApiFunction::CreateLabel))
        ->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
})->with([
    'username' => [['dpd.username' => '']],
    'password' => [['dpd.password' => '', 'dpd.password_md5' => '']],
    'client' => [['dpd.client' => '']],
]);

it('prefers the configured password digest and allows status without a client number', function () {
    config()->set(['dpd.password' => 'ignored', 'dpd.client' => null]);
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => []])]);

    app(DpdTransport::class)->call(ApiFunction::Status);

    Http::assertSent(fn (Request $request): bool => $request['data']['password'] === md5('test-secret')
        && ! array_key_exists('mandant', $request['data']));
});
