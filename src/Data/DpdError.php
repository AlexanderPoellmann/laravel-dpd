<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\DpdErrorCode;

final readonly class DpdError
{
    public function __construct(
        public ?string $code,
        public string $message,
        public ?int $index = null,
        public ?DpdErrorCode $knownCode = null,
    ) {}

    public static function fromString(string $error, ?int $index = null): self
    {
        $message = trim($error);

        if (preg_match('/\A\[(\d+)\]\s*/', $message, $prefix) === 1) {
            $index = (int) $prefix[1];
            $message = substr($message, strlen($prefix[0]));
        }

        if (preg_match('/\A([A-Z]{2}\d{2})(?=\s|[-:]|\z)/', $message, $matches) !== 1) {
            return new self(null, $message, $index);
        }

        $code = $matches[1];

        // Service-specific matchcodes (e.g. PR02-hv) precede the human-readable message.
        foreach (DpdErrorCode::cases() as $knownCode) {
            if (str_starts_with($knownCode->value, $code.'-')
                && preg_match('/\A'.preg_quote($knownCode->value, '/').'(?=\s|[-:]|\z)/', $message) === 1) {
                $code = $knownCode->value;
                break;
            }
        }

        $message = preg_replace('/\A\s*[-:]?\s*/', '', substr($message, strlen($code))) ?? '';

        return new self($code, $message, $index, DpdErrorCode::tryFrom($code));
    }

    /** @return non-empty-list<self> */
    public static function parseMany(string $errors, ?int $index = null): array
    {
        $parts = preg_split('/(?=\[\d+\]\s*[A-Z]{2}\d{2}(?:\b|-))/', trim($errors), flags: PREG_SPLIT_NO_EMPTY);

        return array_map(fn (string $error): self => self::fromString($error, $index), $parts ?: ['']);
    }
}
