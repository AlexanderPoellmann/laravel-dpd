<?php

namespace AlexanderPoellmann\LaravelDpd\Exceptions;

use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Enums\DpdErrorCode;

class DpdApiException extends DpdException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?DpdErrorCode $knownErrorCode = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(DpdResponse $response): self
    {
        $message = is_string($response->result)
            ? $response->result
            : json_encode($response->result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return self::fromErrorString($message ?: 'DPD WEB.Service rejected the request.');
    }

    public static function fromErrorString(string $message): self
    {
        $errorCode = self::extractErrorCode($message);

        return new self(
            message: $message,
            errorCode: $errorCode,
            knownErrorCode: $errorCode ? DpdErrorCode::tryFrom($errorCode) : null,
        );
    }

    private static function extractErrorCode(string $message): ?string
    {
        preg_match('/\b(?:ER|PR|FR|NK|AU)\d{2}\b/', $message, $matches);

        return $matches[0] ?? null;
    }
}
