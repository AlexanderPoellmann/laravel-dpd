<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class ValuableParcel extends PrimetimeService
{
    public function __construct(public int $amountInCents)
    {
        if ($this->amountInCents < 52001 || $this->amountInCents > 1500000) {
            throw new InvalidArgumentException('DPD valuable parcel insurance must be between 52,001 and 1,500,000 cents.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product4;
    }

    /** @return array{wp: string} */
    public function toPayload(): array
    {
        return ['wp' => (string) $this->amountInCents];
    }
}
