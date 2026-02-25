<?php

namespace OpenSwag\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class OpenSwag extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'openswag';
    }
}
