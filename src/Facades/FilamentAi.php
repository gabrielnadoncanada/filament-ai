<?php

namespace Devlense\FilamentAi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Devlense\FilamentAi\FilamentAi
 */
class FilamentAi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Devlense\FilamentAi\FilamentAi::class;
    }
}
