<?php

namespace App\Services;

use App\Models\MobileUploadBatch;
use App\Models\WorkerHeartbeat;
use App\Support\MobileIngestRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
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
            'recent_uploads' => $this->recentUploadStats(),
            'upload_monitoring' => $this->uploadMonitoringStats(),
        ];
    }

    private function uploadMonitoringStats(): array
    {
        if (! Schema::hasTable('mobile_upload_batches')) {
            return [
                'enabled' => false,
                'message' => 'Tabel mobile_upload_batches belum dimigrate',
            ];
        }

        $since = now()->subHour();
        $recent = MobileUploadBatch::query()
            ->where('received_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $workers = [];
        if (Schema::hasTable('worker_heartbeats')) {
            $workers = WorkerHeartbeat::query()
                ->orderByDesc('last_seen_at')
                ->limit(10)
                ->get(['worker_name', 'queue_name', 'status', 'current_upload_id', 'last_seen_at', 'processed_count', 'failed_count'])
                ->map(fn (WorkerHeartbeat $worker): array => [
                    'worker_name' => $worker->worker_name,
                    'queue_name' => $worker->queue_name,
                    'status' => $worker->status,
                    'current_upload_id' => $worker->current_upload_id,
                    'last_seen_at' => optional($worker->last_seen_at)->toDateTimeString(),
                    'processed_count' => (int) $worker->processed_count,
                    'failed_count' => (int) $worker->failed_count,
                ])
                ->values()
                ->all();
        }

        return [
            'enabled' => true,
            'last_hour_by_status' => $recent,
            'pending' => (int) ($recent['received'] ?? 0) + (int) ($recent['queued'] ?? 0),
            'processing' => (int) ($recent['processing'] ?? 0),
            'completed' => (int) ($recent['completed'] ?? 0),
            'failed' => (int) ($recent['failed'] ?? 0),
            'workers' => $workers,
        ];
    }

    private function recentUploadStats(): array
    {
        $routes = ['summary', 'detail', 'mobile.sleep-snapshot', 'ingest.v2.wearable'];
        $requests = Cache::store(MobileIngestRuntime::cacheStore('file'))->get('recent_requests', []);
        if (! is_array($requests)) {
            return [
                'total' => 0,
                'failed' => 0,
                'avg_ms' => 0.0,
                'max_payload_bytes' => 0,
                'last_time' => null,
                'last_route' => null,
                'routes' => [],
            ];
        }

        $total = 0;
        $failed = 0;
        $durations = [];
        $maxPayloadBytes = 0;
        $lastTime = null;
        $lastRoute = null;
        $byRoute = [];

        foreach ($requests as $request) {
            $route = (string) ($request['route'] ?? '');
            if (! in_array($route, $routes, true)) {
                continue;
            }

            $total++;
            $status = (int) ($request['status'] ?? 0);
            if ($status <= 0 || $status >= 400) {
                $failed++;
            }
            $durations[] = (float) ($request['duration_ms'] ?? 0.0);
            $maxPayloadBytes = max($maxPayloadBytes, (int) ($request['request_bytes'] ?? 0));
            $byRoute[$route] = ($byRoute[$route] ?? 0) + 1;
            if ($lastTime === null) {
                $lastTime = $request['time'] ?? null;
                $lastRoute = $route;
            }
        }

        return [
            'total' => $total,
            'failed' => $failed,
            'fail_rate' => $total > 0 ? round(($failed / $total) * 100, 1) : 0.0,
            'avg_ms' => $durations === [] ? 0.0 : round(array_sum($durations) / count($durations), 2),
            'max_payload_bytes' => $maxPayloadBytes,
            'last_time' => $lastTime,
            'last_route' => $lastRoute,
            'routes' => $byRoute,
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
