<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\SelfBookingListResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use DateTimeInterface;

final readonly class GetSelfBookingList
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(
        DateTimeInterface $from,
        DateTimeInterface $to,
        SelfBookingMode $mode = SelfBookingMode::SelfBookingList,
    ): SelfBookingListResult {
        $response = $this->transport->call(ApiFunction::SelfBookingList, [
            'von' => $from->format('Ymd'),
            'bis' => $to->format('Ymd'),
            'modus' => (string) $mode->value,
        ]);
        $result = $response->associativeResult();

        return new SelfBookingListResult(
            dpdUrl: isset($result['dpd']) ? (string) $result['dpd'] : null,
            primetimeUrl: isset($result['pt']) ? (string) $result['pt'] : null,
            raw: $response->raw,
        );
    }
}
