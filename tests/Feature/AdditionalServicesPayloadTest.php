<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Aviso;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\CashOnDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\ConstructionSiteDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\DepartmentDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\FreightCollect;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\HigherInsurance;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\IdentityCheck;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\LimitedQuantity;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Predict;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\ValuableParcel;
use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\IdentityCheckType;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Support\Facades\Http;

// WEB_Service_EN.pdf sections 15.5–15.13 and Request und Response.pdf sections 3.3, 3.5, 3.14–3.16.
it('sends exact additional service label payloads', function (Products $products, ParcelType $type, string $wireType, string $product1, array $additional) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => [
        ['label' => 'https://example.com/label.pdf', 'paknr' => '06215000000217'],
    ]])]);

    app(LaravelDpd::class)->createLabel(new LabelRequest(
        recipient: new Address('Name / Firma', 'Anschrift', '1090', 'Wien', 'AT', phone: '05787777', email: 'email@domain.com'),
        parcels: new Parcel($type, 3500),
        products: $products,
        shippingDate: new DateTimeImmutable('2024-11-12'),
        references: ['0815ab', 'LFNR214687'],
        invoiceNumber: '202308abcd',
    ));

    $this->assertDpdPayload('getLabel', [
        'vdat' => '20241112',
        'pakanz' => '1',
        'empfaenger' => [
            'name' => 'Name / Firma',
            'anschrift' => 'Anschrift',
            'kdnr' => '',
            'zusatz' => '',
            'zusatz2' => '',
            'hausnr' => '',
            'tuernr' => '',
            'plz' => '1090',
            'ort' => 'Wien',
            'land' => 'AT',
            'latitude' => '',
            'longitude' => '',
            'bezugsp' => '',
            'tel' => '05787777',
            'mail' => 'email@domain.com',
        ],
        'paket' => [
            'liefernr' => '0815ab~LFNR214687',
            'rechnungsnr' => '202308abcd',
            'pakettyp' => $wireType,
            'gewicht' => '3500',
            'volumen' => '',
        ],
        ...array_replace([
            'produkt1' => $product1,
            'produkt2' => '',
            'produkt3' => '',
            'produkt4' => '',
            'produkt5' => '',
            'produkt6' => '',
            'produkt7' => '',
        ], $additional),
        'absender' => [
            'name' => '', 'adresse' => '', 'adresse2' => '', 'plz' => '', 'ort' => '',
            'land' => '', 'tel_name' => '', 'tel' => '', 'mail_name' => '', 'mail' => '',
        ],
        'dfu' => '0',
        'format' => 'PDF',
        'kreferenz' => '',
        'optionen' => '',
    ]);
})->with([
    'higher insurance and Predict' => [
        Products::normalParcel()->with(new HigherInsurance(1500000))->with(new Predict('email@domain.com')),
        ParcelType::Dpd, 'DPD', 'NP', ['produkt2' => ['hv' => '1500000'], 'produkt6' => ['pred' => 'email@domain.com']],
    ],
    'B2C Predict' => [Products::normalParcel()->with(new Predict('email@domain.com')), ParcelType::B2c, 'B2C', 'NP', ['produkt6' => ['pred' => 'email@domain.com']]],
    'primetime COD example' => [(new Products(Product1::Primetime17))->with(new CashOnDelivery(36000, '81acd')), ParcelType::Primetime, 'PT', 'PM2', ['produkt3' => ['nnbar' => '36000', 'refnnbar' => '81acd']]],
    'primetime COD without reference' => [(new Products(Product1::Primetime17))->with(new CashOnDelivery(1)), ParcelType::Primetime, 'PT', 'PM2', ['produkt3' => ['nnbar' => '1']]],
    'department example' => [(new Products(Product1::Primetime10))->with(new DepartmentDelivery('Verkauf')), ParcelType::Primetime, 'PT', 'AM1', ['produkt2' => ['abt' => 'Verkauf']]],
    'valuable parcel example' => [(new Products(Product1::Primetime17))->with(new ValuableParcel(1500000)), ParcelType::Primetime, 'PT', 'PM2', ['produkt4' => ['wp' => '1500000']]],
    'identity check' => [(new Products(Product1::Primetime12))->with(new IdentityCheck('Maria Muster')), ParcelType::Primetime, 'PT', 'AM2', ['produkt2' => ['id' => 'Maria Muster']]],
    'identity from 16' => [(new Products(Product1::Primetime12))->with(new IdentityCheck('Maria Muster', IdentityCheckType::Age16)), ParcelType::Primetime, 'PT', 'AM2', ['produkt2' => ['id16' => 'Maria Muster']]],
    'identity from 18' => [(new Products(Product1::Primetime12))->with(new IdentityCheck('Maria Muster', IdentityCheckType::Age18)), ParcelType::Primetime, 'PT', 'AM2', ['produkt2' => ['id18' => 'Maria Muster']]],
    'AVISO email' => [(new Products(Product1::Primetime12))->with(new Aviso('email@domain.com')), ParcelType::Primetime, 'PT', 'AM2', ['produkt2' => ['aviso' => 'email@domain.com']]],
    'AVISO mobile' => [(new Products(Product1::Primetime12))->with(new Aviso('+4369912345678')), ParcelType::Primetime, 'PT', 'AM2', ['produkt2' => ['aviso' => '+4369912345678']]],
    'freight collect' => [Products::normalParcel()->with(new FreightCollect), ParcelType::Dpd, 'DPD', 'NP', ['produkt5' => 'UNFREI']],
    'construction site' => [(new Products(Product1::Primetime17))->with(new ConstructionSiteDelivery), ParcelType::Primetime, 'PT', 'PM2', ['produkt6' => 'bau']],
    'limited quantity' => [Products::normalParcel()->with(new LimitedQuantity(1200)), ParcelType::Dpd, 'DPD', 'NP', ['produkt7' => ['LQ' => '1200']]],
    'primetime composition' => [(new Products(Product1::Primetime17))->with(new IdentityCheck('Maria Muster'))->with(new CashOnDelivery(36000, '81acd'))->with(new ValuableParcel(52001)), ParcelType::Primetime, 'PT', 'PM2', ['produkt2' => ['id' => 'Maria Muster'], 'produkt3' => ['nnbar' => '36000', 'refnnbar' => '81acd'], 'produkt4' => ['wp' => '52001']]],
    'raw future payload' => [Products::normalParcel()->withRaw(ProductSlot::Product7, ['future' => ['enabled' => true]]), ParcelType::Dpd, 'DPD', 'NP', ['produkt7' => ['future' => ['enabled' => true]]]],
]);

it('rejects incompatible typed services before an HTTP request', function () {
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->createLabel(new LabelRequest(
        new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        new Parcel(ParcelType::Dpd, 3500),
        (new Products(Product1::Primetime17))->with(new CashOnDelivery(1)),
        new DateTimeImmutable('2024-11-12'),
    )))->toThrow(InvalidArgumentException::class, 'primetime parcels');

    Http::assertNothingSent();
});

it('validates typed order import services before sending', function () {
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->importOrder(new OrderImportRequest(
        orderNumber: 'ORDER-1',
        recipient: new Address('Receiver', 'Street', '1010', 'Wien', 'AT'),
        parcelType: ParcelType::Dpd,
        product1: Product1::NormalParcel,
        product3: new CashOnDelivery(1),
    )))->toThrow(InvalidArgumentException::class, 'primetime product');

    Http::assertNothingSent();
});
