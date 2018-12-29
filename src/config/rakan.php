<?php

return [
    'default'  => [                         //默认配置
        'prefix'     => 'rakan',               //前缀
        'module'     => 'default',             //模块
        'gateway'    => 'oss',                 //网关
        'table_name' => 'rakan_files',           //迁移表 默认名
    ],
    'gateways' => [
        'oss'   => [
            'access_key' => env('OSS_AK'),
            'secret_key' => env('OSS_SK'),
            'bucket'     => env('OSS_BUCKET'),
            'endpoint'   => env('OSS_ENDPOINT'),
            'host'       => env('OSS_HOST'),
            'expire'     => env('OSS_EXPIRE', 3600),
        ],
        'qiniu' => [
            'access_key' => env('QINIU_AK'),
            'secret_key' => env('QINIU_SK'),
            'bucket'     => env('QINIU_BUCKET'),
            'endpoint'   => env('QINIU_ENDPOINT'),
            'host'       => env('QINIU_HOST'),
            'expire'     => env('QINIU_EXPIRE', 3600),
        ]
    ]
];
