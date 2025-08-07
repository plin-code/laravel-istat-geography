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
