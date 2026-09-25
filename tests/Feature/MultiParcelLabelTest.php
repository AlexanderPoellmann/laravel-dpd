<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Support\Facades\Http;

// Request und Response.pdf: section 2.4, page 11 (weights), section 3.4, page 60 (REST envelope).
it('sends the documented label payload for single and multiple parcels', function (Parcel|array $parcels, string $count, string $weights, array $labels) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => $labels])]);

    $request = new LabelRequest(
        recipient: new Address(
            name: 'Name / Firma',
            street: 'Anschrift',
            postalCode: '1090',
            city: 'Wien',
            countryCode: 'AT',
            customerNumber: '4711',
            additional: 'Zusatz 1',
            additional2: 'Zusatz 2',
            houseNumber: '53',
            doorNumber: '6a',
            contactPerson: 'Bezugsperson',
            phone: '05787777',
            email: 'email@domain.com',
        ),
        parcels: $parcels,
        products: Products::normalParcel(),
        shippingDate: new DateTimeImmutable('2024-11-12'),
        references: ['0815ab', 'LFNR214687'],
        invoiceNumber: '202308abcd',
    );

    $result = app(LaravelDpd::class)->createLabel($request);

    expect($result->successful())->toBeTrue()
        ->and($result->labels)->toHaveCount((int) $count)
        ->and(array_map(fn ($label) => $label->trackingNumber, $result->labels))->toBe(array_column($labels, 'paknr'));

    $this->assertDpdPayload('getLabel', [
        'vdat' => '20241112',
        'pakanz' => $count,
        'empfaenger' => [
            'name' => 'Name / Firma',
            'anschrift' => 'Anschrift',
            'kdnr' => '4711',
            'zusatz' => 'Zusatz 1',
            'zusatz2' => 'Zusatz 2',
            'hausnr' => '53',
            'tuernr' => '6a',
            'plz' => '1090',
            'ort' => 'Wien',
            'land' => 'AT',
            'latitude' => '',
            'longitude' => '',
            'bezugsp' => 'Bezugsperson',
            'tel' => '05787777',
            'mail' => 'email@domain.com',
        ],
        'paket' => [
            'liefernr' => '0815ab~LFNR214687',
            'rechnungsnr' => '202308abcd',
            'pakettyp' => 'DPD',
            'gewicht' => $weights,
            'volumen' => '',
        ],
        'produkt1' => 'NP',
        'produkt2' => '',
        'produkt3' => '',
        'produkt4' => '',
        'produkt5' => '',
        'produkt6' => '',
        'produkt7' => '',
        'absender' => [
            'name' => '',
            'adresse' => '',
            'adresse2' => '',
            'plz' => '',
            'ort' => '',
            'land' => '',
            'tel_name' => '',
            'tel' => '',
            'mail_name' => '',
            'mail' => '',
        ],
        'dfu' => '0',
        'format' => 'PDF',
        'kreferenz' => '',
        'optionen' => '',
    ]);
})->with([
    'one parcel' => [new Parcel(weightInGrams: 3000), '1', '3000', [
        ['label' => 'https://example.com/one.pdf', 'paknr' => '06215000000188', 'saved' => '1', 'err_code' => null],
    ]],
    'three different weights' => [
        [new Parcel(weightInGrams: 3000), new Parcel(weightInGrams: 13500), new Parcel(weightInGrams: 23500)],
        '3',
        '3000~13500~23500',
        [
            ['label' => 'https://example.com/one.pdf', 'paknr' => '06215000000188', 'saved' => '1', 'err_code' => null],
            ['label' => 'https://example.com/two.pdf', 'paknr' => '06215000000189', 'saved' => '1', 'err_code' => null],
            ['label' => 'https://example.com/three.pdf', 'paknr' => '06215000000190', 'saved' => '1', 'err_code' => null],
        ],
    ],
    'REST example with omitted weights' => [
        [new Parcel, new Parcel, new Parcel],
        '3',
        '',
        [
            ['label' => 'https://example.com/one.pdf', 'paknr' => '06215000000188', 'saved' => '1', 'err_code' => null],
            ['label' => 'https://example.com/two.pdf', 'paknr' => '06215000000189', 'saved' => '1', 'err_code' => null],
            ['label' => 'https://example.com/three.pdf', 'paknr' => '06215000000190', 'saved' => '1', 'err_code' => null],
        ],
    ],
]);

it('rejects invalid multi-parcel requests before sending them', function (Closure $makeParcels) {
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->createLabel(new LabelRequest(
        new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        $makeParcels(),
        Products::normalParcel(),
        new DateTimeImmutable('2024-11-12'),
    )))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'empty list' => [fn () => []],
    'too many parcels' => [fn () => array_fill(0, 21, new Parcel)],
    'overweight sibling' => [fn () => [new Parcel(weightInGrams: 3000), new Parcel(weightInGrams: 31501)]],
    'missing sibling weight' => [fn () => [new Parcel(weightInGrams: 3000), new Parcel]],
]);
