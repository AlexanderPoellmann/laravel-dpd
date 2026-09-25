<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class CreateLabelSheet
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(LabelSheetRequest $request): LabelSheetResult
    {
        $response = $this->transport->call(ApiFunction::CreateLabelSheet, $request->toArray());

        return LabelSheetResult::fromResponse($response);
    }
}
