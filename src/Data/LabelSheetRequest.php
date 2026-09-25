<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\LabelSheetFormat;
use AlexanderPoellmann\LaravelDpd\Exceptions\InvalidTrackingNumberException;
use InvalidArgumentException;

final readonly class LabelSheetRequest
{
    /** @var list<string> */
    public array $trackingNumbers;

    /** @param array<array-key, string|TrackingNumber> $trackingNumbers */
    public function __construct(
        array $trackingNumbers,
        public LabelSheetFormat $format = LabelSheetFormat::A4,
        public float|int $offset = 0,
    ) {
        if ($trackingNumbers === []) {
            throw new InvalidArgumentException('At least one DPD tracking number is required.');
        }

        $this->trackingNumbers = array_values(array_map(self::normalizeTrackingNumber(...), $trackingNumbers));
    }

    /** @return array{offset: string, format: string, trackingnumberList: list<string>} */
    public function toArray(): array
    {
        return [
            'offset' => (string) $this->offset,
            'format' => $this->format->value,
            'trackingnumberList' => $this->trackingNumbers,
        ];
    }

    private static function normalizeTrackingNumber(mixed $number): string
    {
        if (! is_string($number) && ! $number instanceof TrackingNumber) {
            throw new InvalidTrackingNumberException('A DPD tracking number must be a string or TrackingNumber.');
        }

        return ($number instanceof TrackingNumber ? $number : new TrackingNumber($number))->value;
    }
}
