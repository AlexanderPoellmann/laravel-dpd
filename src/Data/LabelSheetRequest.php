<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\LabelSheetFormat;
use InvalidArgumentException;

final readonly class LabelSheetRequest
{
    /** @param list<string> $trackingNumbers */
    public function __construct(
        public array $trackingNumbers,
        public LabelSheetFormat $format = LabelSheetFormat::A4,
        public float|int $offset = 0,
    ) {
        if ($this->trackingNumbers === []) {
            throw new InvalidArgumentException('At least one DPD tracking number is required.');
        }
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'offset' => (string) $this->offset,
            'format' => $this->format->value,
            'trackingnumberList' => implode(',', $this->trackingNumbers),
        ];
    }
}
