<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\ServiceState;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class GetStatus
{
    public function __construct(private DpdTransport $transport) {}

    /** @return list<ServiceState> */
    public function handle(): array
    {
        $response = $this->transport->call(ApiFunction::Status);

        return array_map(ServiceState::fromArray(...), $response->listResult());
    }
}
