<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;

final readonly class DpdResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,
        public mixed $result,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['status']) || ! is_string($payload['status']) || trim($payload['status']) === '') {
            throw new DpdResponseException('DPD returned an invalid response status.');
        }

        if (! array_key_exists('result', $payload)) {
            throw new DpdResponseException('DPD response did not contain a result.');
        }

        return new self(
            status: $payload['status'],
            result: $payload['result'] ?? null,
            raw: $payload,
        );
    }

    public function successful(): bool
    {
        return mb_strtolower($this->status) === 'ok';
    }

    /** @return array<string, mixed> */
    public function associativeResult(): array
    {
        if (! is_array($this->result) || ($this->result !== [] && array_is_list($this->result))) {
            throw new DpdResponseException('DPD returned an unexpected result shape.');
        }

        return $this->result;
    }

    /** @return list<array<string, mixed>> */
    public function listResult(): array
    {
        if (! is_array($this->result) || ! array_is_list($this->result)
            || ! array_all($this->result, fn (mixed $item): bool => is_array($item))) {
            throw new DpdResponseException('DPD returned an unexpected result list.');
        }

        return $this->result;
    }

    public function stringResult(): string
    {
        if (! is_string($this->result)) {
            throw new DpdResponseException('DPD returned an unexpected string result.');
        }

        return $this->result;
    }
}
