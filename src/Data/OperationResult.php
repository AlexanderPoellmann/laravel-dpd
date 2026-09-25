<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

final readonly class OperationResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $result,
        public array $raw,
    ) {}

    public function successful(): bool
    {
        return in_array(mb_strtolower($this->result), ['ok', 'successful'], true);
    }
}
