<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Predict;
use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\CollectionRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Data\TrackingNumber;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\LabelSheetFormat;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// WEB_Service_EN.pdf section 18; Request und Response.pdf section 3.22 (array, not CSV).
it('sends getLabelA4x4 trackingnumberList as a JSON array of strings', function (array $numbers, array $expected) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => ['label' => 'https://example.com/sheet.pdf']])]);

    app(LaravelDpd::class)->createLabelSheet(new LabelSheetRequest($numbers));

    $this->assertDpdPayload('getLabelA4x4', [
        'offset' => '0',
        'format' => 'A4',
        'trackingnumberList' => $expected,
    ]);

    Http::assertSent(function (Request $request) use ($expected): bool {
        $body = json_decode($request->body(), flags: JSON_THROW_ON_ERROR);

        return is_array($body->data->trackingnumberList) && $body->data->trackingnumberList === $expected;
    });
})->with([
    'single' => [['06215000000793'], ['06215000000793']],
    'documented example' => [
        ['06215000000793', '06215000000794', '06215000000088', '06215000000089'],
        ['06215000000793', '06215000000794', '06215000000088', '06215000000089'],
    ],
    'sparse keys' => [[2 => '06215000000793', 5 => '06215000000794'], ['06215000000793', '06215000000794']],
    'named keys' => [['first' => '06215000000793'], ['06215000000793']],
    'value objects and strings' => [[new TrackingNumber('06215000000793'), '06215000000794'], ['06215000000793', '06215000000794']],
]);

it('sends label sheet formats and offsets exactly', function (LabelSheetFormat $format, string $expected) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => ['label' => 'https://example.com/sheet.pdf']])]);

    app(LaravelDpd::class)->createLabelSheet(new LabelSheetRequest(['06215000000793'], $format, 1.5));

    $this->assertDpdPayload('getLabelA4x4', ['offset' => '1.5', 'format' => $expected, 'trackingnumberList' => ['06215000000793']]);
})->with([[LabelSheetFormat::A6, 'A6'], [LabelSheetFormat::Zpl, 'zpl']]);

it('sends the exact cancellation payload', function (string|TrackingNumber $number) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => ['paknr' => '06215000000258', 'storno' => true]])]);

    $result = app(LaravelDpd::class)->cancelLabel($number);

    expect($result->trackingNumber)->toBe('06215000000258')->and($result->cancelled)->toBeTrue();
    $this->assertDpdPayload('cancelByTracknr', ['paknr' => '06215000000258']);
})->with(['string' => ['06215000000258'], 'value object' => [new TrackingNumber('06215000000258')]]);

it('sends the exact reprint payload', function (string|TrackingNumber $number, LabelFormat $format, string $expected) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => ['label' => 'https://example.com/copy.pdf']])]);

    app(LaravelDpd::class)->reprintLabel($number, $format);

    $this->assertDpdPayload('getReprintByTracknr', ['paknr' => '06215000000260', 'format' => $expected]);
})->with([
    ['06215000000260', LabelFormat::Pdf, 'PDF'],
    [new TrackingNumber('06215000000260'), LabelFormat::Zpl, 'ZPL'],
    ['06215000000260', LabelFormat::Epl, 'EPL'],
]);

it('sends the exact self booking payload', function (SelfBookingMode $mode, string $expected) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => ['dpd' => 'https://example.com/list.pdf', 'pt' => '']])]);

    app(LaravelDpd::class)->selfBookingList(new DateTimeImmutable('2026-09-24'), new DateTimeImmutable('2026-09-25'), $mode);

    $this->assertDpdPayload('getSblList', ['von' => '20260924', 'bis' => '20260925', 'modus' => $expected]);
})->with([[SelfBookingMode::SelfBookingList, '1'], [SelfBookingMode::DailyClosing, '2']]);

it('sends the exact pickup payload', function (?int $count, ?string $comment, array $expected) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => 'OK'])]);

    app(LaravelDpd::class)->createPickupOrder(new PickupOrderRequest(new DateTimeImmutable('2026-09-26'), $count, $comment));

    $this->assertDpdPayload('abholauftrag', $expected);
})->with([
    [2, 'Please ring', ['datum' => '20260926', 'anzahl' => '2', 'bemerkung' => 'Please ring']],
    [null, null, ['datum' => '20260926', 'anzahl' => '', 'bemerkung' => '']],
]);

it('sends the exact collection payload with distinct pickup and recipient addresses', function () {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => 'OK'])]);

    app(LaravelDpd::class)->createCollectionRequest(new CollectionRequest(
        date: new DateTimeImmutable('2026-09-26'),
        pickupAddress: new Address('Pickup', 'First Street', '1010', 'Wien', 'at', additional: 'Warehouse', houseNumber: '1', contactPerson: 'Anna', phone: '+431111', email: 'pickup@example.com'),
        recipientAddress: new Address('Recipient', 'Second Street', '4020', 'Linz', 'AT', houseNumber: '2', contactPerson: 'Ben'),
        parcelType: ParcelType::Dpd,
        product1: Product1::NormalParcel,
        reference: 'ORDER-1~ORDER-2',
        parcelCount: 2,
        comment: 'Please ring',
    ));

    $this->assertDpdPayload('rueckholauftrag_v2', [
        'datum' => '20260926',
        'referenz' => 'ORDER-1~ORDER-2',
        'paktyp' => 'DPD',
        'produkt1' => 'NP',
        'produkt2' => '',
        'anzahl' => '2',
        'bemerkung' => 'Please ring',
        'aname' => 'Pickup',
        'azusatz' => 'Warehouse',
        'akontakt' => 'Anna',
        'aland' => 'AT',
        'aplz' => '1010',
        'aort' => 'Wien',
        'astrasse' => 'First Street 1',
        'amail' => 'pickup@example.com',
        'atel' => '+431111',
        'ename' => 'Recipient',
        'ezusatz' => '',
        'ekontakt' => 'Ben',
        'eland' => 'AT',
        'eplz' => '4020',
        'eort' => 'Linz',
        'estrasse' => 'Second Street 2',
    ]);
});

it('sends the exact order import payload', function () {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => 'successful'])]);

    app(LaravelDpd::class)->importOrder(new OrderImportRequest(
        orderNumber: 'ORDER-42',
        recipient: new Address('Receiver', 'Street', '1010', 'Wien', 'at', customerNumber: '4711', additional: 'Floor 1', additional2: 'Office', houseNumber: '3', doorNumber: '6a', contactPerson: 'Maria', phone: '+431234', email: 'receiver@example.com'),
        parcelType: ParcelType::Dpd,
        product1: Product1::NormalParcel,
        shippingDate: new DateTimeImmutable('2026-09-25'),
        invoiceNumber: 'INV-42',
        weightInGrams: 3500,
        product6: new Predict('receiver@example.com'),
    ));

    $this->assertDpdPayload('importAube', [
        'auftragsnr' => 'ORDER-42',
        'rechnungsnr' => 'INV-42',
        'kundennr' => '4711',
        'name' => 'Receiver',
        'bezugsperson' => 'Maria',
        'strasse' => 'Street',
        'hausnr' => '3',
        'tuernr' => '6a',
        'zusatz' => 'Floor 1',
        'zusatz2' => 'Office',
        'plz' => '1010',
        'ort' => 'Wien',
        'land' => 'AT',
        'tel' => '+431234',
        'mail' => 'receiver@example.com',
        'vdat' => '20260925',
        'gewicht' => '3500',
        'pakanz' => '1',
        'pakettyp' => 'DPD',
        'produkt1' => 'NP',
        'produkt2' => '',
        'produkt3' => '',
        'produkt4' => '',
        'produkt5' => '',
        'produkt6' => ['pred' => 'receiver@example.com'],
        'produkt7' => '',
        'barcode' => '',
        'optionen' => '',
    ]);
});

it('sends the exact status payload without a client number', function () {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => []])]);

    app(LaravelDpd::class)->status();

    $this->assertDpdPayload('getStatus', [], withClient: false);
});
