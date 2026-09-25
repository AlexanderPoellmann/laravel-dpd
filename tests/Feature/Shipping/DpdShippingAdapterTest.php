<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdShipmentCreationException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use AlexanderPoellmann\LaravelDpd\Exceptions\InvalidTrackingNumberException;
use AlexanderPoellmann\LaravelDpd\Shipping\DpdShippingAdapter;
use AlexanderPoellmann\Shipping\Contracts\CancelsShipments;
use AlexanderPoellmann\Shipping\Contracts\Carrier;
use AlexanderPoellmann\Shipping\Contracts\CreatesShipments;
use AlexanderPoellmann\Shipping\Contracts\DownloadsLabels;
use AlexanderPoellmann\Shipping\Data\Address;
use AlexanderPoellmann\Shipping\Data\Label;
use AlexanderPoellmann\Shipping\Data\Parcel;
use AlexanderPoellmann\Shipping\Data\Shipment;
use AlexanderPoellmann\Shipping\Data\ShipmentResult;
use AlexanderPoellmann\Shipping\Data\TrackingNumber;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->shipment = new Shipment(
        sender: new Address('Sender', 'Street', '1010', 'Wien', 'AT'),
        recipient: new Address('Recipient', 'Street', '4020', 'Linz', 'AT'),
        parcels: [new Parcel(1200)],
    );
});

it('implements only the shared capabilities DPD actually exposes', function (): void {
    $adapter = app(DpdShippingAdapter::class);

    expect($adapter)->toBeInstanceOf(Carrier::class)
        ->toBeInstanceOf(CreatesShipments::class)
        ->toBeInstanceOf(DownloadsLabels::class)
        ->toBeInstanceOf(CancelsShipments::class)
        ->and($adapter->carrier())->toBe('dpd');
});

it('translates a neutral shipment into DPD DTOs without leaking DPD products into the shared DTO', function (): void {
    Http::fake(['*' => Http::response([
        'status' => 'ok',
        'result' => [[
            'label' => 'https://ws-etikett.paketomat.at/secure/example.pdf',
            'paknr' => '06215000000647',
            'saved' => '1',
            'err_code' => null,
        ]],
    ])]);

    $shipment = new Shipment(
        sender: new Address('Sender GmbH', 'Senderstrasse', '1010', 'Wien', 'at', name2: 'Shipping Department', houseNumber: '1', additional: 'Building A', contactPerson: 'Sender Contact', phone: '+4398765', email: 'sender@example.com'),
        recipient: new Address('Receiver GmbH', 'Receiverstrasse', '4020', 'Linz', 'at', name2: 'Receiving Department', houseNumber: '2', additional: 'Floor 3', contactPerson: 'Receiver Contact', phone: '+4312345', email: 'receiver@example.com'),
        parcels: [new Parcel(1200, 300, 200, 100)],
        reference: 'ORDER-42',
        shippingDate: new DateTimeImmutable('2026-09-25'),
    );

    $result = app(DpdShippingAdapter::class)
        ->forProducts(new Products(Product1::NormalParcel))
        ->createShipment($shipment);

    expect($result->trackingNumbers)->toHaveCount(1)
        ->and((string) $result->trackingNumbers[0])->toBe('06215000000647')
        ->and($result->labels[0]->url)->toEndWith('example.pdf');

    $this->assertDpdPayload('getLabel', [
        'vdat' => '20260925',
        'pakanz' => '1',
        'empfaenger' => [
            'name' => 'Receiver GmbH',
            'anschrift' => 'Receiverstrasse',
            'kdnr' => '',
            'zusatz' => 'Receiving Department',
            'zusatz2' => 'Floor 3',
            'hausnr' => '2',
            'tuernr' => '',
            'plz' => '4020',
            'ort' => 'Linz',
            'land' => 'AT',
            'latitude' => '',
            'longitude' => '',
            'bezugsp' => 'Receiver Contact',
            'tel' => '+4312345',
            'mail' => 'receiver@example.com',
        ],
        'paket' => [
            'liefernr' => '',
            'rechnungsnr' => '',
            'pakettyp' => 'DPD',
            'gewicht' => '1200',
            'volumen' => '030002000100',
        ],
        'produkt1' => 'NP',
        'produkt2' => '',
        'produkt3' => '',
        'produkt4' => '',
        'produkt5' => '',
        'produkt6' => '',
        'produkt7' => '',
        'absender' => [
            'name' => 'Sender GmbH',
            'adresse' => 'Senderstrasse 1',
            'adresse2' => 'Shipping Department, Building A',
            'plz' => '1010',
            'ort' => 'Wien',
            'land' => 'AT',
            'tel_name' => 'Sender Contact',
            'tel' => '+4398765',
            'mail_name' => 'Sender Contact',
            'mail' => 'sender@example.com',
        ],
        'dfu' => '0',
        'format' => 'PDF',
        'kreferenz' => 'ORDER-42',
        'optionen' => '',
    ]);
});

it('maps cancellation through the shared tracking number', function (bool $cancelled): void {
    Http::fake(['*' => Http::response([
        'status' => 'ok',
        'result' => ['paknr' => '06215000000647', 'storno' => $cancelled],
    ])]);

    $result = app(DpdShippingAdapter::class)->cancelShipment(new TrackingNumber('06215000000647'));

    expect($result->cancelled)->toBe($cancelled)
        ->and((string) $result->trackingNumber)->toBe('06215000000647');

    $this->assertDpdPayload('cancelByTracknr', ['paknr' => '06215000000647']);
})->with([true, false]);

it('registers the DPD adapter as a tagged concrete service without a global capability binding', function (): void {
    $tagged = iterator_to_array(app()->tagged('shipping.adapters'));

    expect($tagged)->toContain(app(DpdShippingAdapter::class))
        ->and(app(DpdShippingAdapter::class))->toBe(app(DpdShippingAdapter::class))
        ->and(app()->bound(Carrier::class))->toBeFalse()
        ->and(app()->bound(DownloadsLabels::class))->toBeFalse()
        ->and(app()->bound(CancelsShipments::class))->toBeFalse()
        ->and(app()->bound(CreatesShipments::class))->toBeFalse();
});

it('downloads a remote DPD label into the neutral label contents', function (): void {
    Http::fake([
        'https://ws-etikett.paketomat.at/secure/example.pdf' => Http::response('%PDF-test', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $label = new Label(
        trackingNumber: new TrackingNumber('06215000000647'),
        url: 'https://ws-etikett.paketomat.at/secure/example.pdf',
        format: 'pdf',
    );

    $downloaded = app(DpdShippingAdapter::class)->downloadLabel($label);

    expect($downloaded->contents)->toBe('%PDF-test')
        ->and($downloaded->mimeType)->toBe('application/pdf')
        ->and($downloaded->format)->toBe('pdf')
        ->and($downloaded->url)->toBe($label->url)
        ->and($downloaded)->not->toBe($label)
        ->and($label->contents)->toBeNull()
        ->and((string) $downloaded->trackingNumber)->toBe('06215000000647');
});

it('requires an explicit DPD product before sending a shipment', function (): void {
    Http::fake();

    expect(fn () => app(DpdShippingAdapter::class)->createShipment($this->shipment))
        ->toThrow(LogicException::class, 'forProducts()');

    Http::assertNothingSent();
});

it('keeps native configuration on immutable adapter instances', function (LabelFormat $format): void {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => [
        ['label' => 'https://ws-etikett.paketomat.at/secure/example.'.strtolower($format->value), 'paknr' => '06215000000647'],
    ]])]);

    $base = app(DpdShippingAdapter::class);
    $normal = $base->forProducts(Products::normalParcel()->withPredict('recipient@example.com'));
    $configured = $normal->forParcelType(ParcelType::B2c)->withLabelFormat($format);
    $small = $configured->forProducts(Products::smallParcel()->withPredict('recipient@example.com'));

    foreach ([$configured, $small, $normal] as $adapter) {
        $result = $adapter->createShipment($this->shipment);
        expect($result->labels[0]->format)->toBe(strtolower(($adapter === $normal ? LabelFormat::Pdf : $format)->value));
    }

    $requests = Http::recorded()->map(fn (array $record): array => $record[0]['data'])->all();
    expect(array_column($requests, 'produkt1'))->toBe(['NP', 'KP', 'NP'])
        ->and(array_column($requests, 'format'))->toBe([$format->value, $format->value, 'PDF'])
        ->and(array_column(array_column($requests, 'paket'), 'pakettyp'))->toBe(['B2C', 'B2C', 'DPD'])
        ->and($requests[0]['produkt6'])->toBe(Products::normalParcel()->withPredict('recipient@example.com')->toArray()['produkt6'])
        ->and($requests[0]['vdat'])->toBe((new DateTimeImmutable('today'))->format('Ymd'));

    expect(fn () => $base->createShipment($this->shipment))->toThrow(LogicException::class);
    Http::assertSentCount(3);
})->with(LabelFormat::cases());

it('maps multiple parcels and optional tracking numbers in response order', function (): void {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => [
        ['label' => 'https://ws-etikett.paketomat.at/secure/one.pdf', 'paknr' => '06215000000647'],
        ['label' => 'https://ws-etikett.paketomat.at/secure/two.pdf'],
        ['label' => 'https://ws-etikett.paketomat.at/secure/three.pdf', 'paknr' => '06215000000648'],
    ]])]);

    $shipment = new Shipment($this->shipment->sender, $this->shipment->recipient, [new Parcel(1200), new Parcel(2400), new Parcel(3600)]);
    $result = app(DpdShippingAdapter::class)->forProducts(Products::normalParcel())->createShipment($shipment);

    expect(array_map(strval(...), $result->trackingNumbers))->toBe(['06215000000647', '06215000000648'])
        ->and($result->labels)->toHaveCount(3)
        ->and($result->labels[1]->trackingNumber)->toBeNull()
        ->and($result->labels[0]->trackingNumber)->toBe($result->trackingNumbers[0])
        ->and($result->labels[2]->trackingNumber)->toBe($result->trackingNumbers[1]);

    Http::assertSent(fn ($request): bool => $request['data']['pakanz'] === '3' && $request['data']['paket']['gewicht'] === '1200~2400~3600');
    Http::assertSentCount(1);
});

it('retains native results when DPD does not return exactly one usable label per parcel', function (array $labels): void {
    config()->set('dpd.retries', 3);
    $payload = ['status' => 'ok', 'result' => $labels];
    Http::fake(['*' => Http::response($payload)]);
    $shipment = new Shipment($this->shipment->sender, $this->shipment->recipient, [new Parcel, new Parcel]);

    try {
        app(DpdShippingAdapter::class)->forProducts(Products::normalParcel())->createShipment($shipment);
        test()->fail('Expected incomplete shipment creation to fail.');
    } catch (DpdShipmentCreationException $exception) {
        expect($exception)->toBeInstanceOf(DpdResponseException::class)
            ->and($exception->result->raw)->toBe($payload)
            ->and($exception->result->labels)->toHaveCount(count($labels));

        foreach ($exception->result->labels as $index => $label) {
            expect($label->raw)->toBe($labels[$index]);
        }
    }

    Http::assertSentCount(1);
})->with([
    'empty response' => [[]],
    'too few labels' => [[['label' => 'https://ws-etikett.paketomat.at/secure/one.pdf', 'paknr' => '06215000000647']]],
    'too many labels' => [array_fill(0, 3, ['label' => 'https://ws-etikett.paketomat.at/secure/one.pdf'])],
    'partial failure' => [[
        ['label' => 'https://ws-etikett.paketomat.at/secure/one.pdf', 'paknr' => '06215000000647', 'saved' => '1'],
        ['label' => null, 'err_code' => '[1] PR02-hv-Invalid insurance'],
    ]],
    'blank label' => [[['label' => '  '], ['label' => 'https://ws-etikett.paketomat.at/secure/two.pdf']]],
]);

it('enforces native DPD validation before sending a neutral shipment', function (array $parcels, string $country): void {
    Http::fake();
    $shipment = new Shipment($this->shipment->sender, new Address('Recipient', 'Street', '1010', 'City', $country), $parcels);

    expect(fn () => app(DpdShippingAdapter::class)->forProducts(Products::normalParcel())->createShipment($shipment))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'too light' => [[new Parcel(9)], 'AT'],
    'too heavy' => [[new Parcel(31501)], 'AT'],
    'missing German weight' => [[new Parcel], 'DE'],
    'mixed weights' => [[new Parcel(1200), new Parcel], 'AT'],
    'different dimensions' => [[new Parcel(1200, 100, 100, 100), new Parcel(1200, 200, 100, 100)], 'AT'],
    'too many parcels' => [array_fill(0, 21, new Parcel), 'AT'],
]);

it('propagates API and transport failures without retrying shipment creation', function (array|string $body, int $status, string $exception): void {
    config()->set('dpd.retries', 3);
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn () => app(DpdShippingAdapter::class)->forProducts(Products::normalParcel())->createShipment($this->shipment))
        ->toThrow($exception);

    Http::assertSentCount(1);
})->with([
    'API rejection' => [['status' => 'error', 'result' => '[0] ER02-Passwort falsch'], 200, DpdApiException::class],
    'HTTP failure' => ['Unavailable', 503, DpdTransportException::class],
]);

it('validates generic tracking numbers with DPD rules before cancellation', function (): void {
    Http::fake();

    expect(fn () => app(DpdShippingAdapter::class)->cancelShipment(new TrackingNumber('other-carrier-number')))
        ->toThrow(InvalidTrackingNumberException::class);

    Http::assertNothingSent();
});

it('rejects labels without a downloadable DPD URL before making an HTTP request', function (Label $label, string $exception): void {
    Http::fake();

    expect(fn () => app(DpdShippingAdapter::class)->downloadLabel($label))->toThrow($exception);

    Http::assertNothingSent();
})->with([
    'inline contents only' => [new Label(contents: '%PDF-test'), LogicException::class],
    'another host' => [new Label(url: 'https://example.com/label.pdf'), DpdResponseException::class],
]);

it('discovers multiple carriers without replacing an application capability binding', function (): void {
    $other = new class implements Carrier, CreatesShipments
    {
        public function carrier(): string
        {
            return 'other';
        }

        public function createShipment(Shipment $shipment): ShipmentResult
        {
            return new ShipmentResult([]);
        }
    };
    app()->instance($other::class, $other);
    app()->instance(CreatesShipments::class, $other);
    app()->tag([$other::class], 'shipping.adapters');

    $adapters = collect(app()->tagged('shipping.adapters'))->keyBy(fn (Carrier $adapter): string => $adapter->carrier());

    expect($adapters->get('dpd'))->toBe(app(DpdShippingAdapter::class))
        ->and($adapters->get('other'))->toBe($other)
        ->and(app(CreatesShipments::class))->toBe($other);
});
