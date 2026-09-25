<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Exceptions;

use Throwable;

class DpdTransportException extends DpdException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $rawBody = null,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromHttpStatus(int $status, string $body): self
    {
        return new self(sprintf('DPD returned HTTP %d.', $status), rawBody: $body, statusCode: $status);
    }

    public static function invalidJson(string $body): self
    {
        return new self('DPD WEB.Service returned an invalid JSON response.', rawBody: $body);
    }

    public static function fromThrowable(Throwable $exception): self
    {
        return new self('Unable to communicate with DPD.', previous: $exception);
    }
}
