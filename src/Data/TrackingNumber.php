<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Exceptions\InvalidTrackingNumberException;
use JsonSerializable;
use Stringable;

final readonly class TrackingNumber implements JsonSerializable, Stringable
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A[0-9]{14}\z/', $this->value) !== 1) {
            throw new InvalidTrackingNumberException('A DPD tracking number must contain exactly 14 ASCII digits.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
