<?php

use PlinCode\IstatGeography\Models\Geography\Region;

test('can create a region', function () {
    $region = Region::factory()->create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    expect($region)
        ->name->toBe('Piemonte')
        ->istat_code->toBe('01');
});

test('istatFields returns the correct updatable ISTAT fields for Region', function () {
    $fields = Region::istatFields();

    expect($fields)
        ->toBeArray()
        ->toContain('name')
        ->toContain('istat_code')
        ->not->toContain('id')
        ->not->toContain('created_at')
        ->not->toContain('updated_at')
        ->not->toContain('deleted_at');
});
