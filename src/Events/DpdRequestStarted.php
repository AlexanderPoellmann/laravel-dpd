<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Events;

final readonly class DpdRequestStarted
{
    public function __construct(public string $operation) {}
}
