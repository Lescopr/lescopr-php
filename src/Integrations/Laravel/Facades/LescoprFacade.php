<?php

declare(strict_types=1);

namespace Lescopr\Integrations\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel Facade for the Lescopr SDK.
 *
 * Allows static-style calls:
 *   Lescopr::sendLog('ERROR', 'Something went wrong');
 */
class LescoprFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'lescopr';
    }
}

