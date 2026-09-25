<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\CancellationResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class CancelLabel
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(string $trackingNumber): CancellationResult
    {
        $response = $this->transport->call(ApiFunction::CancelLabel, ['paknr' => $trackingNumber]);
        $result = $response->associativeResult();

        return new CancellationResult(
            trackingNumber: (string) ($result['paknr'] ?? $trackingNumber),
            cancelled: (bool) ($result['storno'] ?? false),
            raw: $response->raw,
        );
    }
}
