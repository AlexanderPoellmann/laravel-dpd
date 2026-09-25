<?php

use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\LabelOption;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a DPD label with the documented request shape and maps the response', function () {
    Http::fake([
        '*' => Http::response([
            'status' => 'ok',
            'result' => [[
                'label' => 'https://ws-etikett.paketomat.at/secure/example.pdf',
                'paknr' => '06215000000647',
                'saved' => '1',
                'err_code' => null,
                'barcodecontent' => '%000109006215000000647327040',
                'kreferenz' => 'ORDER-42',
                'code2d' => 'https://ws-etikett.paketomat.at/secure/example.png',
            ]],
        ]),
    ]);

    $request = new LabelRequest(
        recipient: new Address(
            name: 'Musterfirma GmbH',
            street: 'Landesgerichtsstrasse',
            postalCode: '1010',
            city: 'Wien',
            countryCode: 'AT',
            customerNumber: '4711',
            houseNumber: '1',
            contactPerson: 'Maria Muster',
            phone: '+431234567',
            email: 'maria@example.com',
        ),
        parcel: new Parcel(
            type: ParcelType::B2c,
            weightInGrams: 3500,
            lengthInMillimeters: 1000,
            widthInMillimeters: 500,
            heightInMillimeters: 200,
            references: ['0815ab', 'LFNR214687'],
            invoiceNumber: '2026-42',
        ),
        products: Products::normalParcel()->withPredict('maria@example.com'),
        shippingDate: new DateTimeImmutable('2026-09-25'),
        option: LabelOption::DigitalLabel,
        customerReference: 'ORDER-42',
    );

    $result = app(LaravelDpd::class)->createLabel($request);

    expect($result->successful())->toBeTrue()
        ->and($result->labels)->toHaveCount(1)
        ->and($result->labels[0]->trackingNumber)->toBe('06215000000647')
        ->and($result->labels[0]->digitalCodeUrl)->toEndWith('example.png');

    Http::assertSent(function (Request $httpRequest): bool {
        $data = $httpRequest->data()['data'];

        return $data['vdat'] === '20260925'
            && $data['empfaenger']['name'] === 'Musterfirma GmbH'
            && $data['paket']['pakettyp'] === 'B2C'
            && $data['paket']['gewicht'] === '3500'
            && $data['paket']['volumen'] === '100005000200'
            && $data['paket']['liefernr'] === '0815ab~LFNR214687'
            && $data['produkt1'] === 'NP'
            && $data['produkt6'] === ['pred' => 'maria@example.com']
            && $data['optionen'] === '2d';
    });
});

it('retains per-label errors for partial multi-label responses', function () {
    Http::fake([
        '*' => Http::response([
            'status' => 'ok',
            'result' => [[
                'label' => null,
                'paknr' => null,
                'saved' => '0',
                'err_code' => '[0] ER12-Gewichtslimit überschritten',
            ]],
        ]),
    ]);

    $request = new LabelRequest(
        recipient: new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        parcel: new Parcel(ParcelType::Dpd, 3500),
        products: new Products(Product1::NormalParcel),
        shippingDate: new DateTimeImmutable('2026-09-25'),
    );

    $result = app(LaravelDpd::class)->createLabel($request);

    expect($result->successful())->toBeFalse()
        ->and($result->labels[0]->errorCode)->toContain('ER12');
});
