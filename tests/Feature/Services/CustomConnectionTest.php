<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\IstatGeography;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\GeographyImportService;

$csvHeader = 'Codice Regione;Col1;Codice Provincia (Storico);Col3;Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Denominazione italiano;Denominazione altra lingua;Col8;Col9;Denominazione Regione;Denominazione Provincia;Col12;Col13;Sigla automobilistica;Col15;Col16;Col17;Col18;Codice Catastale del comune;Col20;Col21;Col22;Col23';
$csvRow = '01;001;001;001;010001;AGLIÈ;Agliè;;1;Nord-ovest;Piemonte;Torino;0;0;TO;010001;010001;010001;010001;A074;ITC;ITC1;ITC11;ITC';

test('IstatGeography import uses configured connection end-to-end', function () use ($csvHeader, $csvRow) {
    config()->set('istat-geography.connection', 'testing');

    Http::fake(['*' => Http::response($csvHeader."\n".$csvRow, 200)]);
    Storage::disk('local')->delete('istat_municipalities.csv');

    $result = app(IstatGeography::class)->import();

    expect($result)->toBe(1)
        ->and(Region::count())->toBe(1)
        ->and(Province::count())->toBe(1)
        ->and(Municipality::count())->toBe(1);
});

test('GeographyImportService execute stores provided connection', function () use ($csvHeader, $csvRow) {
    Http::fake(['*' => Http::response($csvHeader."\n".$csvRow, 200)]);
    Storage::disk('local')->delete('istat_municipalities.csv');

    $service = app(GeographyImportService::class);
    $service->execute('testing');

    $property = (new ReflectionClass($service))->getProperty('connection');
    $property->setAccessible(true);

    expect($property->getValue($service))->toBe('testing');
});
