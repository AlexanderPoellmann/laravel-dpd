<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Facades;

use AlexanderPoellmann\LaravelDpd\Data\CancellationResult;
use AlexanderPoellmann\LaravelDpd\Data\CollectionRequest;
use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Data\LabelDocument;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelResult;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Data\OperationResult;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Data\SelfBookingListResult;
use AlexanderPoellmann\LaravelDpd\Data\ServiceState;
use AlexanderPoellmann\LaravelDpd\Data\TrackingNumber;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use DateTimeInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @see LaravelDpd
 *
 * @method static LabelResult createLabel(LabelRequest $request)
 * @method static LabelDocument downloadLabel(Label|LabelSheetResult|string $label)
 * @method static LabelSheetResult createLabelSheet(LabelSheetRequest $request)
 * @method static CancellationResult cancelLabel(string|TrackingNumber $trackingNumber)
 * @method static LabelSheetResult reprintLabel(string|TrackingNumber $trackingNumber, LabelFormat $format = LabelFormat::Pdf)
 * @method static SelfBookingListResult selfBookingList(DateTimeInterface $from, DateTimeInterface $to, SelfBookingMode $mode = SelfBookingMode::SelfBookingList)
 * @method static OperationResult createPickupOrder(PickupOrderRequest $request)
 * @method static OperationResult createCollectionRequest(CollectionRequest $request)
 * @method static OperationResult importOrder(OrderImportRequest $request)
 * @method static list<ServiceState> serviceStatus()
 * @method static list<ServiceState> status() @deprecated Use serviceStatus() to retrieve WEB.Service availability.
 */
class Dpd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelDpd::class;
    }
}
