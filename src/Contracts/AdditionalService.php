<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Contracts;

use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;

interface AdditionalService
{
    public function slot(): ProductSlot;

    /** @return array<string, mixed>|string */
    public function toPayload(): array|string;

    public function validateProduct(Product1|string $product): void;

    public function validateParcelType(ParcelType $type): void;
}
