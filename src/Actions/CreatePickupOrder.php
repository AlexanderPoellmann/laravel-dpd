<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\OperationResult;
use AlexanderPoellmann\LaravelDpd\Data\PickupOrderRequest;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class CreatePickupOrder
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(PickupOrderRequest $request): OperationResult
    {
        $response = $this->transport->call(ApiFunction::PickupOrder, $request->toArray());

        return new OperationResult($response->stringResult(), $response->raw);
    }
}
