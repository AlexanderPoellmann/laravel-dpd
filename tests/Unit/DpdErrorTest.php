<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\DpdError;
use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Enums\DpdErrorCode;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;

it('parses codes messages indexes and known enum values', function (string $input, ?string $code, string $message, ?int $index, ?DpdErrorCode $known) {
    $error = DpdError::fromString($input);

    expect($error->code)->toBe($code)
        ->and($error->message)->toBe($message)
        ->and($error->index)->toBe($index)
        ->and($error->knownCode)->toBe($known);
})->with([
    ['[0] ER02-Passwort falsch', 'ER02', 'Passwort falsch', 0, DpdErrorCode::InvalidPassword],
    ['[12] PR02-hv-Invalid amount', 'PR02-hv', 'Invalid amount', 12, DpdErrorCode::InvalidHigherInsurance],
    ['PR03-nnbar: Invalid COD', 'PR03-nnbar', 'Invalid COD', null, DpdErrorCode::InvalidPrimetimeCashOnDelivery],
    ['FR06 Already cancelled', 'FR06', 'Already cancelled', null, DpdErrorCode::AlreadyCancelled],
    ['[2] ZZ99-Future error', 'ZZ99', 'Future error', 2, null],
    ['ER99-Unknown DPD code', 'ER99', 'Unknown DPD code', null, null],
    ['[3] Uncoded error', null, 'Uncoded error', 3, null],
    ['Uncoded error', null, 'Uncoded error', null, null],
    ['ER12', 'ER12', '', null, DpdErrorCode::WeightLimitExceeded],
]);

it('recognizes every documented code including service suffixes', function (DpdErrorCode $code) {
    expect(DpdError::fromString('[0] '.$code->value.'-Description')->knownCode)->toBe($code);
})->with(DpdErrorCode::cases());

it('exposes all indexed errors while retaining first-error compatibility properties', function () {
    $exception = DpdApiException::fromErrorString("[0] ER04-Name missing\n[1] ER05-Address missing");

    expect($exception->errors)->toHaveCount(2)
        ->and($exception->errors[0]->message)->toBe('Name missing')
        ->and($exception->errors[1]->code)->toBe('ER05')
        ->and($exception->errors[1]->index)->toBe(1)
        ->and($exception->errorCode)->toBe('ER04')
        ->and($exception->knownErrorCode)->toBe(DpdErrorCode::InvalidName);
});

it('parses documented err_code objects and error lists', function () {
    $single = DpdApiException::fromResponse(DpdResponse::fromArray(['status' => 'error', 'result' => ['err_code' => '[0] ER02-Passwort falsch']]));
    $multiple = DpdApiException::fromResponse(DpdResponse::fromArray(['status' => 'error', 'result' => [
        ['err_code' => 'ER04-Name missing'],
        '[7] ER05-Address missing',
    ]]));

    expect($single->errors[0]->code)->toBe('ER02')
        ->and($multiple->errors)->toHaveCount(2)
        ->and($multiple->errors[0]->index)->toBe(0)
        ->and($multiple->errors[1]->index)->toBe(7);
});

it('exposes structured label errors without removing the original error string', function () {
    $label = Label::fromArray(['err_code' => '[2] ER12-Weight limit exceeded']);

    expect($label->errorCode)->toBe('[2] ER12-Weight limit exceeded')
        ->and($label->errors[0]->code)->toBe('ER12')
        ->and($label->errors[0]->index)->toBe(2)
        ->and($label->successful())->toBeFalse();
});

it('uses package exceptions for malformed error structures', function (mixed $result) {
    expect(fn () => DpdApiException::fromResponse(new DpdResponse('error', $result, [])))->toThrow(DpdResponseException::class);
})->with([
    'null' => [null], 'boolean' => [false], 'empty list' => [[]],
    'unknown object' => [['private' => 'secret']],
    'malformed err_code' => [['err_code' => []]],
    'malformed list entry' => [[123]],
]);

it('keeps transport bodies available only through explicit diagnostics', function () {
    $body = 'private address, password and token';
    $http = DpdTransportException::fromHttpStatus(503, $body);
    $json = DpdTransportException::invalidJson($body);

    expect($http->getMessage())->toBe('DPD returned HTTP 503.')
        ->and($http->rawBody)->toBe($body)
        ->and($http->statusCode)->toBe(503)
        ->and($json->getMessage())->toBe('DPD WEB.Service returned an invalid JSON response.')
        ->and($json->rawBody)->toBe($body);
});
