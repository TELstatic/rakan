<?php

return [
    "debug"  => true,
    "alioss" => [
        "host"     => env('ALI_HOST'),
        "endpoint" => env('ALI_ENDPOINT'),
        "bucket"   => env('ALI_BUCKET'),
        "ak"       => env('ALI_AK'),
        "sk"       => env('ALI_SK'),
        "callback" => env('ALI_CALLBACK'),
        "expire"   => env('ALI_EXPIRE'),
    ]
];