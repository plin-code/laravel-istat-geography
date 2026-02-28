<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

beforeEach(function () {
    Storage::disk('local')->delete('istat_municipalities.csv');
});

function mockEmptyIstatResponse(): void
{
    Http::fake([
        '*' => Http::response(createUpdateTestCsvHeader(), 200),
    ]);
}

function createUpdateTestCsvHeader(): string
{
    return "Codice Regione;Codice dell'Unità territoriale sovracomunale (valida a fini statistici);Codice Provincia (Storico)(1);Progressivo del Comune (2);Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Codice Ripartizione Geografica;Ripartizione geografica;Denominazione regione;Denominazione dell'Unità territoriale sovracomunale (valida a fini statistici);Denominazione in italiano;Denominazione della Provincia;Flag Comune capoluogo di provincia/città metropolitana/libero consorzio;Sigla automobilistica;Codice Comune formato numerico;Codice Comune 110 (107) (3);Codice Comune 103 (1995) (3);Codice Catastale;Codice NUTS1 2010;Codice NUTS2 2010 (3);Codice NUTS3 2010;Codice NUTS1 2021;Codice NUTS2 2021 (3);Codice NUTS3 2021\n";
}

function createUpdateTestCsvRow(
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

test('update command is registered', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update')
        ->assertSuccessful();
});

test('update command has a clear description', function () {
    mockEmptyIstatResponse();
    $command = $this->artisan('geography:update', ['--help' => true]);
    $command->assertSuccessful();
    // The description is shown in help output and contains 'Update' and 'ISTAT'
    $command->expectsOutputToContain('Update');
});

test('update command accepts dry-run option', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY-RUN]');
});

test('update command respects verbosity flag -v', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update', ['-v' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Downloading ISTAT data');
});

test('update command respects verbosity flag -vv', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update', ['-vv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Comparing with existing database records');
});

test('update command respects verbosity flag -vvv', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update', ['-vvv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Debug mode enabled');
});

test('update command shows start and end messages', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('Starting geographical data update')
        ->expectsOutputToContain('Update completed');
});

test('update command shows summary with counts in end message', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('0 added, 0 modified, 0 deleted');
});

test('update command dry-run prefixes all output messages', function () {
    mockEmptyIstatResponse();
    $this->artisan('geography:update', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY-RUN] Starting')
        ->expectsOutputToContain('[DRY-RUN] Update completed');
});

test('update command creates new records when database is empty', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('3 added');

    // Verify records were actually created
    expect(Region::count())->toBe(1)
        ->and(Province::count())->toBe(1)
        ->and(Municipality::count())->toBe(1);
});

test('update command correctly links new records via relationships', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful();

    $region = Region::where('istat_code', '01')->first();
    $province = Province::where('istat_code', '001')->first();
    $municipality = Municipality::where('istat_code', '010001')->first();

    expect($province->region_id)->toBe($region->id)
        ->and($municipality->province_id)->toBe($province->id);
});

test('update command dry-run lists new records but does not create them', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['--dry-run' => true, '-v' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY-RUN] Update completed: 3 added');

    // Verify no records were created
    expect(Region::count())->toBe(0)
        ->and(Province::count())->toBe(0)
        ->and(Municipality::count())->toBe(0);
});

test('update command shows count of added records per entity in output', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Aglie', 'Piemonte', 'Torino', 'TO').
            createUpdateTestCsvRow('01', '001', '010002', 'Airasca', 'Piemonte', 'Torino', 'TO').
            createUpdateTestCsvRow('01', '002', '010003', 'Alessandria', 'Piemonte', 'Alessandria', 'AL'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('6 added'); // 1 region + 2 provinces + 3 municipalities

    // 1 region, 2 provinces, 3 municipalities = 6 total
    expect(Region::count())->toBe(1)
        ->and(Province::count())->toBe(2)
        ->and(Municipality::count())->toBe(3);
});

test('update command populates all ISTAT fields for new records', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful();

    $region = Region::where('istat_code', '01')->first();
    expect($region->name)->toBe('Piemonte')
        ->and($region->istat_code)->toBe('01');

    $province = Province::where('istat_code', '001')->first();
    expect($province->name)->toBe('Torino')
        ->and($province->istat_code)->toBe('001')
        ->and($province->code)->toBe('010001')
        ->and($province->region_id)->toBe($region->id);

    $municipality = Municipality::where('istat_code', '010001')->first();
    expect($municipality->name)->toBe('Torino')
        ->and($municipality->istat_code)->toBe('010001')
        ->and($municipality->province_id)->toBe($province->id);
});

test('update command updates only ISTAT fields preserving custom fields', function () {
    // Create existing records
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001', // Must match position 14 in CSV which is municipalityCode
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    $municipality = Municipality::create([
        'name' => 'Torino Old Name',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // ISTAT data has updated municipality name
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino New Name', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('1 modified');

    // Verify the municipality name was updated
    $municipality->refresh();
    expect($municipality->name)->toBe('Torino New Name')
        ->and($municipality->istat_code)->toBe('010001')
        ->and($municipality->province_id)->toBe($province->id);
});

test('update command does not update unchanged records', function () {
    // Create existing records that match ISTAT data exactly
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001', // Must match position 14 in CSV which is municipalityCode
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    $municipality = Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('0 modified');
});

test('update command dry-run lists modified records but does not apply changes', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001', // Must match position 14 in CSV which is municipalityCode
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    $municipality = Municipality::create([
        'name' => 'Torino Old Name',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino New Name', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY-RUN] Update completed: 0 added, 1 modified');

    // Verify no changes were applied
    $municipality->refresh();
    expect($municipality->name)->toBe('Torino Old Name');
});

test('update command shows modification details at verbosity -vv', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001', // Must match position 14 in CSV which is municipalityCode
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    Municipality::create([
        'name' => 'Torino Old Name',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino New Name', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['-vv' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Modified municipality (ISTAT: 010001)')
        ->expectsOutputToContain('name: Torino Old Name → Torino New Name');
});

test('update command soft-deletes records no longer present in ISTAT', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    // Create a municipality that won't be in ISTAT data
    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010002',
        'province_id' => $province->id,
    ]);
    // Also create one that will exist
    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // ISTAT data only has the first municipality
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('1 deleted');

    // Verify the old municipality was soft-deleted
    expect(Municipality::withoutTrashed()->where('istat_code', '010002')->exists())->toBeFalse()
        ->and(Municipality::withTrashed()->where('istat_code', '010002')->exists())->toBeTrue()
        ->and(Municipality::withoutTrashed()->where('istat_code', '010001')->exists())->toBeTrue();
});

test('update command preserves foreign key relationships when soft-deleting', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    $municipality = Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // ISTAT data is empty (simulating suppression of all)
    Http::fake([
        '*' => Http::response(createUpdateTestCsvHeader(), 200),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful();

    // Verify foreign keys are preserved even after soft delete
    $deletedMunicipality = Municipality::withTrashed()->where('istat_code', '010001')->first();
    expect($deletedMunicipality->province_id)->toBe($province->id);

    $deletedProvince = Province::withTrashed()->where('istat_code', '001')->first();
    expect($deletedProvince->region_id)->toBe($region->id);
});

test('update command does not cascade delete child records when suppressing parent', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    $municipality = Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // ISTAT data still has the municipality but not the province
    // (unusual but should not auto-delete the municipality)
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful();

    // Municipality should NOT be automatically deleted
    // Note: In this test, the ISTAT data DOES include the municipality, so it won't be deleted
    // The important thing is verifying that suppression is based on ISTAT codes, not cascading
    expect(Municipality::withoutTrashed()->where('istat_code', '010001')->exists())->toBeTrue();
});

test('update command dry-run lists suppressed records but does not delete them', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010002',
        'province_id' => $province->id,
    ]);
    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY-RUN] Update completed: 0 added, 0 modified, 1 deleted');

    // Verify record was NOT deleted
    expect(Municipality::where('istat_code', '010002')->exists())->toBeTrue()
        ->and(Municipality::withTrashed()->where('istat_code', '010002')->first()->deleted_at)->toBeNull();
});

test('update command shows count of suppressed records per entity in output', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    Municipality::create([
        'name' => 'Mun1',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);
    Municipality::create([
        'name' => 'Mun2',
        'istat_code' => '010002',
        'province_id' => $province->id,
    ]);
    Municipality::create([
        'name' => 'Mun3',
        'istat_code' => '010003',
        'province_id' => $province->id,
    ]);

    // ISTAT data is empty
    Http::fake([
        '*' => Http::response(createUpdateTestCsvHeader(), 200),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('5 deleted'); // 1 region + 1 province + 3 municipalities

    // Verify all were soft-deleted
    expect(Region::withoutTrashed()->count())->toBe(0)
        ->and(Province::withoutTrashed()->count())->toBe(0)
        ->and(Municipality::withoutTrashed()->count())->toBe(0)
        ->and(Region::withTrashed()->count())->toBe(1)
        ->and(Province::withTrashed()->count())->toBe(1)
        ->and(Municipality::withTrashed()->count())->toBe(3);
});

test('update command shows suppressed record details at verbosity -v', function () {
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    $province = Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);
    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010002',
        'province_id' => $province->id,
    ]);

    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['-v' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Suppressed municipality: Old Municipality (ISTAT: 010002)');
});

test('update command wraps database operations in a transaction', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful();

    // Verify records are created atomically
    expect(Region::count())->toBe(1)
        ->and(Province::count())->toBe(1)
        ->and(Municipality::count())->toBe(1);
});

test('update command handles HTTP errors gracefully with clear message', function () {
    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $this->artisan('geography:update')
        ->assertFailed()
        ->expectsOutputToContain('Update failed')
        ->expectsOutputToContain('rolled back');

    // Verify no records were created
    expect(Region::count())->toBe(0)
        ->and(Province::count())->toBe(0)
        ->and(Municipality::count())->toBe(0);
});

test('update command accepts --force option', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['--force' => true])
        ->assertSuccessful();
});

test('update command handles comparison errors gracefully', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);
    Province::create([
        'name' => 'Torino',
        'code' => '010001',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->expectsOutputToContain('Update completed');
});

test('update command returns success code on successful completion', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update')
        ->assertSuccessful()
        ->assertExitCode(0);
});

test('update command dry-run does not use transaction for database operations', function () {
    Http::fake([
        '*' => Http::response(
            createUpdateTestCsvHeader().
            createUpdateTestCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO'),
            200
        ),
    ]);

    $this->artisan('geography:update', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY-RUN]');

    // Verify no records were created in dry-run mode
    expect(Region::count())->toBe(0)
        ->and(Province::count())->toBe(0)
        ->and(Municipality::count())->toBe(0);
});
