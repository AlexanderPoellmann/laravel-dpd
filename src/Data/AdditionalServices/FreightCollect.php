<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;

/** UNFREI (MGL / Metro), WEB.Service section 15.9. */
final readonly class FreightCollect extends DpdService
{
    public function slot(): ProductSlot
    {
        return ProductSlot::Product5;
    }

    public function toPayload(): string
    {
        return 'UNFREI';
    }
}
