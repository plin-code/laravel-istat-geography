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

test('istatFields returns the correct updatable ISTAT fields for Municipality', function () {
    $fields = Municipality::istatFields();

    expect($fields)
        ->toBeArray()
        ->toContain('name')
        ->toContain('istat_code')
        ->toContain('province_id')
        ->toContain('bel_code')
        ->not->toContain('id')
        ->not->toContain('created_at')
        ->not->toContain('updated_at')
        ->not->toContain('deleted_at');
});

test('capFields returns the correct updatable CAP fields for Municipality', function () {
    $fields = Municipality::capFields();

    expect($fields)
        ->toBeArray()
        ->toContain('postal_code')
        ->toContain('postal_codes')
        ->not->toContain('bel_code')
        ->not->toContain('name')
        ->not->toContain('istat_code');
});
