<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

test('import command imports all geographical data', function () {
    $csvHeader = 'Codice Regione;Col1;Codice Provincia (Storico);Col3;Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Denominazione italiano;Denominazione altra lingua;Col8;Col9;Denominazione Regione;Denominazione Provincia;Col12;Col13;Sigla automobilistica;Col15;Col16;Col17;Col18;Codice Catastale del comune;Col20;Col21;Col22;Col23';
    $csvRow = '01;001;001;001;010001;AGLIÈ;Agliè;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010001;010001;010001;010001;A074;ITC;ITC1;ITC11;ITC';

    Http::fake(['*' => Http::response($csvHeader."\n".$csvRow, 200)]);
    Storage::disk('local')->delete('istat_municipalities.csv');

    $this->artisan('geography:import')
        ->assertSuccessful();

    expect(Region::count())->toBeGreaterThan(0)
        ->and(Province::count())->toBeGreaterThan(0)
        ->and(Municipality::count())->toBeGreaterThan(0);
});
