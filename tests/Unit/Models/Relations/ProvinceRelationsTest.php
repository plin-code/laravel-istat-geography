<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('province belongs to region', function () {
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);

    expect($province->region)
        ->toBeInstanceOf(Region::class)
        ->id->toBe($region->id);
});

test('province has many municipalities', function () {
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $municipalities = Municipality::factory()
        ->count(5)
        ->create(['province_id' => $province->id]);

    expect($province->municipalities)
        ->toHaveCount(5)
        ->each->toBeInstanceOf(Municipality::class);
});
