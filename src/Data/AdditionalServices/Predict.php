<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class Predict extends DpdService
{
    public function __construct(public string $email)
    {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('DPD Predict requires a valid email address.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product6;
    }

    /** @return array{pred: string} */
    public function toPayload(): array
    {
        return ['pred' => $this->email];
    }
}
