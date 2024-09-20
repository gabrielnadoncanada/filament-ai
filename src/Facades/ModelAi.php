<?php

namespace Devlense\ModelAi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Devlense\ModelAi\ModelAi
 */
class ModelAi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Devlense\ModelAi\ModelAi::class;
    }
}
