<?php

declare(strict_types=1);

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('Region getConnectionName returns value from config', function () {
    config()->set('istat-geography.connection', 'custom_connection');

    expect((new Region)->getConnectionName())->toBe('custom_connection');
});

test('Province getConnectionName returns value from config', function () {
    config()->set('istat-geography.connection', 'custom_connection');

    expect((new Province)->getConnectionName())->toBe('custom_connection');
});

test('Municipality getConnectionName returns value from config', function () {
    config()->set('istat-geography.connection', 'custom_connection');

    expect((new Municipality)->getConnectionName())->toBe('custom_connection');
});

test('getConnectionName falls back to the default connection when not configured', function () {
    config()->set('istat-geography.connection', null);

    expect((new Region)->getConnectionName())->toBe(config('database.default'));
});

test('models reflect connection change at runtime', function () {
    config()->set('istat-geography.connection', 'connection_a');
    expect((new Region)->getConnectionName())->toBe('connection_a');

    config()->set('istat-geography.connection', 'connection_b');
    expect((new Region)->getConnectionName())->toBe('connection_b');
});
