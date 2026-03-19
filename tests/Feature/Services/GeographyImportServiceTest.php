<?php

use Illuminate\Support\Facades\Http;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\GeographyImportService;

test('mocked import service imports all geographical data', function () {
    // Mock CSV - colonna 19 contiene il Codice Catastale nel formato reale
    // Header: 0=Regione, 2=Provincia, 4=CodiceComune, 5=Nome, 10=NomeRegione, 11=NomeProvincia, 14=Sigla, 19=CodiceCatastale
    $header = 'Codice Regione;Col1;Codice Provincia (Storico);Col3;Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Denominazione italiano;Denominazione altra lingua;Col8;Col9;Denominazione Regione;Denominazione Provincia;Col12;Col13;Sigla automobilistica;Col15;Col16;Col17;Col18;Codice Catastale del comune;Col20;Col21;Col22;Col23';
    $rows = [
        '01;001;001;001;010001;AGLIÈ;Agliè;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010001;010001;010001;010001;A074;ITC;ITC1;ITC11;ITC',
        '01;001;001;002;010002;AIRASCA;Airasca;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010002;010002;010002;010002;A109;ITC;ITC1;ITC11;ITC',
        '01;001;001;003;010003;ALA DI STURA;Ala di Stura;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010003;010003;010003;010003;A117;ITC;ITC1;ITC11;ITC',
        '01;001;001;004;010004;ALBIANO D\'IVREA;Albiano d\'Ivrea;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010004;010004;010004;010004;A158;ITC;ITC1;ITC11;ITC',
        '01;001;001;005;010005;ALICE SUPERIORE;Alice Superiore;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010005;010005;010005;010005;A199;ITC;ITC1;ITC11;ITC',
    ];

    Http::fake([
        '*' => Http::response($header."\n".implode("\n", $rows), 200),
    ]);

    Storage::disk('local')->delete('istat_municipalities.csv');

    $service = app(GeographyImportService::class);

    $count = $service->execute();

    expect($count)->toBe(5)
        ->and(Region::count())->toBe(1)
        ->and(Province::count())->toBe(1)
        ->and(Municipality::count())->toBe(5);
});

test('import service imports all geographical data', function () {
    if (app()->environment() !== 'local') {
        $this->markTestSkipped('Only runs locally');
    }
    $service = app(GeographyImportService::class);

    $count = $service->execute();

    expect($count)->toBeGreaterThan(0)
        ->and(Region::count())->toBeGreaterThan(0)
        ->and(Province::count())->toBeGreaterThan(0)
        ->and(Municipality::count())->toBeGreaterThan(0);
});
