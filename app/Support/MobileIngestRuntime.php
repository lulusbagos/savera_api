<?php

namespace App\Support;

class MobileIngestRuntime
{
    public static function storageDisk(string $fallback = 'local'): string
    {
        $configured = trim((string) config('mobile_ingest.storage_disk', $fallback));
        $disks = array_keys((array) config('filesystems.disks', []));

        return in_array($configured, $disks, true) ? $configured : $fallback;
    }

    public static function cacheStore(string $fallback = 'file'): string
    {
        return self::normalizeStore((string) config('mobile_ingest.cache_store', $fallback), $fallback);
    }

    public static function lockStore(string $fallback = 'file'): string
    {
        return self::normalizeStore((string) config('mobile_ingest.lock_store', self::cacheStore($fallback)), $fallback);
    }

    public static function queueConnection(string $fallback = 'sync'): string
    {
        $configured = (string) config('mobile_ingest.queue_connection', config('queue.default', $fallback));
        $connections = array_keys((array) config('queue.connections', []));

        return in_array($configured, $connections, true) ? $configured : $fallback;
    }

    public static function queueName(string $fallback = 'mobile-metrics'): string
    {
        $name = trim((string) config('mobile_ingest.queue_name', $fallback));

        return $name !== '' ? $name : $fallback;
    }

    public static function dispatchMode(): string
    {
        $mode = strtolower(trim((string) config('mobile_ingest.dispatch_mode', 'auto')));

        return in_array($mode, ['auto', 'queue', 'after_response', 'sync'], true) ? $mode : 'auto';
    }

    public static function usesAsyncQueue(): bool
    {
        return in_array(self::queueConnection(), ['database', 'redis', 'beanstalkd', 'sqs'], true);
    }

    public static function workerEnabled(): bool
    {
        return (bool) config('mobile_ingest.worker_enabled', true);
    }

    private static function normalizeStore(string $store, string $fallback): string
    {
        $store = trim($store);
        $stores = array_keys((array) config('cache.stores', []));

        return in_array($store, $stores, true) ? $store : $fallback;
    }
}
