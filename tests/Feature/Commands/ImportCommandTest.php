<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('import command imports all geographical data', function () {
    $this->artisan('geography:import')
        ->assertSuccessful();

    expect(Region::count())->toBeGreaterThan(0)
        ->and(Province::count())->toBeGreaterThan(0)
        ->and(Municipality::count())->toBeGreaterThan(0);
});
