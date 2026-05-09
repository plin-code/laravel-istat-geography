<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Models\Geography\Concerns;

trait CustomizesConnection
{
    /**
     * Retrieves the name of the database connection used for the 'istat-geography' configuration.
     *
     * @return string The name of the connection as defined in the configuration.
     */
    public function getConnectionName(): string
    {
        return config('istat-geography.connection');
    }
}