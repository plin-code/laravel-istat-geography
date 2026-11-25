<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Models\Geography\Concerns;

trait HasCustomConnection
{
    /**
     * Retrieves the name of the database connection used for the 'istat-geography' configuration.
     *
     * Falls back to the application's default connection when the package
     * config does not define a 'connection' key (e.g. configs published
     * before this option existed), keeping the feature backward compatible.
     *
     * @return string The name of the connection as defined in the configuration.
     */
    public function getConnectionName(): string
    {
        return config('istat-geography.connection') ?? config('database.default');
    }
}
