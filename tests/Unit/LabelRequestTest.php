<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;

function labelRequestForParcels(Parcel|array $parcels, array $options = []): LabelRequest
{
    return new LabelRequest(
        new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        $parcels,
        Products::normalParcel(),
        new DateTimeImmutable('2024-11-11'),
        ...$options,
    );
}

it('normalizes a single parcel and derives its count', function () {
    $parcel = new Parcel(weightInGrams: 3000);
    $request = labelRequestForParcels($parcel, ['references' => ['0815ab', 'LFNR214687'], 'invoiceNumber' => '202308abcd']);

    expect($request->parcels)->toBe([$parcel])
        ->and($request->parcelCount)->toBe(1)
        ->and($request->toArray()['pakanz'])->toBe('1')
        ->and($request->toArray()['paket'])->toBe([
            'liefernr' => '0815ab~LFNR214687',
            'rechnungsnr' => '202308abcd',
            'pakettyp' => 'DPD',
            'gewicht' => '3000',
            'volumen' => '',
        ]);
});

it('serializes each parcel weight in order and derives the count', function () {
    $parcels = [new Parcel(weightInGrams: 3000), new Parcel(weightInGrams: 13500), new Parcel(weightInGrams: 23500)];
    $request = labelRequestForParcels($parcels);

    expect($request->parcels)->toBe($parcels)
        ->and($request->parcelCount)->toBe(3)
        ->and($request->toArray()['pakanz'])->toBe('3')
        ->and($request->toArray()['paket']['gewicht'])->toBe('3000~13500~23500');
});

it('normalizes keyed parcel arrays without changing their order', function () {
    $request = labelRequestForParcels([4 => new Parcel(weightInGrams: 13500), 2 => new Parcel(weightInGrams: 3000)]);

    expect(array_is_list($request->parcels))->toBeTrue()
        ->and($request->toArray()['paket']['gewicht'])->toBe('13500~3000');
});

it('allows twenty parcels and enforces weight limits per parcel rather than on the total', function () {
    $request = labelRequestForParcels(array_fill(0, 20, new Parcel(weightInGrams: 31500)));

    expect($request->parcelCount)->toBe(20)
        ->and($request->toArray()['pakanz'])->toBe('20')
        ->and(explode('~', $request->toArray()['paket']['gewicht']))->toBe(array_fill(0, 20, '31500'));
});

it('rejects an empty or oversized parcel list', function (int $count) {
    expect(fn () => labelRequestForParcels(array_fill(0, $count, new Parcel)))
        ->toThrow(InvalidArgumentException::class, 'between 1 and 20');
})->with([0, 21]);

it('rejects non-parcel list entries', function (mixed $parcel) {
    expect(fn () => labelRequestForParcels([new Parcel, $parcel]))
        ->toThrow(InvalidArgumentException::class, 'only Parcel objects');
})->with(['null' => [null], 'weight' => [3000], 'array' => [[]], 'string' => ['3000~13500']]);

it('rejects weights outside the documented 10 to 31500 gram range', function (int $weight) {
    expect(fn () => new Parcel(weightInGrams: $weight))->toThrow(InvalidArgumentException::class);
})->with([-1, 0, 1, 9, 31501]);

it('accepts the documented weight boundaries', function (int $weight) {
    expect((new Parcel(weightInGrams: $weight))->weightInGrams)->toBe($weight);
})->with([10, 31500]);

it('enforces the parcel shop weight limit', function () {
    expect((new Parcel(ParcelType::ParcelShop, 20000))->weightInGrams)->toBe(20000)
        ->and(fn () => new Parcel(ParcelType::ParcelShop, 20001))->toThrow(InvalidArgumentException::class, '20,000');
});

it('validates the small parcel limit for every parcel', function () {
    expect(fn () => new LabelRequest(
        new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        [new Parcel(weightInGrams: 3000), new Parcel(weightInGrams: 3001)],
        Products::smallParcel(),
        new DateTimeImmutable('2024-11-11'),
    ))->toThrow(InvalidArgumentException::class, '3,000');
});

it('requires every parcel weight for Germany', function () {
    expect(fn () => new LabelRequest(
        new Address('Receiver', 'Street', '10115', 'Berlin', 'de'),
        [new Parcel(weightInGrams: 3000), new Parcel],
        Products::normalParcel(),
        new DateTimeImmutable('2024-11-11'),
    ))->toThrow(InvalidArgumentException::class, 'every parcel weight');
});

it('allows all weights to be omitted where DPD permits it', function () {
    $request = labelRequestForParcels([new Parcel, new Parcel, new Parcel]);

    expect($request->toArray()['paket']['gewicht'])->toBe('')
        ->and($request->toArray()['pakanz'])->toBe('3');
});

it('rejects partially specified weights without losing parcel positions', function (array $parcels) {
    expect(fn () => labelRequestForParcels($parcels))
        ->toThrow(InvalidArgumentException::class, 'every parcel or omitted for all');
})->with([
    'missing last' => [[new Parcel(weightInGrams: 3000), new Parcel]],
    'missing first' => [[new Parcel, new Parcel(weightInGrams: 3000)]],
]);

it('rejects parcel types that cannot be represented by one DPD request', function () {
    expect(fn () => labelRequestForParcels([new Parcel, new Parcel(ParcelType::B2c)]))
        ->toThrow(InvalidArgumentException::class, 'same parcel type');
});

it('preserves a shared volume and rejects differing volumes', function () {
    $parcel = new Parcel(lengthInMillimeters: 1000, widthInMillimeters: 500, heightInMillimeters: 200);

    expect(labelRequestForParcels([$parcel, $parcel])->toArray()['paket']['volumen'])->toBe('100005000200')
        ->and(fn () => labelRequestForParcels([$parcel, new Parcel]))->toThrow(InvalidArgumentException::class, 'one volume');
});

it('requires complete valid dimensions', function () {
    expect(fn () => new Parcel(lengthInMillimeters: 1000))->toThrow(InvalidArgumentException::class, 'supplied together')
        ->and(fn () => new Parcel(lengthInMillimeters: 10000, widthInMillimeters: 500, heightInMillimeters: 200))->toThrow(InvalidArgumentException::class, '9,999');
});

it('accepts the legacy count only when it matches the actual parcels', function () {
    expect(labelRequestForParcels(new Parcel, ['parcelCount' => 1])->parcelCount)->toBe(1)
        ->and(fn () => labelRequestForParcels(new Parcel, ['parcelCount' => 3]))->toThrow(InvalidArgumentException::class, 'one Parcel per label');
});

it('validates shipment references and invoice limits', function (array $options) {
    expect(fn () => labelRequestForParcels(new Parcel, $options))->toThrow(InvalidArgumentException::class);
})->with([
    'too many references' => [['references' => array_fill(0, 11, 'reference')]],
    'references too long' => [['references' => [str_repeat('a', 105), str_repeat('b', 105)]]],
    'delimiter' => [['references' => ['one~two']]],
    'non-string reference' => [['references' => [123]]],
    'empty reference' => [['references' => ['']]],
    'invoice too long' => [['invoiceNumber' => str_repeat('a', 51)]],
]);

it('accepts reference and invoice limits including reference separators', function () {
    $request = labelRequestForParcels(new Parcel, [
        'references' => [str_repeat('a', 104), str_repeat('b', 105)],
        'invoiceNumber' => str_repeat('c', 50),
    ]);

    expect(strlen($request->toArray()['paket']['liefernr']))->toBe(210)
        ->and(strlen($request->toArray()['paket']['rechnungsnr']))->toBe(50)
        ->and(labelRequestForParcels(new Parcel, ['references' => array_fill(0, 10, 'ref')])->references)->toHaveCount(10);
});

it('enforces the documented maximum future shipping date', function () {
    $makeRequest = fn (string $date) => new LabelRequest(
        new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        new Parcel,
        Products::normalParcel(),
        new DateTimeImmutable($date),
    );

    expect($makeRequest('today +25 days')->parcelCount)->toBe(1)
        ->and(fn () => $makeRequest('today +26 days'))->toThrow(InvalidArgumentException::class, '25 days');
});
