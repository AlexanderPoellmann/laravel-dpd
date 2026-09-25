<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Exceptions;

use AlexanderPoellmann\LaravelDpd\Data\DpdError;
use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Enums\DpdErrorCode;

class DpdApiException extends DpdException
{
    /** @var non-empty-list<DpdError> */
    public readonly array $errors;

    public readonly ?string $errorCode;

    public readonly ?DpdErrorCode $knownErrorCode;

    /** @param list<DpdError> $errors */
    public function __construct(
        string $message,
        ?string $errorCode = null,
        ?DpdErrorCode $knownErrorCode = null,
        array $errors = [],
    ) {
        parent::__construct($message);

        $parsed = DpdError::fromString($message);
        $this->errors = $errors !== [] ? array_values($errors) : ($errorCode !== null
            ? [new DpdError($errorCode, $parsed->message, $parsed->index, $knownErrorCode ?? DpdErrorCode::tryFrom($errorCode))]
            : DpdError::parseMany($message));
        $this->errorCode = $errorCode ?? $this->errors[0]->code;
        $this->knownErrorCode = $knownErrorCode ?? $this->errors[0]->knownCode;
    }

    public static function fromResponse(DpdResponse $response): self
    {
        if (is_string($response->result)) {
            return self::fromErrorString($response->result);
        }

        if (! is_array($response->result) || $response->result === []) {
            throw new DpdResponseException('DPD returned an unexpected error result.');
        }

        if (! array_is_list($response->result)) {
            return self::fromErrorString(self::errorString($response->result));
        }

        $errors = [];
        $messages = [];

        foreach ($response->result as $index => $item) {
            if (! is_string($item) && ! is_array($item)) {
                throw new DpdResponseException('DPD returned an unexpected error entry.');
            }

            $message = is_string($item) ? $item : self::errorString($item);
            $messages[] = $message;
            array_push($errors, ...DpdError::parseMany($message, $index));
        }

        return new self(implode("\n", $messages), errors: $errors);
    }

    public static function fromErrorString(string $message): self
    {
        return new self($message !== '' ? $message : 'DPD WEB.Service rejected the request.');
    }

    /** @param array<array-key, mixed> $payload */
    private static function errorString(array $payload): string
    {
        if (! isset($payload['err_code']) || ! is_string($payload['err_code']) || trim($payload['err_code']) === '') {
            throw new DpdResponseException('DPD returned an unexpected error structure.');
        }

        return $payload['err_code'];
    }
}
