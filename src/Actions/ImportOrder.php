<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\OperationResult;
use AlexanderPoellmann\LaravelDpd\Data\OrderImportRequest;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class ImportOrder
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(OrderImportRequest $request): OperationResult
    {
        $response = $this->transport->call(ApiFunction::ImportOrder, $request->toArray());

        return new OperationResult($response->stringResult(), $response->raw);
    }
}
