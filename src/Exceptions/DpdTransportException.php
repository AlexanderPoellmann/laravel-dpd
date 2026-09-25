<?php

namespace AlexanderPoellmann\LaravelDpd\Exceptions;

use Throwable;

class DpdTransportException extends DpdException
{
    public static function fromHttpStatus(int $status, string $body): self
    {
        return new self(sprintf('DPD WEB.Service returned HTTP %d: %s', $status, mb_substr($body, 0, 500)));
    }

    public static function invalidJson(string $body): self
    {
        return new self('DPD WEB.Service returned an invalid JSON response: '.mb_substr($body, 0, 500));
    }

    public static function fromThrowable(Throwable $exception): self
    {
        return new self('Unable to communicate with DPD WEB.Service: '.$exception->getMessage(), previous: $exception);
    }
}
