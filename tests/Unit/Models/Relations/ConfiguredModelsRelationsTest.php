<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Tests\Fixtures\Models\GeoMunicipality;
use PlinCode\IstatGeography\Tests\Fixtures\Models\GeoProvince;
use PlinCode\IstatGeography\Tests\Fixtures\Models\GeoRegion;

beforeEach(function () {
    $this->region = Region::factory()->create();
    $this->province = Province::factory()->create(['region_id' => $this->region->id]);
    $this->municipality = Municipality::factory()->create(['province_id' => $this->province->id]);
});

test('relations return the package models by default', function () {
    expect($this->region->provinces->first())->toBeInstanceOf(Province::class)->not->toBeInstanceOf(GeoProvince::class)
        ->and($this->province->region)->toBeInstanceOf(Region::class)->not->toBeInstanceOf(GeoRegion::class)
        ->and($this->province->municipalities->first())->toBeInstanceOf(Municipality::class)->not->toBeInstanceOf(GeoMunicipality::class)
        ->and($this->municipality->province)->toBeInstanceOf(Province::class)->not->toBeInstanceOf(GeoProvince::class);
});

test('relations return the configured model classes', function () {
    config()->set('istat-geography.models', [
        'region' => GeoRegion::class,
        'province' => GeoProvince::class,
        'municipality' => GeoMunicipality::class,
    ]);

    $region = GeoRegion::findOrFail($this->region->id);
    $province = GeoProvince::findOrFail($this->province->id);
    $municipality = GeoMunicipality::findOrFail($this->municipality->id);

    expect($region->provinces)->toHaveCount(1)->each->toBeInstanceOf(GeoProvince::class)
        ->and($province->region)->toBeInstanceOf(GeoRegion::class)->id->toBe($this->region->id)
        ->and($province->municipalities)->toHaveCount(1)->each->toBeInstanceOf(GeoMunicipality::class)
        ->and($municipality->province)->toBeInstanceOf(GeoProvince::class)->id->toBe($this->province->id);
});

test('custom model class names do not change the relation keys', function () {
    config()->set('istat-geography.models', [
        'region' => GeoRegion::class,
        'province' => GeoProvince::class,
        'municipality' => GeoMunicipality::class,
    ]);

    $region = new GeoRegion;
    $province = new GeoProvince;
    $municipality = new GeoMunicipality;

    expect($region->provinces()->getForeignKeyName())->toBe('region_id')
        ->and($province->region()->getForeignKeyName())->toBe('region_id')
        ->and($province->region()->getOwnerKeyName())->toBe('id')
        ->and($province->municipalities()->getForeignKeyName())->toBe('province_id')
        ->and($municipality->province()->getForeignKeyName())->toBe('province_id')
        ->and($municipality->province()->getOwnerKeyName())->toBe('id');
});
