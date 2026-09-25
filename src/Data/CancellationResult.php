<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

final readonly class CancellationResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $trackingNumber,
        public bool $cancelled,
        public array $raw,
    ) {}
}
