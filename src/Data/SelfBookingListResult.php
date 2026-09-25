<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

final readonly class SelfBookingListResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public ?string $dpdUrl,
        public ?string $primetimeUrl,
        public array $raw,
    ) {}
}
