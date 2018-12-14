<?php

return [
    'debug' => true,
    'oss'   => [
        'host'       => env('ALI_HOST'),
        'endpoint'   => env('ALI_ENDPOINT'),
        'bucket'     => env('ALI_BUCKET'),
        'access_key' => env('ALI_AK'),
        'secret_key' => env('ALI_SK'),
        'callback'   => env('ALI_CALLBACK'),
        'expire'     => env('ALI_EXPIRE', 120),
    ],
    'table' => [
        'name' => 'rakan_files'
    ]
];
