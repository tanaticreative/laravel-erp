<?php


return [
    'queue' => 'default', // RESERVED
    'default_locale' => env('ERP_DEFAULT_LOCALE', 'de'),
    'sync' => [
        'enabled' => env('ERP_SYNC_ENABLED', false),
    ],
    'api' => [
        'base_url' => env('ERP_API_BASEURL'),
        'authtoken' => env('ERP_API_AUTHTOKEN'),
    ],
    'webhook' => [
        'client' => env('ERP_WEBHOOK_CLIENT'),
        'secret' => env('ERP_WEBHOOK_SECRET'),
    ]
];
