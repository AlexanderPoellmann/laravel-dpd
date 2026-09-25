<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\ServiceStatus;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;

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
        foreach (['name', 'status'] as $field) {
            if (isset($payload[$field]) && ! is_string($payload[$field])) {
                throw new DpdResponseException("DPD returned an invalid service {$field}.");
            }
        }

        if (isset($payload['serviceid']) && ! is_int($payload['serviceid'])
            && (! is_string($payload['serviceid']) || ! ctype_digit($payload['serviceid']))) {
            throw new DpdResponseException('DPD returned an invalid service ID.');
        }

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
