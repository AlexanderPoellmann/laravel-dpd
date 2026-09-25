<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\IdentityCheckType;
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class IdentityCheck extends PrimetimeService
{
    public function __construct(public string $recipientName, public IdentityCheckType $type = IdentityCheckType::Identity)
    {
        if (trim($this->recipientName) === '' || mb_strlen($this->recipientName) > 30) {
            throw new InvalidArgumentException('DPD identity check requires a recipient name of at most 30 characters.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product2;
    }

    /** @return array<string, string> */
    public function toPayload(): array
    {
        return [$this->type->value => $this->recipientName];
    }
}
