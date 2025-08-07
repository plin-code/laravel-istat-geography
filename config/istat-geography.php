<?php

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
        'region' => \PlinCode\IstatGeography\Models\Geography\Region::class,
        'province' => \PlinCode\IstatGeography\Models\Geography\Province::class,
        'municipality' => \PlinCode\IstatGeography\Models\Geography\Municipality::class,
    ],

    'import' => [
        'csv_url' => 'https://www.istat.it/storage/codici-unita-amministrative/Elenco-comuni-italiani.csv',
        'temp_filename' => 'istat_municipalities.csv',
    ],
];