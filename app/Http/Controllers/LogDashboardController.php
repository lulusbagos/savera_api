<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MobileUploadBatch;
use App\Models\MobileUploadChunk;
use App\Models\WorkerHeartbeat;
use App\Support\MobileIngestRuntime;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LogDashboardController extends Controller
{
    private const CACHE_STORE = 'file';

    public function stream(Request $request): JsonResponse
    {
        $path = $this->resolveLogPath();

        if (!$path || !file_exists($path)) {
            return response()->json([
                'summary' => [],
                'recent' => [],
                'message' => 'Log file not found',
            ], 404);
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -600);

        $entries = [];
        $storageDurations = [];
        $firstLogTime = null;
        $summary = [
            'api_success' => 0,
            'api_failed' => 0,
            'db_slow' => 0,
            'storage_errors' => 0,
            'storage_writes' => 0,
        ];

        foreach ($lines as $line) {
            if (!preg_match('/^\\[(.*?)\\]\\s+([^\\.]+)\\.([A-Z]+):\\s+(.*)$/', $line, $matches)) {
                continue;
            }

            [, $timestamp, $env, $level, $message] = $matches;
            if ($firstLogTime === null) {
                $firstLogTime = $timestamp;
            }
            $lower = Str::lower($message);
            $category = 'general';

            if (Str::contains($lower, 'api request success')) {
                $category = 'api';
                $summary['api_success']++;
            } elseif (Str::contains($lower, 'api request failed') || Str::contains($lower, 'api error')) {
                $category = 'api';
                $summary['api_failed']++;
            } elseif (Str::contains($lower, 'db slow query')) {
                $category = 'database';
                $summary['db_slow']++;
            } elseif (Str::contains($lower, 'failed storing user metrics')) {
                $category = 'storage';
                $summary['storage_errors']++;
            } elseif (Str::contains($lower, 'metrics stored') || Str::contains($lower, 'metrics unchanged')) {
                $category = 'storage';
                $summary['storage_writes']++;

                // Ambil durasi tulis storage dari context log (duration_ms).
                if (preg_match('/\"duration_ms\":([0-9]+(?:\\.[0-9]+)?)/', $line, $durationMatch)) {
                    $storageDurations[] = (float) $durationMatch[1];
                }
            }

            $entries[] = [
                'time' => $timestamp,
                'env' => $env,
                'level' => $level,
                'category' => $category,
                'message' => Str::limit($message, 400),
            ];
        }

        $entries = array_slice(array_reverse($entries), 0, 120);

        // Tampilkan error/warning terbaru agar panel "Error & Warning Terbaru"
        // tidak terus menampilkan incident lama yang sudah ditangani.
        $windowMinutes = (int) $request->query('window_minutes', 60);
        $windowMinutes = max(5, min(1440, $windowMinutes));
        $windowStart = Carbon::now()->subMinutes($windowMinutes);
        $entries = array_values(array_filter($entries, function (array $entry) use ($windowStart) {
            try {
                return Carbon::parse((string) ($entry['time'] ?? ''))->gte($windowStart);
            } catch (\Throwable $e) {
                return false;
            }
        }));

        $storageDurations = array_slice(array_reverse($storageDurations), 0, 60);
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $requests = $cache->get('recent_requests', []);
        $requestFallbacks = $this->buildRequestFallbackFromUploadMonitoring();
        if (!empty($requestFallbacks)) {
            $requests = array_slice(array_merge($requests, $requestFallbacks), 0, 100);
        }

        // Fallback & penambahan dari cache file store untuk mengurangi beban database.
        $cachedDurations = $cache->get('storage_durations', []);
        if (!empty($cachedDurations)) {
            $storageDurations = array_slice(array_merge($cachedDurations, $storageDurations), 0, 60);
        }
        $storageFallbackDurations = $this->buildStorageDurationFallback();
        if (!empty($storageFallbackDurations)) {
            $storageDurations = array_slice(array_merge($storageDurations, $storageFallbackDurations), 0, 60);
        }

        $cachedStats = $cache->get('storage_stats', []);
        if (!empty($cachedStats)) {
            $summary['storage_writes'] = max($summary['storage_writes'], $cachedStats['writes'] ?? 0);
            $summary['storage_errors'] = max($summary['storage_errors'], $cachedStats['errors'] ?? 0);
        }
        if (!empty($storageDurations)) {
            $summary['storage_writes'] = max($summary['storage_writes'], count($storageDurations));
        }

        $uploadSummary = $this->buildUploadSummary($requests);
        $summary = array_merge($summary, $uploadSummary);

        $totalApi = max(0, $summary['api_success'] + $summary['api_failed']);
        $errorRate = $totalApi > 0 ? round(($summary['api_failed'] / $totalApi) * 100, 1) : 0.0;
        $lastLogTime = $entries[0]['time'] ?? null;
        $lastErrorTime = null;
        foreach ($entries as $entry) {
            $level = strtoupper($entry['level'] ?? '');
            if (in_array($level, ['ERROR', 'CRITICAL', 'WARNING'], true)) {
                $lastErrorTime = $entry['time'];
                break;
            }
        }

        $now = Carbon::now();
        $uptimeStart = $lastErrorTime ?: ($firstLogTime ?: $lastLogTime);
        $uptimeSeconds = $uptimeStart ? Carbon::parse($uptimeStart)->diffInSeconds($now, false) : 0;
        $uptimeHuman = $uptimeSeconds > 0 ? $this->formatDuration($uptimeSeconds) : 'n/a';

        $meta = [
            'log_path' => $path,
            'log_size_mb' => round((@filesize($path) ?: 0) / 1048576, 2),
            'last_log_time' => $lastLogTime,
            'last_error_time' => $lastErrorTime,
            'window_minutes' => $windowMinutes,
            'error_rate' => $errorRate,
            'refreshed_at' => $now->toDateTimeString(),
            'uptime_seconds' => $uptimeSeconds,
            'uptime_human' => $uptimeHuman,
            'uptime_since' => $uptimeStart,
        ];

        return response()->json([
            'summary' => $summary,
            'recent' => $entries,
            'requests' => $requests,
            'storage_durations' => $storageDurations,
            'operations' => $this->buildOperationsSummary(),
            'meta' => $meta,
        ]);
    }

    /**
     * Cari file log yang tersedia. Prioritas ke laravel.log,
     * jika memakai driver daily akan mengambil file terbaru (laravel-YYYY-MM-DD.log).
     */
    private function resolveLogPath(): ?string
    {
        $single = storage_path('logs/laravel.log');
        if (file_exists($single)) {
            return $single;
        }

        $dailyFiles = glob(storage_path('logs/laravel-*.log')) ?: [];
        if (empty($dailyFiles)) {
            return null;
        }

        usort($dailyFiles, function ($a, $b) {
            return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
        });

        return $dailyFiles[0] ?? null;
    }

    /**
     * Ubah detik menjadi format singkat (mis: "2h 5m").
     */
    private function formatDuration(int $seconds): string
    {
        $units = [
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ];

        $parts = [];
        foreach ($units as $label => $value) {
            if ($seconds >= $value) {
                $count = intdiv($seconds, $value);
                $seconds -= $count * $value;
                $parts[] = "{$count}{$label}";
            }
            if (count($parts) >= 2) {
                break; // tampilkan 2 unit teratas agar ringkas
            }
        }

        return !empty($parts) ? implode(' ', $parts) : '0s';
    }

    /**
     * Ringkasan upload mobile dari cache recent_requests.
     *
     * @param array<int, array<string, mixed>> $requests
     * @return array<string, int|float|string|null>
     */
    private function buildUploadSummary(array $requests): array
    {
        $summary = [
            'upload_recent_total' => 0,
            'upload_summary_ok' => 0,
            'upload_summary_failed' => 0,
            'upload_detail_ok' => 0,
            'upload_detail_failed' => 0,
            'upload_sleep_snapshot_ok' => 0,
            'upload_sleep_snapshot_failed' => 0,
            'upload_ingest_v2_ok' => 0,
            'upload_ingest_v2_failed' => 0,
            'upload_fail_rate' => 0.0,
            'upload_avg_ms' => 0.0,
            'upload_last_time' => null,
            'upload_last_route' => null,
        ];

        $durations = [];
        foreach ($requests as $request) {
            $route = (string) ($request['route'] ?? '');
            $uri = (string) ($request['uri'] ?? '');
            if ($route === '') {
                if (Str::contains($uri, '/api/summary')) {
                    $route = 'summary';
                } elseif (Str::contains($uri, '/api/detail')) {
                    $route = 'detail';
                } elseif (Str::contains($uri, '/api/mobile/sleep-snapshot')) {
                    $route = 'mobile.sleep-snapshot';
                } elseif (Str::contains($uri, '/api/v2/ingest/wearable')) {
                    $route = 'ingest.v2.wearable';
                }
            }

            if (!in_array($route, ['summary', 'detail', 'mobile.sleep-snapshot', 'ingest.v2.wearable'], true)) {
                continue;
            }

            $summary['upload_recent_total']++;
            $status = (int) ($request['status'] ?? 0);
            $isSuccess = $status > 0 && $status < 400;
            $durations[] = (float) ($request['duration_ms'] ?? 0.0);

            if ($route === 'summary') {
                $summary[$isSuccess ? 'upload_summary_ok' : 'upload_summary_failed']++;
            } elseif ($route === 'detail') {
                $summary[$isSuccess ? 'upload_detail_ok' : 'upload_detail_failed']++;
            } elseif ($route === 'mobile.sleep-snapshot') {
                $summary[$isSuccess ? 'upload_sleep_snapshot_ok' : 'upload_sleep_snapshot_failed']++;
            } else {
                $summary[$isSuccess ? 'upload_ingest_v2_ok' : 'upload_ingest_v2_failed']++;
            }

            if ($summary['upload_last_time'] === null) {
                $summary['upload_last_time'] = $request['time'] ?? null;
                $summary['upload_last_route'] = $route;
            }
        }

        $failed = $summary['upload_summary_failed']
            + $summary['upload_detail_failed']
            + $summary['upload_sleep_snapshot_failed']
            + $summary['upload_ingest_v2_failed'];
        if ($summary['upload_recent_total'] > 0) {
            $summary['upload_fail_rate'] = round(($failed / $summary['upload_recent_total']) * 100, 1);
            $summary['upload_avg_ms'] = round(array_sum($durations) / count($durations), 2);
        }

        return $summary;
    }

    /**
     * Fallback untuk grafik Request Duration ketika cache recent_requests kosong,
     * misalnya setelah cache clear/restart. Data diambil dari tabel monitoring upload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRequestFallbackFromUploadMonitoring(): array
    {
        if (! Schema::hasTable('mobile_upload_batches')) {
            return [];
        }

        return MobileUploadBatch::query()
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->map(function (MobileUploadBatch $batch): array {
                $extra = is_array($batch->extra_json) ? $batch->extra_json : [];
                $status = (string) ($batch->status ?: 'received');
                $receivedAt = $batch->received_at ?: $batch->created_at;
                $endAt = $batch->queued_at ?: ($batch->received_at ?: $batch->updated_at);
                $durationMs = $this->durationMs($receivedAt, $endAt, 1.0);

                return [
                    'time' => optional($receivedAt)->format('Y-m-d H:i:s') ?: now()->format('Y-m-d H:i:s'),
                    'method' => 'POST',
                    'uri' => '/api/' . $batch->source,
                    'route' => $batch->source,
                    'status' => $this->httpStatusFromUploadStatus($status),
                    'duration_ms' => $durationMs,
                    'mac' => $extra['mac_address'] ?? null,
                    'ip' => null,
                    'user_id' => $batch->user_id,
                    'app_version' => $extra['app_version'] ?? null,
                    'request_bytes' => (int) $batch->payload_bytes_total,
                    'speed_kbps_est' => $durationMs > 0
                        ? round((((int) $batch->payload_bytes_total) * 8) / $durationMs, 2)
                        : null,
                    'source' => 'upload_monitoring_fallback',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Fallback untuk grafik Storage Write Speed. Saat worker belum menulis cache
     * storage_durations, pakai durasi transisi chunk processing->processed.
     * Jika belum processed, tampilkan durasi queue accept kecil agar chart tetap
     * memberi sinyal bahwa payload sudah masuk tetapi masih menunggu worker.
     *
     * @return array<int, float>
     */
    private function buildStorageDurationFallback(): array
    {
        if (! Schema::hasTable('mobile_upload_chunks')) {
            return [];
        }

        return MobileUploadChunk::query()
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->map(function (MobileUploadChunk $chunk): float {
                if ($chunk->processing_started_at && $chunk->processed_at) {
                    return $this->durationMs($chunk->processing_started_at, $chunk->processed_at, 1.0);
                }

                if ($chunk->received_at && $chunk->queued_at) {
                    return $this->durationMs($chunk->received_at, $chunk->queued_at, 1.0);
                }

                return 1.0;
            })
            ->values()
            ->all();
    }

    private function httpStatusFromUploadStatus(string $status): int
    {
        return match ($status) {
            'failed' => 500,
            'completed' => 200,
            'processing', 'queued', 'received' => 202,
            default => 200,
        };
    }

    private function durationMs($start, $end, float $fallback): float
    {
        try {
            if (! $start || ! $end) {
                return $fallback;
            }

            $startMs = (float) Carbon::parse($start)->valueOf();
            $endMs = (float) Carbon::parse($end)->valueOf();
            $duration = round(max($endMs - $startMs, $fallback), 2);

            return min($duration, 60000.0);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * Ringkasan runtime ingest supaya dashboard tahu apakah upload diproses sync/queue
     * dan apakah backlog sedang menumpuk.
     *
     * @return array<string, int|bool|string|null>
     */
    private function buildOperationsSummary(): array
    {
        $connection = MobileIngestRuntime::queueConnection('sync');
        $queueName = MobileIngestRuntime::queueName('mobile-metrics');
        $workerEnabled = MobileIngestRuntime::workerEnabled();
        $usesAsyncQueue = MobileIngestRuntime::usesAsyncQueue();
        $backlog = null;
        $queueStatus = $connection === 'sync' ? 'sync' : 'unknown';
        $queueMessage = $connection === 'sync'
            ? 'Upload masih diproses di request utama.'
            : 'Status queue belum diketahui.';

        if ($usesAsyncQueue) {
            try {
                $backlog = Queue::connection($connection)->size($queueName);
                $queueStatus = $workerEnabled ? 'ready' : 'worker_off';
                $queueMessage = $workerEnabled
                    ? "Queue {$connection} aktif, backlog {$backlog}."
                    : "Queue {$connection} aktif, backlog {$backlog}, tetapi worker dimatikan.";
            } catch (\Throwable $e) {
                $queueStatus = 'error';
                $queueMessage = $e->getMessage();
            }
        }

        return [
            'dispatch_mode' => MobileIngestRuntime::dispatchMode(),
            'storage_disk' => MobileIngestRuntime::storageDisk('local'),
            'cache_store' => MobileIngestRuntime::cacheStore('file'),
            'lock_store' => MobileIngestRuntime::lockStore(MobileIngestRuntime::cacheStore('file')),
            'queue_connection' => $connection,
            'queue_name' => $queueName,
            'queue_backlog' => $backlog,
            'queue_status' => $queueStatus,
            'queue_message' => $queueMessage,
            'worker_enabled' => $workerEnabled,
            'uses_async_queue' => $usesAsyncQueue,
            'upload_monitoring' => $this->buildUploadMonitoringSummary(),
        ];
    }

    private function buildUploadMonitoringSummary(): array
    {
        if (! Schema::hasTable('mobile_upload_batches')) {
            return [
                'enabled' => false,
                'message' => 'Tabel monitoring upload belum tersedia.',
            ];
        }

        $since = Carbon::now()->subHour();
        $byStatus = MobileUploadBatch::query()
            ->where('received_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $workers = [];
        if (Schema::hasTable('worker_heartbeats')) {
            $workers = WorkerHeartbeat::query()
                ->orderByDesc('last_seen_at')
                ->limit(5)
                ->get(['worker_name', 'status', 'current_upload_id', 'last_seen_at', 'processed_count', 'failed_count'])
                ->map(fn (WorkerHeartbeat $worker): array => [
                    'worker_name' => $worker->worker_name,
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
            'last_hour_by_status' => $byStatus,
            'pending' => (int) ($byStatus['received'] ?? 0) + (int) ($byStatus['queued'] ?? 0),
            'processing' => (int) ($byStatus['processing'] ?? 0),
            'completed' => (int) ($byStatus['completed'] ?? 0),
            'failed' => (int) ($byStatus['failed'] ?? 0),
            'workers' => $workers,
        ];
    }

    /**
     * Pantau aktivitas user/MAC: siapa yang sedang aktif, menu apa yang diakses terakhir.
     */
    public function users(): JsonResponse
    {
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $requests = $cache->get('recent_requests', []);

        // Kelompokkan per MAC address. Jika tidak ada MAC, pakai IP sebagai fallback.
        $byMac = [];
        foreach ($requests as $req) {
            $mac  = !empty($req['mac']) ? $req['mac'] : ('ip:' . ($req['ip'] ?? 'unknown'));
            $key  = $mac;
            if (!isset($byMac[$key])) {
                $ip = (string) ($req['ip'] ?? '');
                $byMac[$key] = [
                    'mac'         => $mac,
                    'user_id'     => $req['user_id'] ?? null,
                    'user_name'   => null,
                    'last_login_at' => null,
                    'last_login_ip' => null,
                    'last_ip'     => $ip !== '' ? $ip : null,
                    'ip_scope'    => $this->classifyIpScope($ip),
                    'network_type'=> $this->networkTypeFromScope($this->classifyIpScope($ip)),
                    'last_seen'   => $req['time'] ?? null,
                    'last_route'  => $req['route'] ?? $req['uri'] ?? null,
                    'app_version' => $this->normalizeAppVersion($req['app_version'] ?? null),
                    'last_upload_at' => null,
                    'last_upload_route' => null,
                    'last_method' => $req['method'] ?? null,
                    'last_status' => $req['status'] ?? null,
                    'last_ms'     => $req['duration_ms'] ?? null,
                    'speed_kbps_est' => $req['speed_kbps_est'] ?? null,
                    'speed_tier'  => $this->speedTierFromMs((float) ($req['duration_ms'] ?? 0)),
                    'request_count' => 0,
                    'error_count'   => 0,
                    'routes'        => [],
                ];
            }
            $byMac[$key]['request_count']++;
            $status = (int) ($req['status'] ?? 0);
            if ($status >= 400) {
                $byMac[$key]['error_count']++;
            }
            $currentIp = (string) ($req['ip'] ?? '');
            if ($currentIp !== '') {
                $byMac[$key]['last_ip'] = $currentIp;
                $byMac[$key]['ip_scope'] = $this->classifyIpScope($currentIp);
                $byMac[$key]['network_type'] = $this->networkTypeFromScope($byMac[$key]['ip_scope']);
            }
            $ms = (float) ($req['duration_ms'] ?? 0);
            if ($ms > 0) {
                $byMac[$key]['last_ms'] = $ms;
                $byMac[$key]['speed_tier'] = $this->speedTierFromMs($ms);
            }
            if (isset($req['speed_kbps_est']) && $req['speed_kbps_est'] !== null) {
                $byMac[$key]['speed_kbps_est'] = (float) $req['speed_kbps_est'];
            }
            $route = (string) ($req['route'] ?? Str::after($req['uri'] ?? '', '/api/'));
            if (in_array($route, ['summary', 'detail'], true)) {
                $byMac[$key]['last_upload_at'] = $req['time'] ?? $byMac[$key]['last_upload_at'];
                $byMac[$key]['last_upload_route'] = $route;
                $byMac[$key]['app_version'] = $this->normalizeAppVersion($req['app_version'] ?? $byMac[$key]['app_version']);
            }
            // Kumpulkan riwayat route unik (max 5)
            if ($route && !in_array($route, $byMac[$key]['routes'], true) && count($byMac[$key]['routes']) < 5) {
                $byMac[$key]['routes'][] = $route;
            }
        }

        $this->attachUserNames($byMac);

        // Merge network report from mobile side (if available).
        foreach ($byMac as $idx => $row) {
            $report = $this->resolveMobileNetworkReport(
                isset($row['user_id']) ? (int) $row['user_id'] : null,
                (string) ($row['mac'] ?? ''),
                (string) ($row['last_ip'] ?? '')
            );
            if (!empty($report)) {
                $byMac[$idx]['network_type_mobile'] = $report['network_type'] ?? null;
                $byMac[$idx]['network_metered_mobile'] = $report['is_metered'] ?? null;
                $byMac[$idx]['downlink_mbps_mobile'] = $report['downlink_mbps'] ?? null;
                $byMac[$idx]['uplink_mbps_mobile'] = $report['uplink_mbps'] ?? null;
                $byMac[$idx]['rtt_ms_mobile'] = $report['rtt_ms'] ?? null;
                $byMac[$idx]['network_reported_at'] = $report['reported_at'] ?? null;
                $byMac[$idx]['network_source'] = 'mobile_report';
            } else {
                $byMac[$idx]['network_source'] = 'server_estimate';
            }
        }

        // Urutkan: paling baru aktif dulu
        usort($byMac, fn($a, $b) => strcmp($b['last_seen'] ?? '', $a['last_seen'] ?? ''));

        $all    = array_values($byMac);
        $top20  = array_slice($all, 0, 20);

        return response()->json([
            'active_users' => $top20,
            'total'        => count($all),
            'showing'      => count($top20),
            'snapshot_at'  => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attachUserNames(array &$rows): void
    {
        $userIds = [];
        foreach ($rows as $row) {
            $uid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            if ($uid > 0) {
                $userIds[] = $uid;
            }
        }
        $userIds = array_values(array_unique($userIds));
        if (empty($userIds)) {
            return;
        }

        $columns = ['id', 'name'];
        $hasLastLoginAt = Schema::hasColumn('users', 'last_login_at');
        $hasLastLoginIp = Schema::hasColumn('users', 'last_login_ip');
        if ($hasLastLoginAt) {
            $columns[] = 'last_login_at';
        }
        if ($hasLastLoginIp) {
            $columns[] = 'last_login_ip';
        }

        $users = User::query()
            ->select($columns)
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        foreach ($rows as &$row) {
            $uid = isset($row['user_id']) ? (int) $row['user_id'] : 0;
            if ($uid > 0) {
                $user = $users->get($uid);
                $row['user_name'] = $user?->name;
                if ($hasLastLoginAt) {
                    $row['last_login_at'] = optional($user?->last_login_at)->toDateTimeString();
                }
                if ($hasLastLoginIp) {
                    $row['last_login_ip'] = $user?->last_login_ip;
                }
            }
        }
        unset($row);
    }

    private function normalizeAppVersion(mixed $value): string
    {
        if (!is_string($value)) {
            return 'N/A';
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : 'N/A';
    }

    private function classifyIpScope(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return 'unknown';
        }

        // Loopback and localhost aliases.
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'local';
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }

        $isPrivateOrReserved = !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $isPrivateOrReserved ? 'local' : 'public';
    }

    private function networkTypeFromScope(string $scope): string
    {
        return match ($scope) {
            'public' => 'public',
            'local' => 'wifi/local',
            default => 'unknown',
        };
    }

    private function speedTierFromMs(float $durationMs): string
    {
        if ($durationMs <= 0) {
            return 'unknown';
        }
        if ($durationMs <= 180) {
            return 'very_fast';
        }
        if ($durationMs <= 350) {
            return 'fast';
        }
        if ($durationMs <= 700) {
            return 'medium';
        }

        return 'slow';
    }

    private function resolveMobileNetworkReport(?int $userId, string $mac, string $ip): array
    {
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $keys = [];
        if ($userId !== null && $userId > 0) {
            $keys[] = 'mobile_network_report:user:' . $userId;
        }
        $mac = trim($mac);
        if ($mac !== '' && !str_starts_with($mac, 'ip:')) {
            $keys[] = 'mobile_network_report:mac:' . strtoupper($mac);
        }
        $ip = trim($ip);
        if ($ip !== '') {
            $keys[] = 'mobile_network_report:ip:' . $ip;
        }

        foreach ($keys as $key) {
            $report = $cache->get($key);
            if (is_array($report) && !empty($report)) {
                return $report;
            }
        }

        return [];
    }
}
