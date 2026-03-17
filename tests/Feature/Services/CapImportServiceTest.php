<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Services\CapImportService;

beforeEach(function () {
    Storage::disk('local')->delete('cap_dataset.geojson');
});

test('imports postal codes from geojson', function () {
    $municipality = Municipality::factory()->create([
        'bel_code' => 'D704',
    ]);

    Http::fake([
        config('istat-geography.cap.geojson_url') => Http::response([
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

    $service = app(CapImportService::class);
    $count = $service->execute();

    expect($count)->toBe(1)
        ->and($municipality->fresh())
        ->postal_code->toBe('47122')
        ->postal_codes->toBe('47121-47122');
});

test('imports multiple postal codes', function () {
    $municipality1 = Municipality::factory()->create(['bel_code' => 'A001']);
    $municipality2 = Municipality::factory()->create(['bel_code' => 'B002']);

    Http::fake([
        config('istat-geography.cap.geojson_url') => Http::response([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'codice_bel' => 'A001',
                        'cap' => '00100',
                        'comune_cap' => '00100',
                    ],
                    'geometry' => null,
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'codice_bel' => 'B002',
                        'cap' => '20100',
                        'comune_cap' => '20100-20199',
                    ],
                    'geometry' => null,
                ],
            ],
        ]),
    ]);

    $service = app(CapImportService::class);
    $count = $service->execute();

    expect($count)->toBe(2)
        ->and($municipality1->fresh()->postal_code)->toBe('00100')
        ->and($municipality2->fresh()->postal_code)->toBe('20100')
        ->and($municipality2->fresh()->postal_codes)->toBe('20100-20199');
});

test('returns zero count for unmatched bel_code', function () {
    // When a bel_code in the CAP data doesn't match any municipality,
    // the service logs a warning and continues. We verify the count is 0.
    Http::fake([
        config('istat-geography.cap.geojson_url') => Http::response([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'codice_bel' => 'XXXX',
                        'cap' => '00000',
                        'comune_cap' => '00000',
                    ],
                    'geometry' => null,
                ],
            ],
        ]),
    ]);

    $service = app(CapImportService::class);
    $count = $service->execute();

    expect($count)->toBe(0);
});

test('skips features without codice_bel', function () {
    $municipality = Municipality::factory()->create(['bel_code' => 'A001']);

    Http::fake([
        config('istat-geography.cap.geojson_url') => Http::response([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'cap' => '00000',
                        'comune_cap' => '00000',
                    ],
                    'geometry' => null,
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'codice_bel' => 'A001',
                        'cap' => '00100',
                        'comune_cap' => '00100',
                    ],
                    'geometry' => null,
                ],
            ],
        ]),
    ]);

    $service = app(CapImportService::class);
    $count = $service->execute();

    expect($count)->toBe(1)
        ->and($municipality->fresh()->postal_code)->toBe('00100');
});

test('throws exception for invalid geojson', function () {
    Http::fake([
        config('istat-geography.cap.geojson_url') => Http::response([
            'type' => 'InvalidType',
            'features' => [],
        ]),
    ]);

    $service = app(CapImportService::class);
    $service->execute();
})->throws(RuntimeException::class, 'Invalid CAP data format');

test('uses cached geojson file if downloaded today', function () {
    $municipality = Municipality::factory()->create(['bel_code' => 'A001']);

    $geojsonContent = json_encode([
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'properties' => [
                    'codice_bel' => 'A001',
                    'cap' => '00100',
                    'comune_cap' => '00100',
                ],
                'geometry' => null,
            ],
        ],
    ]);

    Storage::disk('local')->put('cap_dataset.geojson', $geojsonContent);
    touch(Storage::disk('local')->path('cap_dataset.geojson'));

    Http::fake();

    $service = app(CapImportService::class);
    $count = $service->execute();

    expect($count)->toBe(1);

    Http::assertNothingSent();
});
