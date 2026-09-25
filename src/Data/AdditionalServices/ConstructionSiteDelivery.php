<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;

final readonly class ConstructionSiteDelivery extends PrimetimeService
{
    public function slot(): ProductSlot
    {
        return ProductSlot::Product6;
    }

    public function toPayload(): string
    {
        return 'bau';
    }
}
