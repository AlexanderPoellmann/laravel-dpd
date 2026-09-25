<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use DateTimeInterface;
use InvalidArgumentException;

final readonly class PickupOrderRequest
{
    public function __construct(
        public DateTimeInterface $date,
        public ?int $parcelCount = null,
        public ?string $comment = null,
    ) {
        if ($this->parcelCount !== null && ($this->parcelCount < 1 || $this->parcelCount > 999)) {
            throw new InvalidArgumentException('DPD pickup parcel count must be between 1 and 999.');
        }
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'datum' => $this->date->format('Ymd'),
            'anzahl' => $this->parcelCount !== null ? (string) $this->parcelCount : '',
            'bemerkung' => $this->comment ?? '',
        ];
    }
}
