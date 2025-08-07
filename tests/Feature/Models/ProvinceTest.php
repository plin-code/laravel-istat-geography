<?php

use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('can create a province', function () {
    $region = Region::factory()->create();

    $province = Province::factory()->create([
        'region_id' => $region->id,
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '001',
    ]);

    expect($province)
        ->name->toBe('Torino')
        ->code->toBe('TO')
        ->istat_code->toBe('001')
        ->region_id->toBe($region->id);
});
