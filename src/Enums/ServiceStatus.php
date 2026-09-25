<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum ServiceStatus: string
{
    case Operational = 'Im Betrieb';
    case MinorIssues = 'Geringfügige Probleme';
    case Malfunction = 'Störung';
    case SystemFailure = 'Systemausfall';
}
