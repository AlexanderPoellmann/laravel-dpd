<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Data\LabelResult;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Data\ServiceState;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\InvalidTrackingNumberException;

it('rejects malformed result lists instead of silently discarding entries', function (mixed $result) {
    $response = DpdResponse::fromArray(['status' => 'ok', 'result' => $result]);

    expect(fn () => $response->listResult())->toThrow(DpdResponseException::class);
})->with([
    'scalar' => ['invalid'],
    'missing' => [null],
    'object instead of list' => [['label' => 'https://example.com/label.pdf']],
    'mixed entries' => [[['label' => 'https://example.com/label.pdf'], 'invalid']],
]);

it('requires a nonempty label URL for sheets and reprints', function (mixed $url) {
    $response = DpdResponse::fromArray(['status' => 'ok', 'result' => ['label' => $url]]);

    expect(fn () => LabelSheetResult::fromResponse($response))->toThrow(DpdResponseException::class);
})->with([null, '', '   ', false, 123]);

it('preserves the original response when parsing a label sheet', function () {
    $payload = ['status' => 'ok', 'result' => ['label' => 'https://example.com/label.pdf']];
    $sheet = LabelSheetResult::fromResponse(DpdResponse::fromArray($payload));

    expect($sheet->url)->toBe($payload['result']['label'])
        ->and($sheet->raw)->toBe($payload);
});

it('does not report labels without a usable URL as successful', function (?string $url) {
    expect(Label::fromArray(['label' => $url])->successful())->toBeFalse();
})->with([null, '', '   ']);

it('keeps successful labels and failed siblings in a partial result', function () {
    $success = Label::fromArray(['label' => 'https://example.com/label.pdf', 'paknr' => '06215000000647']);
    $failure = Label::fromArray(['err_code' => 'ER12']);
    $result = new LabelResult([$success, $failure], []);

    expect($result->successful())->toBeFalse()
        ->and($result->labels)->toBe([$success, $failure])
        ->and($success->successful())->toBeTrue()
        ->and((new LabelResult([], []))->successful())->toBeFalse();
});

it('throws a package exception for malformed response envelopes', function (array $payload) {
    expect(fn () => DpdResponse::fromArray($payload))->toThrow(DpdResponseException::class);
})->with([
    'missing status' => [[]],
    'array status' => [['status' => []]],
    'numeric status' => [['status' => 1]],
    'empty status' => [['status' => '']],
]);

it('throws a package exception for unexpected associative results', function (mixed $result) {
    $response = DpdResponse::fromArray(['status' => 'ok', 'result' => $result]);

    expect(fn () => $response->associativeResult())->toThrow(DpdException::class);
})->with(['null' => [null], 'string' => ['OK'], 'list' => [[['label' => 'url']]]]);

it('throws a package exception for unexpected scalar results', function () {
    $response = DpdResponse::fromArray(['status' => 'ok', 'result' => ['unexpected']]);

    expect(fn () => $response->stringResult())->toThrow(DpdResponseException::class);
});

it('validates tracking numbers parsed from label responses', function (string $number) {
    expect(fn () => Label::fromArray(['paknr' => $number]))->toThrow(InvalidTrackingNumberException::class);
})->with(['123', '0621500000025A', "06215000000259\n"]);

it('rejects malformed label fields with package exceptions', function (string $field) {
    expect(fn () => Label::fromArray([$field => []]))->toThrow(DpdResponseException::class);
})->with(['label', 'paknr', 'err_code', 'barcodecontent', 'kreferenz', 'code2d']);

it('rejects malformed service fields with package exceptions', function (string $field) {
    expect(fn () => ServiceState::fromArray([$field => []]))->toThrow(DpdResponseException::class);
})->with(['serviceid', 'name', 'status']);
