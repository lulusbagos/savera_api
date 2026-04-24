<?php

return [
    'storage_disk' => env('MOBILE_INGEST_STORAGE_DISK', 'mobile_metrics'),
    'cache_store' => env('MOBILE_INGEST_CACHE_STORE', env('CACHE_STORE', 'database')),
    'lock_store' => env('MOBILE_INGEST_LOCK_STORE', env('MOBILE_INGEST_CACHE_STORE', env('CACHE_STORE', 'database'))),
    'dispatch_mode' => env('MOBILE_INGEST_DISPATCH_MODE', 'auto'),
    'queue_connection' => env('MOBILE_INGEST_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
    'queue_name' => env('MOBILE_INGEST_QUEUE_NAME', env('DB_QUEUE', 'mobile-metrics')),
    'worker_enabled' => filter_var(env('MOBILE_INGEST_WORKER_ENABLED', true), FILTER_VALIDATE_BOOL),
];
