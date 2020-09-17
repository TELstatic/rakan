<?php

return [
    // Hash 混淆相关配置
    'hashids'  => [
        'salt'     => 'rakan',
        'length'   => 10,
        'alphabet' => 'abcdefghijklmnopqrstuvwxyz',
    ],
    //默认配置
    'default'  => [
        'prefix'     => 'rakan',               //前缀
        'module'     => 'default',             //模块
        'gateway'    => 'oss',                 //网关
        'table_name' => 'rakan_files',         //迁移表 默认名
        'route'      => false,                 //是否启用默认控制器
    ],
    'gateways' => [
        'oss'   => [
            'access_key' => env('OSS_AK'),
            'secret_key' => env('OSS_SK'),
            'bucket'     => env('OSS_BUCKET'),
            'endpoint'   => env('OSS_ENDPOINT'),
            'host'       => env('OSS_HOST'),
            'expire'     => env('OSS_EXPIRE', 3600),
            'acl'        => env('OSS_ACL', 1),       //access controller list ACL 权限模式 0 私有 1 公共读 2 公共读写 七牛无 模式2
        ],
        'qiniu' => [
            'access_key' => env('QINIU_AK'),
            'secret_key' => env('QINIU_SK'),
            'bucket'     => env('QINIU_BUCKET'),
            'host'       => env('QINIU_HOST'),
            'expire'     => env('QINIU_EXPIRE', 3600),
            'acl'        => env('QINIU_ACL', 1),
        ],
        'obs' => [
            'access_key' => env('OBS_AK'),
            'secret_key' => env('OBS_SK'),
            'bucket'     => env('OBS_BUCKET'),
            'expire'     => env('OBS_EXPIRE'),
            'acl'        => env('OBS_ACL'),
            'endpoint'   => env('OBS_ENDPOINT'),
            'host'       => env('OBS_HOST'),
        ],
        'cos'   => [
            'access_key' => env('COS_AK'),
            'secret_key' => env('COS_SK'),
            'region'     => env('COS_REGION'),
            'bucket'     => env('COS_BUCKET'),
            'host'       => env('COS_HOST'),
            'expire'     => env('COS_EXPIRE', 3600),
            'acl'        => env('COS_ACL', 1),
        ],
    ],
];
