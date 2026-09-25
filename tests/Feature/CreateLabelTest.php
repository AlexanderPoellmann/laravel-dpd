<?php

use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\LabelOption;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
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
        parcels: new Parcel(
            type: ParcelType::B2c,
            weightInGrams: 3500,
            lengthInMillimeters: 1000,
            widthInMillimeters: 500,
            heightInMillimeters: 200,
        ),
        products: Products::normalParcel()->withPredict('maria@example.com'),
        shippingDate: new DateTimeImmutable('2026-09-25'),
        option: LabelOption::DigitalLabel,
        customerReference: 'ORDER-42',
        references: ['0815ab', 'LFNR214687'],
        invoiceNumber: '2026-42',
    );

    $result = app(LaravelDpd::class)->createLabel($request);

    expect($result->successful())->toBeTrue()
        ->and($result->labels)->toHaveCount(1)
        ->and($result->labels[0]->trackingNumber)->toBe('06215000000647')
        ->and($result->labels[0]->digitalCodeUrl)->toEndWith('example.png');

    $this->assertDpdPayload('getLabel', [
        'vdat' => '20260925',
        'pakanz' => '1',
        'empfaenger' => [
            'name' => 'Musterfirma GmbH',
            'anschrift' => 'Landesgerichtsstrasse',
            'kdnr' => '4711',
            'zusatz' => '',
            'zusatz2' => '',
            'hausnr' => '1',
            'tuernr' => '',
            'plz' => '1010',
            'ort' => 'Wien',
            'land' => 'AT',
            'latitude' => '',
            'longitude' => '',
            'bezugsp' => 'Maria Muster',
            'tel' => '+431234567',
            'mail' => 'maria@example.com',
        ],
        'paket' => [
            'liefernr' => '0815ab~LFNR214687',
            'rechnungsnr' => '2026-42',
            'pakettyp' => 'B2C',
            'gewicht' => '3500',
            'volumen' => '100005000200',
        ],
        'produkt1' => 'NP',
        'produkt2' => '',
        'produkt3' => '',
        'produkt4' => '',
        'produkt5' => '',
        'produkt6' => ['pred' => 'maria@example.com'],
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
        'kreferenz' => 'ORDER-42',
        'optionen' => '2d',
    ]);
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
        parcels: new Parcel(ParcelType::Dpd, 3500),
        products: new Products(Product1::NormalParcel),
        shippingDate: new DateTimeImmutable('2026-09-25'),
    );

    $result = app(LaravelDpd::class)->createLabel($request);

    expect($result->successful())->toBeFalse()
        ->and($result->labels[0]->errorCode)->toContain('ER12');
});
