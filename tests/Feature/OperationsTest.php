<?php

use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\CollectionRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Support\Facades\Http;

it('supports label sheets cancellation reprints and self booking lists', function () {
    Http::fakeSequence()
        ->push(['status' => 'ok', 'result' => ['label' => 'https://example.com/sheet.pdf']])
        ->push(['status' => 'ok', 'result' => ['paknr' => '06215000000258', 'storno' => true]])
        ->push(['status' => 'ok', 'result' => ['label' => 'https://example.com/copy.pdf']])
        ->push(['status' => 'ok', 'result' => ['pt' => 'https://example.com/pt.pdf', 'dpd' => 'https://example.com/dpd.pdf']]);

    $dpd = app(LaravelDpd::class);

    expect($dpd->createLabelSheet(new LabelSheetRequest(['06215000000259']))->url)->toEndWith('sheet.pdf')
        ->and($dpd->cancelLabel('06215000000258')->cancelled)->toBeTrue()
        ->and($dpd->reprintLabel('06215000000260')->url)->toEndWith('copy.pdf')
        ->and($dpd->selfBookingList(
            new DateTimeImmutable('2026-09-25'),
            new DateTimeImmutable('2026-09-25'),
            SelfBookingMode::SelfBookingList,
        )->dpdUrl)->toEndWith('dpd.pdf');
});

it('supports pickup collection order import and service status', function () {
    Http::fakeSequence()
        ->push(['status' => 'ok', 'result' => 'OK'])
        ->push(['status' => 'ok', 'result' => 'OK'])
        ->push(['status' => 'ok', 'result' => 'successful'])
        ->push(['status' => 'ok', 'result' => [[
            'serviceid' => 19,
            'name' => 'WEB.Service',
            'status' => 'Im Betrieb',
        ]]])
        ->whenEmpty(Http::response(['status' => 'error', 'result' => 'unexpected']));

    $address = new Address(
        name: 'Example GmbH',
        street: 'Musterstrasse',
        postalCode: '1010',
        city: 'Wien',
        countryCode: 'AT',
        houseNumber: '1',
        email: 'ship@example.com',
        phone: '+431234567',
    );
    $dpd = app(LaravelDpd::class);

    $pickup = $dpd->createPickupOrder(new PickupOrderRequest(new DateTimeImmutable('2026-09-26'), 2));
    $collection = $dpd->createCollectionRequest(new CollectionRequest(
        date: new DateTimeImmutable('2026-09-26'),
        pickupAddress: $address,
        recipientAddress: $address,
        parcelType: ParcelType::Dpd,
        product1: Product1::NormalParcel,
    ));
    $order = $dpd->importOrder(new OrderImportRequest(
        orderNumber: 'ORDER-1',
        recipient: $address,
        parcelType: ParcelType::Dpd,
        product1: Product1::NormalParcel,
    ));
    $states = $dpd->status();

    expect($pickup->successful())->toBeTrue()
        ->and($collection->successful())->toBeTrue()
        ->and($order->successful())->toBeTrue()
        ->and($states)->toHaveCount(1)
        ->and($states[0]->name)->toBe('WEB.Service')
        ->and($states[0]->knownStatus?->value)->toBe('Im Betrieb');
});
