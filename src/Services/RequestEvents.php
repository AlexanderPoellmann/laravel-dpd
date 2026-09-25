<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Services;

use AlexanderPoellmann\LaravelDpd\Enums\DpdErrorCode;
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestFailed;
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestStarted;
use AlexanderPoellmann\LaravelDpd\Events\DpdRequestSucceeded;
use Illuminate\Support\Facades\Event;

/** @internal Records one logical request, including any HTTP retries. */
final readonly class RequestEvents
{
    private bool $enabled;

    private float $startedAt;

    public function __construct(private string $operation)
    {
        $this->enabled = (bool) config('dpd.events.enabled', false);
        $this->startedAt = hrtime(true);

        if ($this->enabled) {
            Event::dispatch(new DpdRequestStarted($this->operation));
        }
    }

    public function succeeded(int $httpStatus): void
    {
        if ($this->enabled) {
            Event::dispatch(new DpdRequestSucceeded($this->operation, $this->duration(), $httpStatus));
        }
    }

    public function failed(?int $httpStatus, ?string $errorCode = null): void
    {
        if ($this->enabled) {
            // Never forward error messages or unchecked strings as event metadata.
            $safeCode = $errorCode !== null && (DpdErrorCode::tryFrom($errorCode) !== null
                || preg_match('/\A[A-Z]{2}[0-9]{2}\z/', $errorCode) === 1) ? $errorCode : null;

            Event::dispatch(new DpdRequestFailed($this->operation, $this->duration(), $httpStatus, $safeCode));
        }
    }

    private function duration(): float
    {
        return (hrtime(true) - $this->startedAt) / 1_000_000;
    }
}
