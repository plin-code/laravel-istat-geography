<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('can create a municipality', function () {
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);

    $municipality = Municipality::factory()->create([
        'province_id' => $province->id,
        'name' => 'Torino',
        'istat_code' => '001272',
    ]);

    expect($municipality)
        ->name->toBe('Torino')
        ->istat_code->toBe('001272')
        ->province_id->toBe($province->id);
});
