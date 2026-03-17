<?php

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

return [
    /*
    |--------------------------------------------------------------------------
    | ISTAT Geography Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the ISTAT geography
    | package. You can customize the table names and other settings here.
    |
    */

    'tables' => [
        'regions' => 'regions',
        'provinces' => 'provinces',
        'municipalities' => 'municipalities',
    ],

    'models' => [
        'region' => Region::class,
        'province' => Province::class,
        'municipality' => Municipality::class,
    ],

    'import' => [
        'csv_url' => env('ISTAT_CSV_URL', 'https://www.istat.it/storage/codici-unita-amministrative/Elenco-comuni-italiani.csv'),
        'temp_filename' => 'istat_municipalities.csv',
    ],

    'cap' => [
        'geojson_url' => env('CAP_GEOJSON_URL', 'https://wupqwfqjfpwrapgnogjv.supabase.co/storage/v1/object/public/parcel-data-access/cap/cap_subcomunali_italia.geojson'),
        'temp_filename' => 'cap_dataset.geojson',
    ],
];
