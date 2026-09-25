<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Exceptions;

use AlexanderPoellmann\LaravelDpd\Data\LabelResult;

/** Shipment creation may have succeeded for some parcels; inspect the result before retrying. */
class DpdShipmentCreationException extends DpdResponseException
{
    public function __construct(public readonly LabelResult $result)
    {
        parent::__construct('DPD did not return a usable label for every requested parcel.');
    }
}
