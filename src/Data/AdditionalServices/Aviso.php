<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data\AdditionalServices;

use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class Aviso extends PrimetimeService
{
    public function __construct(public string $contact)
    {
        if (filter_var($this->contact, FILTER_VALIDATE_EMAIL) === false
            && preg_match('/\A\+[1-9][0-9]+\z/', $this->contact) !== 1) {
            throw new InvalidArgumentException('DPD AVISO requires an email address or an international phone number starting with +.');
        }
    }

    public function slot(): ProductSlot
    {
        return ProductSlot::Product2;
    }

    /** @return array{aviso: string} */
    public function toPayload(): array
    {
        return ['aviso' => $this->contact];
    }
}
