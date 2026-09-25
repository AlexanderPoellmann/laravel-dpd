<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum IdentityCheckType: string
{
    case Identity = 'id';
    case Age16 = 'id16';
    case Age18 = 'id18';
}
