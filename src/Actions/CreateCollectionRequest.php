<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\CollectionRequest;
use AlexanderPoellmann\LaravelDpd\Data\OperationResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class CreateCollectionRequest
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(CollectionRequest $request): OperationResult
    {
        $response = $this->transport->call(ApiFunction::CollectionRequest, $request->toArray());

        return new OperationResult($response->stringResult(), $response->raw);
    }
}
