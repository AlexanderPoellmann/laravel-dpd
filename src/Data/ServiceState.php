<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\ServiceStatus;

final readonly class ServiceState
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public int $serviceId,
        public string $name,
        public string $status,
        public ?ServiceStatus $knownStatus,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $status = (string) ($payload['status'] ?? '');

        return new self(
            serviceId: (int) ($payload['serviceid'] ?? 0),
            name: (string) ($payload['name'] ?? ''),
            status: $status,
            knownStatus: ServiceStatus::tryFrom($status),
            raw: $payload,
        );
    }
}
