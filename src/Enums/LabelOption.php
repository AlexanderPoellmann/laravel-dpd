<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum LabelOption: string
{
    case Dpi300 = 'dpi300';
    case ReturnWithoutReceipt = 'noquit';
    case DigitalLabel = '2d';
    case AllDpi = 'alldpi';
}
