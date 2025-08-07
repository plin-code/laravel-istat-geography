<?php

use Illuminate\Support\Facades\Http;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\GeographyImportService;

test('mocked import service imports all geographical data', function () {
    // Mock di tutte le richieste HTTP
    Http::fake([
        '*' => Http::response(
            "Codice Regione;Codice dell'Unità territoriale sovracomunale (valida a fini statistici);Codice Provincia (Storico)(1);Progressivo del Comune (2);Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Codice Ripartizione Geografica;Ripartizione geografica;Denominazione regione;Denominazione dell'Unità territoriale sovracomunale (valida a fini statistici);Denominazione in italiano;Denominazione della Provincia;Flag Comune capoluogo di provincia/città metropolitana/libero consorzio;Sigla automobilistica;Codice Comune formato numerico;Codice Comune 110 (107) (3);Codice Comune 103 (1995) (3);Codice Catastale;Codice NUTS1 2010;Codice NUTS2 2010 (3);Codice NUTS3 2010;Codice NUTS1 2021;Codice NUTS2 2021 (3);Codice NUTS3 2021\n".
            "01;001;001;001;010001;AGLIÈ;1;Nord-ovest;Piemonte;Torino;Piemonte;Torino;0;TO;010001;010001;010001;A074;ITC1;ITC1;ITC11;ITC1;ITC1;ITC11\n".
            "01;001;001;002;010002;AIRASCA;1;Nord-ovest;Piemonte;Torino;Piemonte;Torino;0;TO;010002;010002;010002;A109;ITC1;ITC1;ITC11;ITC1;ITC1;ITC11\n".
            "01;001;001;003;010003;ALA DI STURA;1;Nord-ovest;Piemonte;Torino;Piemonte;Torino;0;TO;010003;010003;010003;A117;ITC1;ITC1;ITC11;ITC1;ITC1;ITC11\n".
            "01;001;001;004;010004;ALBIANO D'IVREA;1;Nord-ovest;Piemonte;Torino;Piemonte;Torino;0;TO;010004;010004;010004;A158;ITC1;ITC1;ITC11;ITC1;ITC1;ITC11\n".
            '01;001;001;005;010005;ALICE SUPERIORE;1;Nord-ovest;Piemonte;Torino;Piemonte;Torino;0;TO;010005;010005;010005;A199;ITC1;ITC1;ITC11;ITC1;ITC1;ITC11',
            200
        ),
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
