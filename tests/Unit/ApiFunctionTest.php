<?php

use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;

it('only retries operations that do not create or mutate shipping state', function () {
    expect(ApiFunction::Status->safeToRetry())->toBeTrue()
        ->and(ApiFunction::SelfBookingList->safeToRetry())->toBeTrue()
        ->and(ApiFunction::ReprintLabel->safeToRetry())->toBeTrue()
        ->and(ApiFunction::CreateLabelSheet->safeToRetry())->toBeTrue()
        ->and(ApiFunction::CreateLabel->safeToRetry())->toBeFalse()
        ->and(ApiFunction::CancelLabel->safeToRetry())->toBeFalse()
        ->and(ApiFunction::PickupOrder->safeToRetry())->toBeFalse()
        ->and(ApiFunction::CollectionRequest->safeToRetry())->toBeFalse()
        ->and(ApiFunction::ImportOrder->safeToRetry())->toBeFalse();
});
