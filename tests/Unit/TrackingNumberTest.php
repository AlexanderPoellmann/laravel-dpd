<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\TrackingNumber;
use AlexanderPoellmann\LaravelDpd\Exceptions\InvalidTrackingNumberException;

it('preserves all fourteen digits when representing a tracking number', function () {
    $number = new TrackingNumber('00015000000259');

    expect($number->value)->toBe('00015000000259')
        ->and((string) $number)->toBe('00015000000259')
        ->and(json_encode($number, JSON_THROW_ON_ERROR))->toBe('"00015000000259"');
});

it('rejects tracking numbers that are not exactly fourteen ASCII digits', function (string $number) {
    expect(fn () => new TrackingNumber($number))->toThrow(InvalidTrackingNumberException::class);
})->with([
    '', '6215000000259', '006215000000259', '0621500000025A',
    ' 06215000000259', '06215000000259 ', "06215000000259\n",
    '+6215000000259', '6.215000000259', '６２１５０００００００２５９',
    '06215000000259,06215000000260',
]);

it('keeps label sheet numbers as a normalized list of strings', function () {
    $request = new LabelSheetRequest([3 => new TrackingNumber('06215000000259'), 8 => '06215000000260']);

    expect($request->trackingNumbers)->toBe(['06215000000259', '06215000000260'])
        ->and($request->toArray())->toBe([
            'offset' => '0',
            'format' => 'A4',
            'trackingnumberList' => ['06215000000259', '06215000000260'],
        ]);
});

it('rejects empty label sheets', function () {
    expect(fn () => new LabelSheetRequest([]))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid label sheet entries without coercing them', function (mixed $number) {
    expect(fn () => new LabelSheetRequest(['06215000000259', $number]))
        ->toThrow(InvalidTrackingNumberException::class);
})->with(['short' => ['123'], 'integer' => [12345678901234], 'float' => [12345678901234.0], 'null' => [null], 'boolean' => [true], 'array' => [[]]]);
