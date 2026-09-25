<?php

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\LabelResult;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

final readonly class CreateLabel
{
    public function __construct(private DpdTransport $transport) {}

    public function handle(LabelRequest $request): LabelResult
    {
        $response = $this->transport->call(ApiFunction::CreateLabel, $request->toArray());

        return new LabelResult(
            array_map(Label::fromArray(...), $response->listResult()),
            $response->raw,
        );
    }
}
