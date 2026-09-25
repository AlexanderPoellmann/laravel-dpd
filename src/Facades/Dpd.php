<?php

namespace AlexanderPoellmann\LaravelDpd\Facades;

use AlexanderPoellmann\LaravelDpd\Data\CancellationResult;
use AlexanderPoellmann\LaravelDpd\Data\CollectionRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelResult;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Data\OperationResult;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Data\SelfBookingListResult;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use DateTimeInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @see LaravelDpd
 *
 * @method static LabelResult createLabel(LabelRequest $request)
 * @method static LabelSheetResult createLabelSheet(LabelSheetRequest $request)
 * @method static CancellationResult cancelLabel(string $trackingNumber)
 * @method static LabelSheetResult reprintLabel(string $trackingNumber, LabelFormat $format = LabelFormat::Pdf)
 * @method static SelfBookingListResult selfBookingList(DateTimeInterface $from, DateTimeInterface $to, SelfBookingMode $mode = SelfBookingMode::SelfBookingList)
 * @method static OperationResult createPickupOrder(PickupOrderRequest $request)
 * @method static OperationResult createCollectionRequest(CollectionRequest $request)
 * @method static OperationResult importOrder(OrderImportRequest $request)
 * @method static array status()
 */
class Dpd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelDpd::class;
    }
}
