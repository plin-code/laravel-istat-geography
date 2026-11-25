<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

function runGeographyMigrations(string $direction = 'up'): void
{
    $migrations = [
        'create_istat_geography_table.php',
        'extend_municipalities_with_postal_codes.php',
        'extend_municipalities_with_coordinates.php',
    ];

    if ($direction === 'down') {
        $migrations = array_reverse($migrations);
    }

    foreach ($migrations as $migration) {
        (require __DIR__.'/../../../database/migrations/'.$migration)->{$direction}();
    }
}

beforeEach(function () {
    config()->set('istat-geography.tables', [
        'regions' => 'geo_regions',
        'provinces' => 'geo_provinces',
        'municipalities' => 'geo_municipalities',
    ]);
});

afterEach(function () {
    // Drop the custom tables and restore the defaults so the package migrations roll back cleanly.
    if (Schema::hasTable('geo_regions')) {
        runGeographyMigrations('down');
    }

    config()->set('istat-geography.tables', [
        'regions' => 'regions',
        'provinces' => 'provinces',
        'municipalities' => 'municipalities',
    ]);
});

test('models use the default table names', function () {
    config()->set('istat-geography.tables', []);

    expect((new Region)->getTable())->toBe('regions')
        ->and((new Province)->getTable())->toBe('provinces')
        ->and((new Municipality)->getTable())->toBe('municipalities');
});

test('models use the configured table names', function () {
    expect((new Region)->getTable())->toBe('geo_regions')
        ->and((new Province)->getTable())->toBe('geo_provinces')
        ->and((new Municipality)->getTable())->toBe('geo_municipalities');
});

test('an explicit table property on a subclass wins over the config', function () {
    $model = new class extends Region
    {
        protected $table = 'italian_regions';
    };

    expect($model->getTable())->toBe('italian_regions');
});

test('migrations create the configured tables', function () {
    expect(Schema::hasTable('geo_regions'))->toBeFalse();

    runGeographyMigrations();

    expect(Schema::hasTable('geo_regions'))->toBeTrue()
        ->and(Schema::hasTable('geo_provinces'))->toBeTrue()
        ->and(Schema::hasTable('geo_municipalities'))->toBeTrue()
        ->and(Schema::hasColumns('geo_municipalities', ['postal_code', 'latitude']))->toBeTrue();
});

test('models read and write the configured tables', function () {
    runGeographyMigrations();

    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $municipality = Municipality::factory()->create(['province_id' => $province->id]);

    expect(Schema::getConnection()->table('geo_regions')->count())->toBe(1)
        ->and(Schema::getConnection()->table('geo_provinces')->count())->toBe(1)
        ->and(Schema::getConnection()->table('geo_municipalities')->count())->toBe(1)
        ->and(Schema::getConnection()->table('regions')->count())->toBe(0)
        ->and(Schema::getConnection()->table('provinces')->count())->toBe(0)
        ->and(Schema::getConnection()->table('municipalities')->count())->toBe(0)
        ->and($region->provinces->pluck('id')->all())->toBe([$province->id])
        ->and($province->region->id)->toBe($region->id)
        ->and($province->municipalities->pluck('id')->all())->toBe([$municipality->id])
        ->and($municipality->province->id)->toBe($province->id);
});

test('foreign keys reference the configured tables', function () {
    runGeographyMigrations();

    $foreignKeys = collect(Schema::getForeignKeys('geo_provinces'))
        ->merge(Schema::getForeignKeys('geo_municipalities'))
        ->pluck('foreign_table')
        ->all();

    expect($foreignKeys)->toContain('geo_regions', 'geo_provinces')
        ->not->toContain('regions', 'provinces');
});

test('rolling back drops the configured tables only', function () {
    runGeographyMigrations();
    runGeographyMigrations('down');

    expect(Schema::hasTable('geo_regions'))->toBeFalse()
        ->and(Schema::hasTable('geo_provinces'))->toBeFalse()
        ->and(Schema::hasTable('geo_municipalities'))->toBeFalse()
        ->and(Schema::hasTable('regions'))->toBeTrue();
});
