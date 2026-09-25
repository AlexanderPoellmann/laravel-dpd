<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Events;

final readonly class DpdRequestFailed
{
    public function __construct(
        public string $operation,
        public float $durationMilliseconds,
        public ?int $httpStatus,
        public ?string $errorCode,
    ) {}
}
