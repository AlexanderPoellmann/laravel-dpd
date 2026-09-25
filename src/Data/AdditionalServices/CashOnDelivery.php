<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

/** Primetime national cash on delivery (WEB.Service section 15.7). */
final readonly class CashOnDelivery extends PrimetimeService
{
    public function __construct(public int $amountInCents, public ?string $reference = null)
    {
        if ($this->amountInCents < 1 || $this->amountInCents > 700000) {
            throw new InvalidArgumentException('DPD primetime cash on delivery must be between 1 and 700,000 cents.');
        }

        if ($this->reference !== null && mb_strlen($this->reference) > 200) {
            throw new InvalidArgumentException('DPD cash on delivery reference may not exceed 200 characters.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product3;
    }

    /** @return array{nnbar: string, refnnbar?: string} */
    public function toPayload(): array
    {
        return [
            'nnbar' => (string) $this->amountInCents,
            ...($this->reference !== null ? ['refnnbar' => $this->reference] : []),
        ];
    }
}
