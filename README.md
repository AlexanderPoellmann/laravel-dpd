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
DPD_EVENTS_ENABLED=false
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
    parcels: new Parcel(
        type: ParcelType::B2c,
        weightInGrams: 3500,
    ),
    products: Products::normalParcel()->withPredict('maria@example.com'),
    shippingDate: now(),
    option: LabelOption::DigitalLabel,
    customerReference: 'ORDER-12345',
    references: ['ORDER-12345'],
    invoiceNumber: 'INV-12345',
);

$result = Dpd::createLabel($request);

$label = $result->labels[0];

$label->trackingNumber; // 14 digit parcel number
$label->url;            // PDF/ZPL/EPL link returned by DPD
$label->barcodeContent;
$label->digitalCodeUrl; // when option "2d" is requested
```

DPD may return a top-level successful response containing a failed item in a multi-label result. The package deliberately keeps `Label::$errorCode` on each item instead of throwing away successful siblings. Use `$result->successful()` to require every returned label to be successful.

## Multiple parcels

Pass one `Parcel` or a list of parcels to `LabelRequest`. Each parcel has its own weight in grams. References and the invoice number belong to the shipment:

```php
$request = new LabelRequest(
    recipient: $recipient,
    parcels: [
        new Parcel(weightInGrams: 3000),
        new Parcel(weightInGrams: 13500),
        new Parcel(weightInGrams: 23500),
    ],
    products: Products::normalParcel(),
    shippingDate: now(),
    references: ['0815ab', 'LFNR214687'],
    invoiceNumber: '202308abcd',
);

$result = Dpd::createLabel($request);
```

The package derives the label count from `$request->parcels` and encodes the weights in parcel order. Application code supplies integers and arrays; the package handles DPD's separators. This follows **Request und Response.pdf, section 2.4, page 11** for individual weights and **section 3.4, page 60** for the REST envelope. The REST example omits all weights, which is also supported where weights are optional.

Requests accept 1–20 parcels. Each supplied weight must be 10–31,500 grams, or at most 20,000 grams for parcel shop delivery; product `KP` allows at most 3,000 grams per parcel. Germany requires a weight for every parcel. Elsewhere, supply every weight or omit all weights. The normal-parcel product accepts the documented mixed-weight example, including its 3,000-gram parcel.

All parcels must use the same parcel type and volume because DPD documents one `pakettyp` and one `volumen` per request. Dimensions must be complete when supplied; requests with different volumes must be split. References allow at most ten entries and 210 characters in total including separators. Invoice numbers allow 50 characters. Shipping dates may be at most 25 days in the future.

## Products and additional services

`Products` composes immutable, typed additional services. Each object validates its values and supplies its DPD payload and product slot. Monetary amounts are integers in cents; do not pass floats or formatted currency strings.

```php
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\HigherInsurance;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Predict;
use AlexanderPoellmann\LaravelDpd\Data\Products;

$products = Products::normalParcel()
    ->with(new HigherInsurance(amountInCents: 1500000)) // EUR 15,000
    ->with(new Predict('recipient@example.com'));

// Pass $products to LabelRequest(products: $products, ...).
```

The following services follow **WEB_Service_EN.pdf, sections 15.5?15.13**. The concrete REST examples in **Request und Response.pdf, sections 3.3, 3.5, 3.14?3.16** establish the `pred`, `hv`, `abt`, `nnbar`/`refnnbar`, and `wp` payloads.

| Service class | Product slot / wire value | Local validation |
| --- | --- | --- |
| `HigherInsurance` | 2 / `hv` | 52,001?1,500,000 cents (EUR 520.01?15,000) |
| `Predict` | 6 / `pred` | Valid email; SMS is not supported by this version |
| `CashOnDelivery` | 3 / `nnbar`, optional `refnnbar` | Primetime national COD; 1?700,000 cents; reference at most 200 characters |
| `IdentityCheck` | 2 / `id`, `id16`, or `id18` | Primetime; required recipient name, at most 30 characters |
| `DepartmentDelivery` | 2 / `abt` | Primetime; required department, at most 30 characters |
| `Aviso` | 2 / `aviso` | Primetime; valid email or international phone number using `+` and digits |
| `ValuableParcel` | 4 / `wp` | Primetime; 52,001?1,500,000 cents |
| `FreightCollect` | 5 / `UNFREI` | DPD's documented MGL / Metro service; no additional fields |
| `ConstructionSiteDelivery` | 6 / `bau` | Primetime; no additional fields |
| `LimitedQuantity` | 7 / `LQ` | Positive gross mass in grams; the manual does not specify a numeric ADR limit |

### Primetime services

COD is sufficiently specified for **primetime national shipments** in this documentation. The DPD COD error code alone does not define a standard DPD COD request, so no such payload is inferred.

```php
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\CashOnDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\IdentityCheck;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\ValuableParcel;
use AlexanderPoellmann\LaravelDpd\Enums\IdentityCheckType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;

$products = (new Products(Product1::Primetime17))
    ->with(new IdentityCheck('Maria Muster', IdentityCheckType::Age18))
    ->with(new CashOnDelivery(amountInCents: 36000, reference: 'ORDER-42'))
    ->with(new ValuableParcel(amountInCents: 1500000));

$request = new LabelRequest(
    recipient: $recipient, // Primetime also requires a recipient phone number.
    parcels: new Parcel(type: ParcelType::Primetime, weightInGrams: 3500),
    products: $products,
    shippingDate: now(),
);
```

Use `IdentityCheckType::Identity` (the default), `Age16`, or `Age18` for the documented identity variants. Other primetime Product 2 choices include `new DepartmentDelivery('Verkauf')` and `new Aviso('+4369912345678')`.

`with()` returns a new `Products` instance and replaces the selected slot. For example, departmental delivery replaces an identity check in Product 2; their payloads are not automatically merged. Other slots are retained. Known DPD/primetime Product1 mismatches are rejected for both enum and string codes, and requests check the parcel family. Unknown Product1 codes remain available without guessing their compatibility.

### Compatibility and raw payloads

Existing `withHigherInsurance()` and `withPredict()` calls still work and now use typed validation. Constructor arrays, strings, and `null` remain supported as raw values. Prefer `withRaw()` to make bypassing service validation explicit:

```php
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;

$products = Products::normalParcel()
    ->withRaw(ProductSlot::Product2, ['hv' => '1500000'])
    ->with(new Predict('recipient@example.com'));
```

`withRaw()` accepts a string or an array, including nested future payloads, and preserves it exactly. `RawAdditionalService` offers the equivalent service object. Raw values bypass service and compatibility validation; use payloads agreed with DPD. The drop-off location catalogue (`asg`) is external to the bundled PDFs, and the swap service (`AUST`) has no concrete payload example there; these remain raw until their allowed values and payload structure are confirmed.

Order import's existing `product2`?`product7` parameters also accept typed service objects, for example `product6: new Predict('recipient@example.com')`. Typed values are validated and serialized through `Products`.

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

$services = Dpd::serviceStatus();
```

`serviceStatus()` reports DPD WEB.Service availability and returns a list of `ServiceState` objects. The existing `status()` method remains a deprecated forwarding alias with the same return values and exceptions. Both call DPD's `getStatus` operation; they do not retrieve parcel tracking information.

Parcel numbers must contain exactly 14 ASCII digits; leading zeroes are preserved. Cancellation, reprints, and label sheets accept strings or `Data\TrackingNumber` objects. Existing result properties continue to expose strings. Invalid numbers throw `InvalidTrackingNumberException` before an HTTP request is sent.

`LabelSheetRequest` serializes `trackingnumberList` as a JSON array, following the REST example in **Request und Response.pdf, section 3.22, page 94**. The main manual's section 18.2 shows a conflicting comma-separated example.

## Optional request events

Set `DPD_EVENTS_ENABLED=true` or `config(['dpd.events.enabled' => true])` to enable sanitized Laravel events. They are disabled by default. The package does not register loggers or write logs.

| Event in `AlexanderPoellmann\LaravelDpd\Events` | Metadata |
| --- | --- |
| `DpdRequestStarted` | `operation` |
| `DpdRequestSucceeded` | `operation`, `durationMilliseconds`, `httpStatus` |
| `DpdRequestFailed` | `operation`, `durationMilliseconds`, nullable `httpStatus`, nullable `errorCode` |

Operation names are the DPD API names, such as `getLabel` and `getStatus`, or `downloadLabel` for document retrieval. A logical request emits a started event and a final succeeded or failed event. Duration includes HTTP retries; intermediate attempts do not emit separate package events. Connection failures have no HTTP status. The failure event exposes only the first parsed DPD code when available, never its message.

Events contain no credentials, password hashes, recipient data, tracking numbers, URLs, exception objects, or raw request/response payloads. Applications can register listeners in a service provider:

```php
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestFailed;
use Illuminate\Support\Facades\Event;

Event::listen(DpdRequestFailed::class, function (DpdRequestFailed $event): void {
    // Feed these sanitized values into your application's metrics or alerting.
    $event->operation;
    $event->durationMilliseconds;
    $event->httpStatus;
    $event->errorCode;
});
```

API events describe the built-in REST transport's HTTP exchange and response envelope, before operation-specific result mapping. A successful envelope can still contain failed individual labels; inspect the returned label errors for those outcomes. Download events include document validation. Input or configuration failures before a request starts emit no events. Custom transports can choose their own event integration. Laravel listeners run normally; exceptions thrown by listeners propagate to the caller.

## Exceptions

Transport and API failures are separated:

- `ConfigurationException`: missing credentials
- `DpdTransportException`: connection, HTTP, or malformed JSON failure
- `DpdApiException`: DPD reported errors, including a global `err_code` inside an `ok` response
- `DpdResponseException`: an unexpected response structure, field type, label URL, or document content
- `InvalidTrackingNumberException`: a parcel number does not contain exactly 14 ASCII digits

All of these exceptions extend `DpdException`, so callers can catch package failures through that base class.

`DpdApiException::$errors` contains immutable `DpdError` objects. Each exposes `code`, `message`, optional `index`, and optional `knownCode` (`DpdErrorCode`). Codes without a known enum retain their original code; uncoded errors have a `null` code. Documented service-specific codes such as `PR02-hv` are preserved and recognized.

```php
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;

try {
    $result = Dpd::cancelLabel($trackingNumber);
} catch (DpdApiException $exception) {
    foreach ($exception->errors as $error) {
        $error->code;      // e.g. FR06
        $error->message;   // human-readable description without the code/index prefix
        $error->index;     // e.g. 0, or null if absent
        $error->knownCode; // DpdErrorCode when recognized
    }
}
```

The existing `errorCode` and `knownErrorCode` exception properties remain aliases for the first error. Partial label results retain all siblings: inspect `$label->errors` for structured failures and `$label->errorCode` for the original string. Multiple indexed errors and error lists are parsed without requiring callers to inspect exception messages.

Transport exception messages contain no raw response body or underlying connection message. For explicit diagnostics, `DpdTransportException::$rawBody` retains the HTTP/malformed JSON response body, `statusCode` retains an HTTP failure status, and `getPrevious()` retains a connection exception. These diagnostic values may contain recipient data or temporary URL tokens; they are not part of the normal message.

## Download label documents

```php
$result = Dpd::createLabel($request);
$document = Dpd::downloadLabel($result->labels[0]);

$document->contents;  // Binary string, preserved byte for byte
$document->mimeType;  // application/pdf, or text/plain for ZPL/EPL
$document->format;    // LabelFormat::Pdf, LabelFormat::Zpl, or LabelFormat::Epl
$document->extension; // pdf, zpl, or epl

return response($document->contents, 200, [
    'Content-Type' => $document->mimeType,
    'Content-Disposition' => 'attachment; filename="label.'.$document->extension.'"',
]);
```

`downloadLabel()` accepts a successful `Label`, a `LabelSheetResult` returned by sheet creation or reprint, or its URL string. It uses Laravel's HTTP client and returns a readonly `LabelDocument`; it does not write to Laravel Storage or the filesystem.

Downloads require HTTPS on the exact documented host `ws-etikett.paketomat.at`, with a PDF/ZPL/EPL filename at the root or under `/secure/`. Credentials, fragments, nonstandard ports, other hosts, and unexpected paths are rejected before a request is sent. Redirects are disabled. Failed label results, unsuccessful HTTP responses, unexpected MIME types, empty contents, invalid PDF headers, and HTML/JSON error pages are rejected. Printer commands are returned as text without being interpreted. Missing or generic binary MIME headers are normalized using the URL's format. Digital-code PNG URLs are not label documents supported by this method.

The download uses the configured HTTP timeouts and sends no DPD API credentials. Expired URLs produce `DpdTransportException` when DPD returns an HTTP error; an error page returned with HTTP 200 produces `DpdResponseException`.

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
