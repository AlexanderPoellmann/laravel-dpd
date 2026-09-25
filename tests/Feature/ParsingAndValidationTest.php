<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Actions\CancelLabel;
use AlexanderPoellmann\LaravelDpd\Actions\ReprintLabel;
use AlexanderPoellmann\LaravelDpd\Data\Address;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdException;
use AlexanderPoellmann\LaravelDpd\Exceptions\InvalidTrackingNumberException;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Support\Facades\Http;

it('validates string tracking numbers before sending cancellation or reprint requests', function (string $method) {
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->{$method}('invalid'))->toThrow(InvalidTrackingNumberException::class);

    Http::assertNothingSent();
})->with(['cancelLabel', 'reprintLabel']);

it('validates tracking numbers when actions are called directly', function (string $action) {
    Http::fake();

    expect(fn () => app($action)->handle('invalid'))->toThrow(InvalidTrackingNumberException::class);

    Http::assertNothingSent();
})->with([CancelLabel::class, ReprintLabel::class]);

it('rejects invalid label sheet numbers before sending', function () {
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->createLabelSheet(new LabelSheetRequest(['06215000000259', 'invalid'])))
        ->toThrow(InvalidTrackingNumberException::class);

    Http::assertNothingSent();
});

it('exposes response parsing failures through the base package exception', function (Closure $operation, mixed $result) {
    Http::fake(['*' => Http::response(['status' => 'ok', 'result' => $result])]);

    expect(fn () => $operation(app(LaravelDpd::class)))->toThrow(DpdException::class);

    Http::assertSentCount(1);
})->with([
    'label list' => [fn (LaravelDpd $dpd) => $dpd->createLabel(new LabelRequest(new Address('Receiver', 'Street', '1010', 'Wien', 'AT'), new Parcel, Products::normalParcel(), new DateTimeImmutable('2026-09-25'))), 'invalid'],
    'sheet URL' => [fn (LaravelDpd $dpd) => $dpd->createLabelSheet(new LabelSheetRequest(['06215000000259'])), ['label' => []]],
    'reprint URL' => [fn (LaravelDpd $dpd) => $dpd->reprintLabel('06215000000259'), ['label' => '']],
    'cancellation result' => [fn (LaravelDpd $dpd) => $dpd->cancelLabel('06215000000259'), 'invalid'],
    'cancellation number' => [fn (LaravelDpd $dpd) => $dpd->cancelLabel('06215000000259'), ['paknr' => 'invalid']],
    'cancellation number type' => [fn (LaravelDpd $dpd) => $dpd->cancelLabel('06215000000259'), ['paknr' => []]],
    'cancellation flag' => [fn (LaravelDpd $dpd) => $dpd->cancelLabel('06215000000259'), ['storno' => []]],
    'self booking URL' => [fn (LaravelDpd $dpd) => $dpd->selfBookingList(new DateTimeImmutable, new DateTimeImmutable), ['dpd' => []]],
    'primetime URL' => [fn (LaravelDpd $dpd) => $dpd->selfBookingList(new DateTimeImmutable, new DateTimeImmutable), ['pt' => []]],
    'pickup result' => [fn (LaravelDpd $dpd) => $dpd->createPickupOrder(new PickupOrderRequest(new DateTimeImmutable)), []],
    'status list' => [fn (LaravelDpd $dpd) => $dpd->status(), [123]],
    'service name' => [fn (LaravelDpd $dpd) => $dpd->status(), [['name' => []]]],
]);
