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

test('istatFields returns the correct updatable ISTAT fields for Province', function () {
    $fields = Province::istatFields();

    expect($fields)
        ->toBeArray()
        ->toContain('name')
        ->toContain('code')
        ->toContain('istat_code')
        ->toContain('region_id')
        ->not->toContain('id')
        ->not->toContain('created_at')
        ->not->toContain('updated_at')
        ->not->toContain('deleted_at');
});
