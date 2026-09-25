<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum LabelFormat: string
{
    case Pdf = 'PDF';
    case Zpl = 'ZPL';
    case Epl = 'EPL';
}
