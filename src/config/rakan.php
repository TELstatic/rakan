<?php

return [
    "dir" => "Images/",
    "debug" => true,
    "alioss" => [
        "host" => env('ALI_HOST'),
        "ak" => env('ALI_AK'),
        "sk" => env('ALI_SK'),
        "callback" => env('ALI_CALLBACK'),
        "expire" => env('ALI_EXPIRE'),
    ]
];