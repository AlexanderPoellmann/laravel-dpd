<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;

final readonly class ReprintLabel
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(string $trackingNumber, LabelFormat $format = LabelFormat::Pdf): LabelSheetResult
    {
        $response = $this->transport->call(ApiFunction::ReprintLabel, [
            'paknr' => $trackingNumber,
            'format' => $format->value,
        ]);

        return LabelSheetResult::fromResponse($response);
    }
}
