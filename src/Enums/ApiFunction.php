<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum ApiFunction: string
{
    case CreateLabel = 'getLabel';
    case CreateLabelSheet = 'getLabelA4x4';
    case CancelLabel = 'cancelByTracknr';
    case ReprintLabel = 'getReprintByTracknr';
    case SelfBookingList = 'getSblList';
    case PickupOrder = 'abholauftrag';
    case CollectionRequest = 'rueckholauftrag_v2';
    case ImportOrder = 'importAube';
    case Status = 'getStatus';

    public function requiresClient(): bool
    {
        return $this !== self::Status;
    }

    public function safeToRetry(): bool
    {
        return match ($this) {
            self::CreateLabelSheet,
            self::ReprintLabel,
            self::SelfBookingList,
            self::Status => true,
            default => false,
        };
    }
}
