<?php

namespace PlinCode\IstatGeographical\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \PlinCode\IstatGeographical\IstatGeographical
 */
class IstatGeographical extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PlinCode\IstatGeographical\IstatGeographical::class;
    }
}
