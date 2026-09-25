<?php

use AlexanderPoellmann\LaravelDpd\Exceptions\DpdException;

arch('package exceptions extend the base DPD exception')
    ->expect('AlexanderPoellmann\\LaravelDpd\\Exceptions')
    ->toExtend(DpdException::class)
    ->ignoring(DpdException::class);

arch('data objects are not facades')
    ->expect('AlexanderPoellmann\\LaravelDpd\\Data')
    ->not->toUse('Illuminate\\Support\\Facades');
