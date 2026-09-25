<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum ParcelType: string
{
    case Dpd = 'DPD';
    case Primetime = 'PT';
    case B2c = 'B2C';
    case ParcelShop = '2S';
}
