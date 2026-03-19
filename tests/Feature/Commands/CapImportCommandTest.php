<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;

beforeEach(function () {
    Storage::disk('local')->delete('cap_dataset.json');
    Storage::disk('local')->delete('istat_municipalities.csv');
});

function mockIstatCsv(string $belCode = 'A074'): string
{
    $header = 'Codice Regione;Col1;Codice Provincia (Storico);Col3;Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Denominazione italiano;Denominazione altra lingua;Col8;Col9;Denominazione Regione;Denominazione Provincia;Col12;Col13;Sigla automobilistica;Col15;Col16;Col17;Col18;Codice Catastale del comune;Col20;Col21;Col22;Col23';
    $row = "01;001;001;001;010001;AGLIÈ;Agliè;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010001;010001;010001;010001;{$belCode};ITC;ITC1;ITC11;ITC";

    return $header."\n".$row;
}

test('import command with --cap flag imports both istat and cap data', function () {
    Http::fake([
        config('istat-geography.import.csv_url') => Http::response(mockIstatCsv('A074'), 200),
        config('istat-geography.cap.properties_url') => Http::response([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'codice_bel' => 'A074',
                        'cap' => '10011',
                        'comune_cap' => '10011',
                    ],
                    'geometry' => null,
                ],
            ],
        ]),
    ]);

    $this->artisan('geography:import --cap')
        ->expectsOutput('Starting geographical data import...')
        ->expectsOutput('Import completed successfully! Imported 1 municipalities.')
        ->expectsOutput('Importing postal codes (CAP)...')
        ->expectsOutput('CAP import completed! Updated 1 municipalities.')
        ->assertSuccessful();

    $municipality = Municipality::where('istat_code', '010001')->first();

    expect($municipality)
        ->bel_code->toBe('A074')
        ->postal_code->toBe('10011');
});

test('import command with --cap-only flag imports only cap data', function () {
    $municipality = Municipality::factory()->create([
        'bel_code' => 'D704',
    ]);

    Http::fake([
        config('istat-geography.cap.properties_url') => Http::response([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'codice_bel' => 'D704',
                        'cap' => '47122',
                        'comune_cap' => '47121-47122',
                    ],
                    'geometry' => null,
                ],
            ],
        ]),
    ]);

    $this->artisan('geography:import --cap-only')
        ->expectsOutput('Importing postal codes (CAP)...')
        ->expectsOutput('CAP import completed! Updated 1 municipalities.')
        ->assertSuccessful();

    expect($municipality->fresh())
        ->postal_code->toBe('47122')
        ->postal_codes->toBe('47121-47122');
});

test('import command without --cap flag does not import cap data', function () {
    Http::fake([
        config('istat-geography.import.csv_url') => Http::response(mockIstatCsv('A074'), 200),
    ]);

    $this->artisan('geography:import')
        ->expectsOutput('Starting geographical data import...')
        ->expectsOutput('Import completed successfully! Imported 1 municipalities.')
        ->assertSuccessful();

    $municipality = Municipality::where('istat_code', '010001')->first();

    expect($municipality)
        ->bel_code->toBe('A074')
        ->postal_code->toBeNull();
});
