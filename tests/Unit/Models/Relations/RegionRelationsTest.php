<?php

use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('region has many provinces', function () {
    $region = Region::factory()->create();
    $provinces = Province::factory()->count(3)->create(['region_id' => $region->id]);

    expect($region->provinces)
        ->toHaveCount(3)
        ->each->toBeInstanceOf(Province::class);
});
