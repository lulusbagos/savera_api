<?php

namespace App\Http\Controllers;

use App\Support\MobileIngestRuntime;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class LogDashboardController extends Controller
{
    private const CACHE_STORE = 'file';

    public function stream(): JsonResponse
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
            } elseif (Str::contains($lower, 'metrics stored')) {
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
        $storageDurations = array_slice(array_reverse($storageDurations), 0, 60);
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $requests = $cache->get('recent_requests', []);

        // Fallback & penambahan dari cache file store untuk mengurangi beban database.
        $cachedDurations = $cache->get('storage_durations', []);
        if (!empty($cachedDurations)) {
            $storageDurations = array_slice(array_merge($cachedDurations, $storageDurations), 0, 60);
        }

        $cachedStats = $cache->get('storage_stats', []);
        if (!empty($cachedStats)) {
            $summary['storage_writes'] = max($summary['storage_writes'], $cachedStats['writes'] ?? 0);
            $summary['storage_errors'] = max($summary['storage_errors'], $cachedStats['errors'] ?? 0);
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
                }
            }

            if (!in_array($route, ['summary', 'detail'], true)) {
                continue;
            }

            $summary['upload_recent_total']++;
            $status = (int) ($request['status'] ?? 0);
            $isSuccess = $status > 0 && $status < 400;
            $durations[] = (float) ($request['duration_ms'] ?? 0.0);

            if ($route === 'summary') {
                $summary[$isSuccess ? 'upload_summary_ok' : 'upload_summary_failed']++;
            } else {
                $summary[$isSuccess ? 'upload_detail_ok' : 'upload_detail_failed']++;
            }

            if ($summary['upload_last_time'] === null) {
                $summary['upload_last_time'] = $request['time'] ?? null;
                $summary['upload_last_route'] = $route;
            }
        }

        $failed = $summary['upload_summary_failed'] + $summary['upload_detail_failed'];
        if ($summary['upload_recent_total'] > 0) {
            $summary['upload_fail_rate'] = round(($failed / $summary['upload_recent_total']) * 100, 1);
            $summary['upload_avg_ms'] = round(array_sum($durations) / count($durations), 2);
        }

        return $summary;
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
                $byMac[$key] = [
                    'mac'         => $mac,
                    'user_id'     => $req['user_id'] ?? null,
                    'last_seen'   => $req['time'] ?? null,
                    'last_route'  => $req['route'] ?? $req['uri'] ?? null,
                    'last_method' => $req['method'] ?? null,
                    'last_status' => $req['status'] ?? null,
                    'last_ms'     => $req['duration_ms'] ?? null,
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
            // Kumpulkan riwayat route unik (max 5)
            $route = $req['route'] ?? Str::after($req['uri'] ?? '', '/api/');
            if ($route && !in_array($route, $byMac[$key]['routes'], true) && count($byMac[$key]['routes']) < 5) {
                $byMac[$key]['routes'][] = $route;
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
}
