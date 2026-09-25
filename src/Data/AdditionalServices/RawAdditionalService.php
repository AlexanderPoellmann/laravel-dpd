<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Contracts\AdditionalService;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;

final readonly class RawAdditionalService implements AdditionalService
{
    /** @param array<string, mixed>|string $payload */
    public function __construct(public ProductSlot $productSlot, public array|string $payload) {}

    public function slot(): ProductSlot
    {
        return $this->productSlot;
    }

    /** @return array<string, mixed>|string */
    public function toPayload(): array|string
    {
        return $this->payload;
    }

    public function validateProduct(Product1|string $product): void {}

    public function validateParcelType(ParcelType $type): void {}
}
