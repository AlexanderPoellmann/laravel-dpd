<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;

final readonly class LabelSheetResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $url,
        public array $raw,
    ) {}

    public static function fromResponse(DpdResponse $response): self
    {
        $url = $response->associativeResult()['label'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            throw new DpdResponseException('DPD response did not contain a label URL.');
        }

        return new self($url, $response->raw);
    }
}
