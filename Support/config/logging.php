<?php


return [
    'channels' => [
        'erp' => [
            'driver' => 'stack',
            'channels' => ['erp-info', 'erp-error'],
        ],
        'erp-info' => [
            'driver' => 'daily',
            'path' => storage_path('logs/erp.log'),
            'level' => 'debug',
            'days' => 30,
        ],
        'erp-error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/erp-error.log'),
            'level' => 'error',
            'days' => 30,
        ]
    ]
];
