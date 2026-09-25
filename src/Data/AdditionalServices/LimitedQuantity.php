<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class LimitedQuantity extends DpdService
{
    public function __construct(public int $grossMassInGrams)
    {
        if ($this->grossMassInGrams < 1) {
            throw new InvalidArgumentException('DPD limited quantity gross mass must be a positive number of grams.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product7;
    }

    /** @return array{LQ: string} */
    public function toPayload(): array
    {
        return ['LQ' => (string) $this->grossMassInGrams];
    }
}
