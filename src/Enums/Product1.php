<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum Product1: string
{
    case SmallParcel = 'KP';
    case NormalParcel = 'NP';
    case Return = 'RETURN';
    case ShopToShop = 'S2S';
    case Primetime10 = 'AM1';
    case Primetime12 = 'AM2';
    case Primetime17 = 'PM2';
    case PrimetimeWindow = 'TFR';
    case Saturday10 = 'AM1-6';
    case Saturday12 = 'AM2-6';

    public function isPrimetime(): bool
    {
        return in_array($this, [self::Primetime10, self::Primetime12, self::Primetime17, self::PrimetimeWindow, self::Saturday10, self::Saturday12], true);
    }
}
