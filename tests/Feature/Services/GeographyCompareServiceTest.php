<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\GeographyCompareService;

beforeEach(function () {
    Storage::disk('local')->delete('istat_municipalities.csv');
});

function mockIstatResponse(string $csvContent): void
{
    Http::fake([
        '*' => Http::response($csvContent, 200),
    ]);
}

function createCsvHeader(): string
{
    return "Codice Regione;Codice dell'Unità territoriale sovracomunale (valida a fini statistici);Codice Provincia (Storico)(1);Progressivo del Comune (2);Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Codice Ripartizione Geografica;Ripartizione geografica;Denominazione regione;Denominazione dell'Unità territoriale sovracomunale (valida a fini statistici);Denominazione in italiano;Denominazione della Provincia;Flag Comune capoluogo di provincia/città metropolitana/libero consorzio;Sigla automobilistica;Codice Comune formato numerico;Codice Comune 110 (107) (3);Codice Comune 103 (1995) (3);Codice Catastale;Codice NUTS1 2010;Codice NUTS2 2010 (3);Codice NUTS3 2010;Codice NUTS1 2021;Codice NUTS2 2021 (3);Codice NUTS3 2021\n";
}

function createCsvRow(
    string $regionCode,
    string $provinceCode,
    string $municipalityCode,
    string $municipalityName,
    string $regionName,
    string $provinceName,
    string $provinceAbbr
): string {
    return "{$regionCode};001;{$provinceCode};001;{$municipalityCode};{$municipalityName};1;Nord-ovest;{$regionName};{$provinceName};{$regionName};{$provinceName};0;{$provinceAbbr};{$municipalityCode};{$municipalityCode};{$municipalityCode};A074;ITC1;ITC1;ITC11;ITC1;ITC1;ITC11\n";
}

test('compare service identifies new records when database is empty', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->regions->countNew())->toBe(1)
        ->and($result->regions->countModified())->toBe(0)
        ->and($result->regions->countSuppressed())->toBe(0)
        ->and($result->provinces->countNew())->toBe(1)
        ->and($result->provinces->countModified())->toBe(0)
        ->and($result->provinces->countSuppressed())->toBe(0)
        ->and($result->municipalities->countNew())->toBe(1)
        ->and($result->municipalities->countModified())->toBe(0)
        ->and($result->municipalities->countSuppressed())->toBe(0);
});

test('compare service identifies modified records when data differs', function () {
    // Create existing data in DB
    $region = Region::create([
        'name' => 'Old Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Old Torino',
        'code' => 'TO',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // Mock ISTAT response with different names
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'New Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // Region name changed
    expect($result->regions->countNew())->toBe(0)
        ->and($result->regions->countModified())->toBe(1)
        ->and($result->regions->modified)->toHaveKey('01')
        ->and($result->regions->modified['01']['changes'])->toHaveKey('name')
        ->and($result->regions->modified['01']['changes']['name']['old'])->toBe('Old Piemonte')
        ->and($result->regions->modified['01']['changes']['name']['new'])->toBe('Piemonte');

    // Province name changed
    expect($result->provinces->countModified())->toBe(1)
        ->and($result->provinces->modified['001']['changes']['name']['old'])->toBe('Old Torino')
        ->and($result->provinces->modified['001']['changes']['name']['new'])->toBe('Torino');

    // Municipality name changed
    expect($result->municipalities->countModified())->toBe(1)
        ->and($result->municipalities->modified['010001']['changes']['name']['old'])->toBe('Old Municipality')
        ->and($result->municipalities->modified['010001']['changes']['name']['new'])->toBe('New Torino');
});

test('compare service identifies suppressed records when absent from ISTAT', function () {
    // Create existing data in DB that won't be in ISTAT
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    // Create a municipality that won't be in ISTAT
    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010099',
        'province_id' => $province->id,
    ]);

    // Also create one that will be in ISTAT
    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // Mock ISTAT response without the old municipality
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->municipalities->countSuppressed())->toBe(1)
        ->and($result->municipalities->suppressed)->toHaveKey('010099')
        ->and($result->municipalities->suppressed['010099']['name'])->toBe('Old Municipality');
});

test('compare service uses ISTAT codes as unique keys', function () {
    // Create a record with same name but different ISTAT code
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '99', // Different code
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '999', // Different code
        'region_id' => $region->id,
    ]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // Should identify as new (by code) and suppressed (old code)
    expect($result->regions->countNew())->toBe(1)
        ->and($result->regions->countSuppressed())->toBe(1)
        ->and($result->regions->new)->toHaveKey('01')
        ->and($result->regions->suppressed)->toHaveKey('99');
});

test('compare service ignores soft-deleted records', function () {
    // Create and soft delete a record
    $region = Region::create([
        'name' => 'Deleted Region',
        'istat_code' => '99',
    ]);
    $region->delete(); // Soft delete

    // Create existing region in DB that matches ISTAT
    Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // The soft-deleted record (99) should not appear as suppressed
    // Only non-deleted records are considered
    expect($result->regions->countSuppressed())->toBe(0)
        ->and($result->regions->suppressed)->not->toHaveKey('99');
});

test('compare service returns no changes when data matches', function () {
    // Create matching data in DB - note that the import service uses $values[14] (Codice Comune formato numerico)
    // as the province code, not the abbreviation at $values[13]
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    // The province code in DB should match what ISTAT data provides at index 14
    // which is the municipality numeric code (010001) based on how GeographyImportService works
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001', // This matches $values[14] from CSV
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // Verify no changes for each entity type
    expect($result->regions->countNew())->toBe(0)
        ->and($result->regions->countModified())->toBe(0)
        ->and($result->regions->countSuppressed())->toBe(0)
        ->and($result->provinces->countNew())->toBe(0)
        ->and($result->provinces->countModified())->toBe(0)
        ->and($result->provinces->countSuppressed())->toBe(0)
        ->and($result->municipalities->countNew())->toBe(0)
        ->and($result->municipalities->countModified())->toBe(0)
        ->and($result->municipalities->countSuppressed())->toBe(0)
        ->and($result->hasChanges())->toBeFalse();
});

test('compare service handles multiple records correctly', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Aglie', 'Piemonte', 'Torino', 'TO').
        createCsvRow('01', '001', '010002', 'Airasca', 'Piemonte', 'Torino', 'TO').
        createCsvRow('01', '002', '010003', 'Alessandria', 'Piemonte', 'Alessandria', 'AL')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->regions->countNew())->toBe(1) // One region (Piemonte)
        ->and($result->provinces->countNew())->toBe(2) // Two provinces (Torino, Alessandria)
        ->and($result->municipalities->countNew())->toBe(3); // Three municipalities
});

test('entity comparison result correctly reports has changes', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->hasChanges())->toBeTrue()
        ->and($result->regions->hasChanges())->toBeTrue()
        ->and($result->provinces->hasChanges())->toBeTrue()
        ->and($result->municipalities->hasChanges())->toBeTrue();
});
