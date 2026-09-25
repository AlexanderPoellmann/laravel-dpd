<?php

namespace AlexanderPoellmann\LaravelDpd\Contracts;

use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

interface DpdTransport
{
    /** @param array<string, mixed> $data */
    public function call(ApiFunction $function, array $data = []): DpdResponse;
}
