<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use UnexpectedValueException;

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
        return new self(
            status: (string) ($payload['status'] ?? ''),
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
        if (! is_array($this->result)) {
            throw new UnexpectedValueException('DPD returned an unexpected result shape.');
        }

        return $this->result;
    }

    /** @return list<array<string, mixed>> */
    public function listResult(): array
    {
        if (! is_array($this->result) || ! array_is_list($this->result)
            || ! array_all($this->result, fn (mixed $item): bool => is_array($item))) {
            throw new UnexpectedValueException('DPD returned an unexpected result list.');
        }

        return $this->result;
    }

    public function stringResult(): string
    {
        if (! is_scalar($this->result) && $this->result !== null) {
            throw new UnexpectedValueException('DPD returned an unexpected scalar result.');
        }

        return (string) $this->result;
    }
}
