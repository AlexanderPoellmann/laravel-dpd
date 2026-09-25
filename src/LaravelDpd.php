<?php

namespace AlexanderPoellmann\LaravelDpd;

use AlexanderPoellmann\LaravelDpd\Actions\CancelLabel;
use AlexanderPoellmann\LaravelDpd\Actions\CreateCollectionRequest;
use AlexanderPoellmann\LaravelDpd\Actions\CreateLabel;
use AlexanderPoellmann\LaravelDpd\Actions\CreateLabelSheet;
use AlexanderPoellmann\LaravelDpd\Actions\CreatePickupOrder;
use AlexanderPoellmann\LaravelDpd\Actions\GetSelfBookingList;
use AlexanderPoellmann\LaravelDpd\Actions\GetStatus;
use AlexanderPoellmann\LaravelDpd\Actions\ImportOrder;
use AlexanderPoellmann\LaravelDpd\Actions\ReprintLabel;
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
use AlexanderPoellmann\LaravelDpd\Data\ServiceState;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use DateTimeInterface;

final readonly class LaravelDpd
{
    public function __construct(
        private CreateLabel $createLabel,
        private CreateLabelSheet $createLabelSheet,
        private CancelLabel $cancelLabel,
        private ReprintLabel $reprintLabel,
        private GetSelfBookingList $getSelfBookingList,
        private CreatePickupOrder $createPickupOrder,
        private CreateCollectionRequest $createCollectionRequest,
        private ImportOrder $importOrder,
        private GetStatus $getStatus,
    ) {}

    public function createLabel(LabelRequest $request): LabelResult
    {
        return $this->createLabel->handle($request);
    }

    public function createLabelSheet(LabelSheetRequest $request): LabelSheetResult
    {
        return $this->createLabelSheet->handle($request);
    }

    public function cancelLabel(string $trackingNumber): CancellationResult
    {
        return $this->cancelLabel->handle($trackingNumber);
    }

    public function reprintLabel(string $trackingNumber, LabelFormat $format = LabelFormat::Pdf): LabelSheetResult
    {
        return $this->reprintLabel->handle($trackingNumber, $format);
    }

    public function selfBookingList(
        DateTimeInterface $from,
        DateTimeInterface $to,
        SelfBookingMode $mode = SelfBookingMode::SelfBookingList,
    ): SelfBookingListResult {
        return $this->getSelfBookingList->handle($from, $to, $mode);
    }

    public function createPickupOrder(PickupOrderRequest $request): OperationResult
    {
        return $this->createPickupOrder->handle($request);
    }

    public function createCollectionRequest(CollectionRequest $request): OperationResult
    {
        return $this->createCollectionRequest->handle($request);
    }

    public function importOrder(OrderImportRequest $request): OperationResult
    {
        return $this->importOrder->handle($request);
    }

    /** @return list<ServiceState> */
    public function status(): array
    {
        return $this->getStatus->handle();
    }
}
