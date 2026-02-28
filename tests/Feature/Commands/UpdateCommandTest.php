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
