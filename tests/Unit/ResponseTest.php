<?php

use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Data\LabelResult;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;

it('rejects malformed result lists instead of silently discarding entries', function (mixed $result) {
    $response = DpdResponse::fromArray(['status' => 'ok', 'result' => $result]);

    expect(fn () => $response->listResult())->toThrow(UnexpectedValueException::class);
})->with([
    'scalar' => ['invalid'],
    'missing' => [null],
    'object instead of list' => [['label' => 'https://example.com/label.pdf']],
    'mixed entries' => [[['label' => 'https://example.com/label.pdf'], 'invalid']],
]);

it('requires a nonempty label URL for sheets and reprints', function (mixed $url) {
    $response = DpdResponse::fromArray(['status' => 'ok', 'result' => ['label' => $url]]);

    expect(fn () => LabelSheetResult::fromResponse($response))->toThrow(UnexpectedValueException::class);
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
