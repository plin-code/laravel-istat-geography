<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('download cap command downloads geojson from default url', function () {
    $geojsonContent = json_encode([
        'type' => 'FeatureCollection',
        'features' => [['type' => 'Feature', 'properties' => ['codice_bel' => 'A001']]],
    ]);

    Http::fake([
        config('istat-geography.cap.geojson_url') => Http::response($geojsonContent, 200),
    ]);

    $outputPath = storage_path('app/cap-dataset.json');

    $this->artisan('geography:download-cap')
        ->expectsOutputToContain('Downloading CAP data from:')
        ->expectsOutputToContain('Download completed!')
        ->assertSuccessful();
});

test('download cap command accepts custom url', function () {
    $customUrl = 'https://example.com/custom-cap.json';

    Http::fake([
        $customUrl => Http::response('{"type":"FeatureCollection","features":[]}', 200),
    ]);

    $this->artisan("geography:download-cap --url={$customUrl}")
        ->expectsOutputToContain($customUrl)
        ->assertSuccessful();
});

test('download cap command fails gracefully on http error', function () {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $this->artisan('geography:download-cap')
        ->expectsOutputToContain('Download failed')
        ->assertFailed();
});

test('download cap command shows usage hint after download', function () {
    Http::fake([
        '*' => Http::response('{}', 200),
    ]);

    $this->artisan('geography:download-cap')
        ->expectsOutputToContain('geography:import --cap --cap-file=')
        ->assertSuccessful();
});
