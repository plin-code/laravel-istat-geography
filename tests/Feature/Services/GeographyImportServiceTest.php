<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\GeographyImportService;

test('import service imports all geographical data', function () {
    $service = app(GeographyImportService::class);

    $count = $service->execute();

    expect($count)->toBeGreaterThan(0)
        ->and(Region::count())->toBeGreaterThan(0)
        ->and(Province::count())->toBeGreaterThan(0)
        ->and(Municipality::count())->toBeGreaterThan(0);
});
