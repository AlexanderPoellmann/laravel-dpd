<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum SelfBookingMode: int
{
    case SelfBookingList = 1;
    case DailyClosing = 2;
}
