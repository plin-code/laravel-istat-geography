<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('municipality belongs to province', function () {
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $municipality = Municipality::factory()->create(['province_id' => $province->id]);

    expect($municipality->province)
        ->toBeInstanceOf(Province::class)
        ->id->toBe($province->id);
});
