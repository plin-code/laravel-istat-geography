<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Services\CoordinatesImportService;

beforeEach(function () {
    Storage::disk('local')->delete('coordinates_dataset.json');
});

function coordinatesDataset(array $municipalities): array
{
    return [
        'meta' => ['license' => 'CC BY 4.0'],
        'municipalities' => $municipalities,
    ];
}

test('imports coordinates from the gzipped dataset matching by istat code', function () {
    $rome = Municipality::factory()->create(['istat_code' => '058091']);
    $bologna = Municipality::factory()->create(['istat_code' => '037006']);

    Http::fake([
        config('istat-geography.coordinates.dataset_url') => Http::response(gzencode(json_encode(coordinatesDataset([
            ['istat_code' => '058091', 'latitude' => 41.8853588, 'longitude' => 12.4607809],
            ['istat_code' => '037006', 'latitude' => 44.4979899, 'longitude' => 11.3334212],
        ])))),
    ]);

    $count = app(CoordinatesImportService::class)->execute();

    expect($count)->toBe(2)
        ->and($rome->fresh())
        ->latitude->toBe(41.8853588)
        ->longitude->toBe(12.4607809)
        ->and($bologna->fresh())
        ->latitude->toBe(44.4979899)
        ->longitude->toBe(11.3334212);
});

test('imports coordinates from a local file without downloading', function () {
    $rome = Municipality::factory()->create(['istat_code' => '058091']);

    Http::fake();

    $count = app(CoordinatesImportService::class)
        ->useLocalFile(__DIR__.'/../../Fixtures/municipality_coordinates_dataset.json')
        ->execute();

    expect($count)->toBe(1)
        ->and($rome->fresh()->latitude)->toBe(41.8853588);

    Http::assertNothingSent();
});

test('logs and skips istat codes without a municipality', function () {
    Log::spy();

    $rome = Municipality::factory()->create(['istat_code' => '058091']);

    Http::fake([
        config('istat-geography.coordinates.dataset_url') => Http::response(coordinatesDataset([
            ['istat_code' => '999999', 'latitude' => 45.0, 'longitude' => 9.0],
            ['istat_code' => '058091', 'latitude' => 41.8853588, 'longitude' => 12.4607809],
        ])),
    ]);

    $count = app(CoordinatesImportService::class)->execute();

    expect($count)->toBe(1)
        ->and($rome->fresh()->latitude)->toBe(41.8853588);

    Log::shouldHaveReceived('warning')->with("Coordinates import: no municipality found for istat_code '999999'")->once();
});

test('skips entries with invalid coordinates', function (array $entry) {
    $municipality = Municipality::factory()->create(['istat_code' => '058091']);

    Http::fake([
        config('istat-geography.coordinates.dataset_url') => Http::response(coordinatesDataset([$entry])),
    ]);

    $count = app(CoordinatesImportService::class)->execute();

    expect($count)->toBe(0)
        ->and($municipality->fresh()->latitude)->toBeNull();
})->with([
    'missing istat code' => [['latitude' => 41.9, 'longitude' => 12.5]],
    'missing latitude' => [['istat_code' => '058091', 'longitude' => 12.5]],
    'non numeric longitude' => [['istat_code' => '058091', 'latitude' => 41.9, 'longitude' => 'east']],
    'latitude out of range' => [['istat_code' => '058091', 'latitude' => 91, 'longitude' => 12.5]],
    'longitude out of range' => [['istat_code' => '058091', 'latitude' => 41.9, 'longitude' => -180.5]],
]);

test('throws exception for an invalid dataset format', function () {
    Http::fake([
        config('istat-geography.coordinates.dataset_url') => Http::response(['type' => 'FeatureCollection', 'features' => []]),
    ]);

    app(CoordinatesImportService::class)->execute();
})->throws(RuntimeException::class, 'Invalid coordinates data format');

test('throws exception when the download fails', function () {
    Http::fake([
        config('istat-geography.coordinates.dataset_url') => Http::response('Not Found', 404),
    ]);

    app(CoordinatesImportService::class)->execute();
})->throws(RuntimeException::class, 'Failed to download coordinates data: HTTP 404');

test('uses the cached dataset if downloaded today', function () {
    Municipality::factory()->create(['istat_code' => '058091']);

    Storage::disk('local')->put('coordinates_dataset.json', json_encode(coordinatesDataset([
        ['istat_code' => '058091', 'latitude' => 41.8853588, 'longitude' => 12.4607809],
    ])));

    Http::fake();

    $count = app(CoordinatesImportService::class)->execute();

    expect($count)->toBe(1);

    Http::assertNothingSent();
});
