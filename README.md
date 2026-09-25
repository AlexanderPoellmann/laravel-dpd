# Laravel DPD

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alexanderpoellmann/laravel-dpd.svg?style=flat-square)](https://packagist.org/packages/alexanderpoellmann/laravel-dpd)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/alexanderpoellmann/laravel-dpd/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/alexanderpoellmann/laravel-dpd/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/alexanderpoellmann/laravel-dpd/code-quality.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/alexanderpoellmann/laravel-dpd/actions/workflows/code-quality.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/alexanderpoellmann/laravel-dpd.svg?style=flat-square)](https://packagist.org/packages/alexanderpoellmann/laravel-dpd)

A Laravel integration for the DPD Austria / Gebrüder Weiss Paketdienst **WEB.Service 1.0.6**.

The package uses DPD's documented REST API and Laravel's HTTP client. Automatic retries are applied only to read/re-render style operations; state-changing calls such as label creation, cancellation, pickup/collection orders, and order import are never retried automatically because DPD documents no idempotency key for them. It exposes typed request/result objects and focused actions for all WEB.Service functions documented in the supplied manual:

- parcel label creation (`getLabel`)
- A4/A6/ZPL label sheet creation (`getLabelA4x4`)
- label cancellation (`cancelByTracknr`)
- label reprint (`getReprintByTracknr`)
- self-booking list / daily closing (`getSblList`)
- pickup order (`abholauftrag`)
- collection request (`rueckholauftrag_v2`)
- WEB.omat order import (`importAube`)
- service status (`getStatus`)

## Installation

```bash
composer require alexanderpoellmann/laravel-dpd
```

Publish the configuration if you want to customize it:

```bash
php artisan vendor:publish --tag="laravel-dpd-config"
```

Add credentials to `.env`:

```dotenv
DPD_USERNAME=
DPD_CLIENT=

# Use either the plain password ...
DPD_PASSWORD=

# ... or the MD5 digest supplied by DPD. The digest takes precedence.
DPD_PASSWORD_MD5=

DPD_TIMEOUT=30
DPD_CONNECT_TIMEOUT=10
DPD_RETRIES=2
DPD_RETRY_DELAY_MS=250
```

The default REST endpoint is:

```text
https://ws.paketomat.at/restapi106/service.php
```

Override it with `DPD_ENDPOINT` when DPD provides another endpoint.

> DPD's documentation states that test credentials must not be used with real recipient data.

## Create a label

```php
use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\LabelOption;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Facades\Dpd;

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
        references: ['ORDER-12345'],
    ),
    products: Products::normalParcel()->withPredict('maria@example.com'),
    shippingDate: now(),
    option: LabelOption::DigitalLabel,
    customerReference: 'ORDER-12345',
);

$result = Dpd::createLabel($request);

$label = $result->labels[0];

$label->trackingNumber; // 14 digit parcel number
$label->url;            // PDF/ZPL/EPL link returned by DPD
$label->barcodeContent;
$label->digitalCodeUrl; // when option "2d" is requested
```

DPD may return a top-level successful response containing a failed item in a multi-label result. The package deliberately keeps `Label::$errorCode` on each item instead of throwing away successful siblings. Use `$result->successful()` to require every returned label to be successful.

## Products and additional services

`Product1` contains the documented product-1 values (`KP`, `NP`, `RETURN`, `S2S`, primetime time services, and Saturday services).

Product 2-7 structures vary by service. `Products` therefore keeps those fields typed as `array|string|null` and provides helpers for common DPD services:

```php
use AlexanderPoellmann\LaravelDpd\Data\Products;

$products = Products::normalParcel()
    ->withHigherInsurance(1500000) // cents, as expected by DPD
    ->withPredict('recipient@example.com');
```

You can pass another documented structure directly:

```php
$products = new Products(
    product1: 'NP',
    product2: ['hv' => '1500000'],
    product6: ['pred' => 'recipient@example.com'],
);
```

## Other WEB.Service functions

```php
use AlexanderPoellmann\LaravelDpd\Data\CollectionRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use AlexanderPoellmann\LaravelDpd\Facades\Dpd;

$sheet = Dpd::createLabelSheet(new LabelSheetRequest([
    '06215000000259',
    '06215000000260',
]));

$cancelled = Dpd::cancelLabel('06215000000258');
$copy = Dpd::reprintLabel('06215000000260');

$list = Dpd::selfBookingList(
    from: now()->startOfDay(),
    to: now()->endOfDay(),
    mode: SelfBookingMode::SelfBookingList,
);

$pickup = Dpd::createPickupOrder(new PickupOrderRequest(
    date: now()->addDay(),
    parcelCount: 3,
    comment: 'Please call before pickup',
));

$collection = Dpd::createCollectionRequest(new CollectionRequest(
    date: now()->addDay(),
    pickupAddress: $pickupAddress,
    recipientAddress: $recipientAddress,
    parcelType: ParcelType::Dpd,
    product1: Product1::NormalParcel,
));

$order = Dpd::importOrder(new OrderImportRequest(
    orderNumber: 'ORDER-12345',
    recipient: $recipientAddress,
    parcelType: ParcelType::Dpd,
    product1: Product1::NormalParcel,
));

$services = Dpd::status();
```

## Exceptions

Transport and API failures are separated:

- `ConfigurationException`: missing credentials
- `DpdTransportException`: connection, HTTP, or malformed JSON failure
- `DpdApiException`: DPD returned a non-`ok` response

`DpdApiException` exposes the parsed `errorCode` and, for documented base codes, `knownErrorCode` (`DpdErrorCode`).

```php
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;

try {
    $result = Dpd::cancelLabel($trackingNumber);
} catch (DpdApiException $exception) {
    report($exception);

    $exception->errorCode;      // e.g. FR06
    $exception->knownErrorCode; // enum when recognized
}
```

## Label URL lifetime

DPD documents that a returned label link remains valid for one week, but after the link is first called it is only active for one hour. Persist the label content in your own storage when your workflow needs longer retention.

## Testing and quality checks

```bash
composer test
composer analyse
composer format:test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
