<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Enums\DpdErrorCode;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Support\Facades\Http;

it('exposes structured errors from failed API responses', function (array $payload) {
    Http::fake(['*' => Http::response($payload)]);

    try {
        app(DpdTransport::class)->call(ApiFunction::CreateLabel);
        test()->fail('Expected an API exception.');
    } catch (DpdApiException $exception) {
        expect($exception->errors)->toHaveCount(1)
            ->and($exception->errors[0]->code)->toBe('ER02')
            ->and($exception->errors[0]->message)->toBe('Passwort falsch')
            ->and($exception->errors[0]->index)->toBe(0)
            ->and($exception->errors[0]->knownCode)->toBe(DpdErrorCode::InvalidPassword);
    }
})->with([
    'error string' => [['status' => 'error', 'result' => '[0] ER02-Passwort falsch']],
    'error object' => [['status' => 'error', 'result' => ['err_code' => '[0] ER02-Passwort falsch']]],
    // Request und Response.pdf section 3.17, page 88.
    'documented ok envelope with global error' => [['status' => 'ok', 'result' => ['err_code' => '[0] ER02-Passwort falsch', 'label' => '']]],
]);

it('preserves successful sibling labels when another label has a structured error', function () {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => [
        ['label' => 'https://ws-etikett.paketomat.at/secure/example.pdf', 'paknr' => '06215000000217'],
        ['label' => null, 'paknr' => null, 'err_code' => '[1] PR02-hv-Invalid insurance'],
    ]])]);

    $result = app(LaravelDpd::class)->createLabel(new LabelRequest(
        new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        [new Parcel, new Parcel],
        Products::normalParcel(),
        new DateTimeImmutable('2024-11-12'),
    ));

    expect($result->labels)->toHaveCount(2)
        ->and($result->successful())->toBeFalse()
        ->and($result->labels[0]->successful())->toBeTrue()
        ->and($result->labels[0]->errors)->toBe([])
        ->and($result->labels[1]->errors[0]->code)->toBe('PR02-hv')
        ->and($result->labels[1]->errors[0]->index)->toBe(1)
        ->and($result->labels[1]->errors[0]->knownCode)->toBe(DpdErrorCode::InvalidHigherInsurance);
});

it('throws package response exceptions for unexpected response structures', function (array $payload) {
    Http::fake(['*' => Http::response($payload)]);

    expect(fn () => app(LaravelDpd::class)->status())->toThrow(DpdResponseException::class);
})->with([
    'missing result' => [['status' => 'ok']],
    'non-string status' => [['status' => [], 'result' => []]],
    'incorrect status list' => [['status' => 'ok', 'result' => 'OK']],
    'incorrect error field' => [['status' => 'ok', 'result' => ['err_code' => []]]],
    'malformed error result' => [['status' => 'error', 'result' => ['unexpected' => 'sensitive']]],
]);

it('omits raw API response bodies from transport error messages', function (string $body, int $status) {
    Http::fake(['*' => Http::response($body, $status)]);

    try {
        app(DpdTransport::class)->call(ApiFunction::CreateLabel);
        test()->fail('Expected a transport exception.');
    } catch (DpdTransportException $exception) {
        expect($exception->getMessage())->not->toContain($body, 'private-token')
            ->and($exception->rawBody)->toBe($body);
    }
})->with([
    ['<html>private-token recipient address</html>', 503],
    ['<html>private-token recipient address</html>', 200],
]);
