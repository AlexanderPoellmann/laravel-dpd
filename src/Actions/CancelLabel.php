<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\CancellationResult;
use AlexanderPoellmann\LaravelDpd\Data\TrackingNumber;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;

final readonly class CancelLabel
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(string|TrackingNumber $trackingNumber): CancellationResult
    {
        $trackingNumber = $trackingNumber instanceof TrackingNumber ? $trackingNumber : new TrackingNumber($trackingNumber);
        $response = $this->transport->call(ApiFunction::CancelLabel, ['paknr' => $trackingNumber->value]);
        $result = $response->associativeResult();

        if (isset($result['paknr']) && ! is_string($result['paknr'])) {
            throw new DpdResponseException('DPD returned an invalid cancellation tracking number.');
        }

        if (isset($result['storno']) && ! is_bool($result['storno'])) {
            throw new DpdResponseException('DPD returned an invalid cancellation flag.');
        }

        return new CancellationResult(
            trackingNumber: (new TrackingNumber($result['paknr'] ?? $trackingNumber->value))->value,
            cancelled: (bool) ($result['storno'] ?? false),
            raw: $response->raw,
        );
    }
}
