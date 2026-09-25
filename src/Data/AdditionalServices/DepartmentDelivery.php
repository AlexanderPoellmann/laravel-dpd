<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class DepartmentDelivery extends PrimetimeService
{
    public function __construct(public string $department)
    {
        if (trim($this->department) === '' || mb_strlen($this->department) > 30) {
            throw new InvalidArgumentException('DPD departmental delivery requires a department of at most 30 characters.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product2;
    }

    /** @return array{abt: string} */
    public function toPayload(): array
    {
        return ['abt' => $this->department];
    }
}
