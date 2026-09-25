<?php

use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;

it('encodes parcel volume in the DPD twelve digit format', function () {
    $parcel = new Parcel(
        type: ParcelType::Dpd,
        weightInGrams: 3500,
        lengthInMillimeters: 1000,
        widthInMillimeters: 500,
        heightInMillimeters: 200,
    );

    expect($parcel->volume())->toBe('100005000200');
});

it('builds documented product structures', function () {
    $products = (new Products(Product1::NormalParcel))
        ->withHigherInsurance(1500000)
        ->withPredict('recipient@example.com');

    expect($products->toArray()['produkt2'])->toBe(['hv' => '1500000'])
        ->and($products->toArray()['produkt6'])->toBe(['pred' => 'recipient@example.com']);
});

it('enforces DPD conditional recipient requirements before sending', function () {
    $recipient = new Address(
        name: 'Receiver',
        street: 'Street',
        postalCode: '1010',
        city: 'Vienna',
        countryCode: 'AT',
    );

    expect(fn () => new LabelRequest(
        recipient: $recipient,
        parcel: new Parcel(ParcelType::ParcelShop, 1000),
        products: new Products(Product1::NormalParcel),
        shippingDate: new DateTimeImmutable('2026-09-25'),
    ))->toThrow(InvalidArgumentException::class, 'parcel shop');
});
