<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Data\TrackingNumber;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;

final readonly class ReprintLabel
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(string|TrackingNumber $trackingNumber, LabelFormat $format = LabelFormat::Pdf): LabelSheetResult
    {
        $trackingNumber = $trackingNumber instanceof TrackingNumber ? $trackingNumber : new TrackingNumber($trackingNumber);

        $response = $this->transport->call(ApiFunction::ReprintLabel, [
            'paknr' => $trackingNumber->value,
            'format' => $format->value,
        ]);

        return LabelSheetResult::fromResponse($response);
    }
}
