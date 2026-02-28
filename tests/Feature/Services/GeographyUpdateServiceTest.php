<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\ComparisonResult;
use PlinCode\IstatGeography\Services\EntityComparisonResult;
use PlinCode\IstatGeography\Services\GeographyUpdateService;

function createEmptyEntityResult(): EntityComparisonResult
{
    return new EntityComparisonResult(new: [], modified: [], suppressed: []);
}

test('update service creates new region records', function () {
    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [
                '01' => ['istat_code' => '01', 'name' => 'Piemonte'],
                '02' => ['istat_code' => '02', 'name' => 'Lombardia'],
            ],
            modified: [],
            suppressed: []
        ),
        provinces: createEmptyEntityResult(),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['regions'])->toBe(2)
        ->and($result['added']['provinces'])->toBe(0)
        ->and($result['added']['municipalities'])->toBe(0);

    // Verify records were actually created
    expect(Region::where('istat_code', '01')->exists())->toBeTrue()
        ->and(Region::where('istat_code', '02')->exists())->toBeTrue()
        ->and(Region::where('istat_code', '01')->first()->name)->toBe('Piemonte')
        ->and(Region::where('istat_code', '02')->first()->name)->toBe('Lombardia');
});

test('update service creates new province records with existing region', function () {
    // Create existing region
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: new EntityComparisonResult(
            new: [
                '001' => [
                    'istat_code' => '001',
                    'name' => 'Torino',
                    'code' => '001001',
                    'region_id' => $region->id,
                ],
            ],
            modified: [],
            suppressed: []
        ),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['provinces'])->toBe(1);

    $province = Province::where('istat_code', '001')->first();
    expect($province)->not->toBeNull()
        ->and($province->name)->toBe('Torino')
        ->and($province->region_id)->toBe($region->id);
});

test('update service creates new province records with new region using istat code lookup', function () {
    // Province has region_istat_code because the region is also new
    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [
                '01' => ['istat_code' => '01', 'name' => 'Piemonte'],
            ],
            modified: [],
            suppressed: []
        ),
        provinces: new EntityComparisonResult(
            new: [
                '001' => [
                    'istat_code' => '001',
                    'name' => 'Torino',
                    'code' => '001001',
                    'region_istat_code' => '01', // Uses ISTAT code, not UUID
                ],
            ],
            modified: [],
            suppressed: []
        ),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['regions'])->toBe(1)
        ->and($result['added']['provinces'])->toBe(1);

    $region = Region::where('istat_code', '01')->first();
    $province = Province::where('istat_code', '001')->first();

    expect($province)->not->toBeNull()
        ->and($province->region_id)->toBe($region->id);
});

test('update service creates new municipality records with existing province', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => '001001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: createEmptyEntityResult(),
        municipalities: new EntityComparisonResult(
            new: [
                '001001' => [
                    'istat_code' => '001001',
                    'name' => 'Torino',
                    'province_id' => $province->id,
                ],
            ],
            modified: [],
            suppressed: []
        )
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['municipalities'])->toBe(1);

    $municipality = Municipality::where('istat_code', '001001')->first();
    expect($municipality)->not->toBeNull()
        ->and($municipality->name)->toBe('Torino')
        ->and($municipality->province_id)->toBe($province->id);
});

test('update service creates new municipality with new province using istat code lookup', function () {
    // Create existing region
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: new EntityComparisonResult(
            new: [
                '001' => [
                    'istat_code' => '001',
                    'name' => 'Torino',
                    'code' => '001001',
                    'region_id' => $region->id,
                ],
            ],
            modified: [],
            suppressed: []
        ),
        municipalities: new EntityComparisonResult(
            new: [
                '001001' => [
                    'istat_code' => '001001',
                    'name' => 'Torino City',
                    'province_istat_code' => '001', // Uses ISTAT code, not UUID
                ],
            ],
            modified: [],
            suppressed: []
        )
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['provinces'])->toBe(1)
        ->and($result['added']['municipalities'])->toBe(1);

    $province = Province::where('istat_code', '001')->first();
    $municipality = Municipality::where('istat_code', '001001')->first();

    expect($municipality)->not->toBeNull()
        ->and($municipality->province_id)->toBe($province->id);
});

test('update service creates complete hierarchy of new records', function () {
    // All entities are new - region, province, and municipality
    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [
                '01' => ['istat_code' => '01', 'name' => 'Piemonte'],
            ],
            modified: [],
            suppressed: []
        ),
        provinces: new EntityComparisonResult(
            new: [
                '001' => [
                    'istat_code' => '001',
                    'name' => 'Torino',
                    'code' => '001001',
                    'region_istat_code' => '01',
                ],
            ],
            modified: [],
            suppressed: []
        ),
        municipalities: new EntityComparisonResult(
            new: [
                '001001' => [
                    'istat_code' => '001001',
                    'name' => 'Torino City',
                    'province_istat_code' => '001',
                ],
            ],
            modified: [],
            suppressed: []
        )
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['regions'])->toBe(1)
        ->and($result['added']['provinces'])->toBe(1)
        ->and($result['added']['municipalities'])->toBe(1);

    // Verify the entire hierarchy was created correctly
    $region = Region::where('istat_code', '01')->first();
    $province = Province::where('istat_code', '001')->first();
    $municipality = Municipality::where('istat_code', '001001')->first();

    expect($region)->not->toBeNull()
        ->and($province)->not->toBeNull()
        ->and($municipality)->not->toBeNull()
        ->and($province->region_id)->toBe($region->id)
        ->and($municipality->province_id)->toBe($province->id);
});

test('update service returns correct counts when no new records', function () {
    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: createEmptyEntityResult(),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyNew($comparison);

    expect($result['added']['regions'])->toBe(0)
        ->and($result['added']['provinces'])->toBe(0)
        ->and($result['added']['municipalities'])->toBe(0);
});

test('update service populates all ISTAT fields for new records', function () {
    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [
                '01' => ['istat_code' => '01', 'name' => 'Piemonte'],
            ],
            modified: [],
            suppressed: []
        ),
        provinces: new EntityComparisonResult(
            new: [
                '001' => [
                    'istat_code' => '001',
                    'name' => 'Torino',
                    'code' => 'TO001',
                    'region_istat_code' => '01',
                ],
            ],
            modified: [],
            suppressed: []
        ),
        municipalities: new EntityComparisonResult(
            new: [
                '001001' => [
                    'istat_code' => '001001',
                    'name' => 'Torino City',
                    'province_istat_code' => '001',
                ],
            ],
            modified: [],
            suppressed: []
        )
    );

    $service = app(GeographyUpdateService::class);
    $service->applyNew($comparison);

    // Verify all fields are populated
    $region = Region::where('istat_code', '01')->first();
    expect($region->name)->toBe('Piemonte')
        ->and($region->istat_code)->toBe('01');

    $province = Province::where('istat_code', '001')->first();
    expect($province->name)->toBe('Torino')
        ->and($province->istat_code)->toBe('001')
        ->and($province->code)->toBe('TO001')
        ->and($province->region_id)->toBe($region->id);

    $municipality = Municipality::where('istat_code', '001001')->first();
    expect($municipality->name)->toBe('Torino City')
        ->and($municipality->istat_code)->toBe('001001')
        ->and($municipality->province_id)->toBe($province->id);
});

test('update service updates only ISTAT fields on existing region', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [],
            modified: [
                '01' => [
                    'id' => $region->id,
                    'changes' => [
                        'name' => ['old' => 'Piemonte', 'new' => 'Piemonte Renamed'],
                    ],
                ],
            ],
            suppressed: []
        ),
        provinces: createEmptyEntityResult(),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyModifications($comparison);

    expect($result['modified']['regions'])->toBe(1)
        ->and($result['modified']['provinces'])->toBe(0)
        ->and($result['modified']['municipalities'])->toBe(0);

    // Verify the record was actually updated
    $region->refresh();
    expect($region->name)->toBe('Piemonte Renamed')
        ->and($region->istat_code)->toBe('01');
});

test('update service updates only ISTAT fields on existing province', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => '001001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: new EntityComparisonResult(
            new: [],
            modified: [
                '001' => [
                    'id' => $province->id,
                    'changes' => [
                        'name' => ['old' => 'Torino', 'new' => 'Turin'],
                        'code' => ['old' => '001001', 'new' => '001002'],
                    ],
                ],
            ],
            suppressed: []
        ),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyModifications($comparison);

    expect($result['modified']['provinces'])->toBe(1);

    $province->refresh();
    expect($province->name)->toBe('Turin')
        ->and($province->code)->toBe('001002')
        ->and($province->istat_code)->toBe('001')
        ->and($province->region_id)->toBe($region->id);
});

test('update service updates only ISTAT fields on existing municipality', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => '001001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    $municipality = Municipality::create([
        'name' => 'Torino',
        'istat_code' => '001001',
        'province_id' => $province->id,
    ]);

    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: createEmptyEntityResult(),
        municipalities: new EntityComparisonResult(
            new: [],
            modified: [
                '001001' => [
                    'id' => $municipality->id,
                    'changes' => [
                        'name' => ['old' => 'Torino', 'new' => 'Torino City'],
                    ],
                ],
            ],
            suppressed: []
        )
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyModifications($comparison);

    expect($result['modified']['municipalities'])->toBe(1);

    $municipality->refresh();
    expect($municipality->name)->toBe('Torino City')
        ->and($municipality->istat_code)->toBe('001001')
        ->and($municipality->province_id)->toBe($province->id);
});

test('update service returns correct counts when no modified records', function () {
    $comparison = new ComparisonResult(
        regions: createEmptyEntityResult(),
        provinces: createEmptyEntityResult(),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyModifications($comparison);

    expect($result['modified']['regions'])->toBe(0)
        ->and($result['modified']['provinces'])->toBe(0)
        ->and($result['modified']['municipalities'])->toBe(0);
});

test('update service updates multiple records of same type', function () {
    $region1 = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $region2 = Region::create([
        'name' => 'Lombardia',
        'istat_code' => '02',
    ]);

    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [],
            modified: [
                '01' => [
                    'id' => $region1->id,
                    'changes' => [
                        'name' => ['old' => 'Piemonte', 'new' => 'Piemonte Updated'],
                    ],
                ],
                '02' => [
                    'id' => $region2->id,
                    'changes' => [
                        'name' => ['old' => 'Lombardia', 'new' => 'Lombardia Updated'],
                    ],
                ],
            ],
            suppressed: []
        ),
        provinces: createEmptyEntityResult(),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyModifications($comparison);

    expect($result['modified']['regions'])->toBe(2);

    $region1->refresh();
    $region2->refresh();
    expect($region1->name)->toBe('Piemonte Updated')
        ->and($region2->name)->toBe('Lombardia Updated');
});

test('update service skips records with empty changes', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $originalUpdatedAt = $region->updated_at;

    // Wait a tiny bit to ensure timestamp would change if updated
    usleep(10000);

    $comparison = new ComparisonResult(
        regions: new EntityComparisonResult(
            new: [],
            modified: [
                '01' => [
                    'id' => $region->id,
                    'changes' => [], // Empty changes
                ],
            ],
            suppressed: []
        ),
        provinces: createEmptyEntityResult(),
        municipalities: createEmptyEntityResult()
    );

    $service = app(GeographyUpdateService::class);
    $result = $service->applyModifications($comparison);

    // Should not count as modified since no actual changes
    expect($result['modified']['regions'])->toBe(0);

    $region->refresh();
    expect($region->name)->toBe('Piemonte')
        ->and($region->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});
