<?php

namespace App\Services;

use App\Support\MobileIngestRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Exception;

class HealthService
{
    public function check()
    {
        $status = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            //'external_api' => $this->checkExternalApi(),
        ];

        $allOk = collect($status)->every(fn($s) => $s['status'] === 'OK');

        return [
            'status' => $allOk ? 'OK' : 'PARTIAL',
            'checks' => $status,
            'network' => $this->networkConfig(),
            'ingest' => $this->ingestConfig(),
            'time' => now()->toDateTimeString(),
        ];
    }

    private function networkConfig(): array
    {
        $publicBaseUrl = rtrim((string) config('mobile_network.public_base_url', ''), '/');
        $localBaseUrl = rtrim((string) config('mobile_network.local_base_url', ''), '/');
        $preferredRoute = strtolower((string) config('mobile_network.preferred_route', 'public'));

        return [
            'public_base_url' => $publicBaseUrl,
            'local_base_url' => $localBaseUrl,
            'preferred_route' => $preferredRoute === 'local' ? 'local' : 'public',
        ];
    }

    private function ingestConfig(): array
    {
        return [
            'storage_disk' => MobileIngestRuntime::storageDisk('local'),
            'cache_store' => MobileIngestRuntime::cacheStore('file'),
            'lock_store' => MobileIngestRuntime::lockStore(MobileIngestRuntime::cacheStore('file')),
            'dispatch_mode' => MobileIngestRuntime::dispatchMode(),
            'queue_connection' => MobileIngestRuntime::queueConnection('sync'),
            'queue_name' => MobileIngestRuntime::queueName('mobile-metrics'),
            'uses_async_queue' => MobileIngestRuntime::usesAsyncQueue(),
            'worker_enabled' => MobileIngestRuntime::workerEnabled(),
        ];
    }

    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'OK', 'message' => 'DB connected'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage()
    {
        try {
            $disk = MobileIngestRuntime::storageDisk('local');
            Storage::disk($disk)->files();

            return ['status' => 'OK', 'message' => "Storage disk {$disk} accessible"];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue()
    {
        $connection = MobileIngestRuntime::queueConnection('sync');
        $queueName = MobileIngestRuntime::queueName('mobile-metrics');
        $workerEnabled = MobileIngestRuntime::workerEnabled();

        if ($connection === 'sync') {
            return [
                'status' => 'WARN',
                'message' => 'Queue masih sync, request upload bisa menahan worker',
                'connection' => $connection,
                'queue' => $queueName,
                'backlog' => 0,
                'worker_enabled' => false,
            ];
        }

        if ($connection === 'redis') {
            try {
                Redis::ping();
                $size = Queue::connection($connection)->size($queueName);

                return [
                    'status' => $workerEnabled ? 'OK' : 'WARN',
                    'message' => $workerEnabled
                        ? "Redis queue connected, backlog {$size}"
                        : "Redis queue connected, backlog {$size}, tetapi worker dimatikan",
                    'connection' => $connection,
                    'queue' => $queueName,
                    'backlog' => $size,
                    'worker_enabled' => $workerEnabled,
                ];
            } catch (Exception $e) {
                return ['status' => 'ERROR', 'message' => $e->getMessage()];
            }
        }

        try {
            $size = Queue::connection($connection)->size($queueName);

            return [
                'status' => $workerEnabled ? 'OK' : 'WARN',
                'message' => $workerEnabled
                    ? "Queue {$connection} ready, backlog {$size}"
                    : "Queue {$connection} ready, backlog {$size}, tetapi worker dimatikan",
                'connection' => $connection,
                'queue' => $queueName,
                'backlog' => $size,
                'worker_enabled' => $workerEnabled,
            ];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function checkExternalApi()
    {
        try {
            $response = Http::timeout(3)->get('https://www.google.com');
            return ['status' => $response->successful() ? 'OK' : 'ERROR', 'message' => 'External API responded'];
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}
