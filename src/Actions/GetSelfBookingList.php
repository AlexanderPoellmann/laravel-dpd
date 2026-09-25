<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\SelfBookingListResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Enums\SelfBookingMode;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
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

        foreach (['dpd', 'pt'] as $field) {
            if (isset($result[$field]) && ! is_string($result[$field])) {
                throw new DpdResponseException("DPD returned an invalid self-booking list {$field} URL.");
            }
        }

        return new SelfBookingListResult(
            dpdUrl: isset($result['dpd']) ? (string) $result['dpd'] : null,
            primetimeUrl: isset($result['pt']) ? (string) $result['pt'] : null,
            raw: $response->raw,
        );
    }
}
