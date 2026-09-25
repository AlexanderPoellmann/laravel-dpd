<?php

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Facades\Dpd;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use AlexanderPoellmann\LaravelDpd\Services\RestTransport;

it('registers the DPD client and transport as singletons', function () {
    expect(app(DpdTransport::class))->toBeInstanceOf(RestTransport::class)
        ->toBe(app(DpdTransport::class))
        ->and(app(LaravelDpd::class))->toBe(app(LaravelDpd::class))
        ->and(Dpd::getFacadeRoot())->toBe(app(LaravelDpd::class));
});

it('allows applications to replace the transport through the container', function () {
    $this->mock(DpdTransport::class)
        ->shouldReceive('call')
        ->once()
        ->with(ApiFunction::Status)
        ->andReturn(DpdResponse::fromArray(['status' => 'ok', 'result' => []]));

    expect(Dpd::status())->toBe([]);
});
