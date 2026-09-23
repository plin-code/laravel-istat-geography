<?php

use Illuminate\Support\Facades\Http;

afterEach(function () {
    @unlink(storage_path('app/coordinates-dataset.json'));
});

test('download coordinates command downloads and decompresses the dataset', function () {
    $dataset = json_encode(['meta' => [], 'municipalities' => []]);

    Http::fake([
        config('istat-geography.coordinates.dataset_url') => Http::response(gzencode($dataset), 200),
    ]);

    $this->artisan('geography:download-coordinates')
        ->expectsOutputToContain('Downloading coordinates dataset from:')
        ->expectsOutputToContain('Download completed!')
        ->expectsOutputToContain('geography:import --coordinates-only --coordinates-file=')
        ->assertSuccessful();

    expect(file_get_contents(storage_path('app/coordinates-dataset.json')))->toBe($dataset);
});

test('download coordinates command accepts a custom url', function () {
    $customUrl = 'https://example.com/coordinates.json';

    Http::fake([
        $customUrl => Http::response('{"meta":{},"municipalities":[]}', 200),
    ]);

    $this->artisan("geography:download-coordinates --url={$customUrl}")
        ->expectsOutputToContain($customUrl)
        ->assertSuccessful();
});

test('download coordinates command fails gracefully on http error', function () {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $this->artisan('geography:download-coordinates')
        ->expectsOutputToContain('Download failed')
        ->assertFailed();
});
