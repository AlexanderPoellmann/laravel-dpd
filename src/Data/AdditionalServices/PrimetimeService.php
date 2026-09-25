<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Contracts\AdditionalService;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use InvalidArgumentException;

abstract readonly class PrimetimeService implements AdditionalService
{
    public function validateProduct(Product1|string $product): void
    {
        $knownProduct = $product instanceof Product1 ? $product : Product1::tryFrom($product);

        if ($knownProduct !== null && ! $knownProduct->isPrimetime()) {
            throw new InvalidArgumentException('This additional service requires a primetime product.');
        }
    }

    public function validateParcelType(ParcelType $type): void
    {
        if ($type !== ParcelType::Primetime) {
            throw new InvalidArgumentException('This additional service requires primetime parcels.');
        }
    }
}
