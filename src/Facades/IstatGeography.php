<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static int import()
 *
 * @see \PlinCode\IstatGeography\IstatGeography
 */
class IstatGeography extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'istat-geography';
    }
}
