<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

beforeEach(function () {
    Storage::disk('local')->delete('coordinates_dataset.json');
    Storage::disk('local')->delete('istat_municipalities.csv');
});

function coordinatesIstatCsv(string $municipalityName = 'Aglie'): string
{
    $header = 'Codice Regione;Col1;Codice Provincia (Storico);Col3;Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Denominazione italiano;Denominazione altra lingua;Col8;Col9;Denominazione Regione;Denominazione Provincia;Col12;Col13;Sigla automobilistica;Col15;Col16;Col17;Col18;Codice Catastale del comune;Col20;Col21;Col22;Col23';
    $row = "01;001;001;001;001001;{$municipalityName};{$municipalityName};;1;Nord-ovest;Piemonte;Torino;0;0;TO;001001;001001;001001;001001;A074;ITC;ITC1;ITC11;ITC";

    return $header."\n".$row;
}

function coordinatesDatasetResponse(): array
{
    return [
        'meta' => ['license' => 'CC BY 4.0'],
        'municipalities' => [
            ['istat_code' => '001001', 'bel_code' => 'A074', 'latitude' => 45.3658964, 'longitude' => 7.7758893],
        ],
    ];
}

test('import command with --coordinates imports istat data and coordinates', function () {
    Http::fake([
        config('istat-geography.import.csv_url') => Http::response(coordinatesIstatCsv(), 200),
        config('istat-geography.coordinates.dataset_url') => Http::response(coordinatesDatasetResponse()),
    ]);

    $this->artisan('geography:import --coordinates')
        ->expectsOutput('Starting geographical data import...')
        ->expectsOutput('Import completed successfully! Imported 1 municipalities.')
        ->expectsOutput('Importing municipality coordinates...')
        ->expectsOutput('Coordinates import completed! Updated 1 municipalities.')
        ->assertSuccessful();

    expect(Municipality::where('istat_code', '001001')->first())
        ->latitude->toBe(45.3658964)
        ->longitude->toBe(7.7758893);
});

test('import command with --coordinates-only and --coordinates-file imports only coordinates', function () {
    $rome = Municipality::factory()->create(['istat_code' => '058091']);

    Http::fake();

    $this->artisan('geography:import --coordinates-only --coordinates-file='.__DIR__.'/../../Fixtures/municipality_coordinates_dataset.json')
        ->doesntExpectOutput('Starting geographical data import...')
        ->expectsOutput('Coordinates import completed! Updated 1 municipalities.')
        ->assertSuccessful();

    expect($rome->fresh())
        ->latitude->toBe(41.8853588)
        ->longitude->toBe(12.4607809);

    Http::assertNothingSent();
});

test('import command fails when the coordinates file does not exist', function () {
    $this->artisan('geography:import --coordinates-only --coordinates-file=/missing/coordinates.json')
        ->expectsOutput('Error during import: Coordinates file not found: /missing/coordinates.json')
        ->assertFailed();
});

test('import command imports coordinates when enabled in config', function () {
    config()->set('istat-geography.coordinates.enabled', true);

    Http::fake([
        config('istat-geography.import.csv_url') => Http::response(coordinatesIstatCsv(), 200),
        config('istat-geography.coordinates.dataset_url') => Http::response(coordinatesDatasetResponse()),
    ]);

    $this->artisan('geography:import')
        ->expectsOutput('Coordinates import completed! Updated 1 municipalities.')
        ->assertSuccessful();

    expect(Municipality::where('istat_code', '001001')->first()->latitude)->toBe(45.3658964);
});

test('import command does not import coordinates when disabled', function () {
    config()->set('istat-geography.coordinates.enabled', false);

    Http::fake([
        config('istat-geography.import.csv_url') => Http::response(coordinatesIstatCsv(), 200),
    ]);

    $this->artisan('geography:import')
        ->doesntExpectOutput('Importing municipality coordinates...')
        ->assertSuccessful();

    expect(Municipality::where('istat_code', '001001')->first()->latitude)->toBeNull();

    Http::assertNotSent(fn (Request $request) => $request->url() === config('istat-geography.coordinates.dataset_url'));
});

test('update command does not overwrite coordinates', function () {
    $region = Region::create(['name' => 'Piemonte', 'istat_code' => '01']);
    $province = Province::create(['name' => 'Torino', 'code' => 'TO', 'istat_code' => '001', 'region_id' => $region->id]);
    $municipality = Municipality::factory()
        ->withCoordinates(45.3658964, 7.7758893)
        ->create(['name' => 'Aglie', 'istat_code' => '001001', 'bel_code' => 'A074', 'province_id' => $province->id]);

    Http::fake([
        '*' => Http::response(coordinatesIstatCsv('Aglie Nuovo'), 200),
    ]);

    $this->artisan('geography:update')->assertSuccessful();

    expect($municipality->fresh())
        ->name->toBe('Aglie Nuovo')
        ->latitude->toBe(45.3658964)
        ->longitude->toBe(7.7758893);
});
