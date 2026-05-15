<?php

namespace App\Http\Controllers;

use App\Models\MobileUploadBatch;
use App\Models\MobileUploadChunk;
use App\Models\WorkerHeartbeat;
use App\Support\MobileIngestRuntime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MobileUploadMonitoringController extends Controller
{
    public function index()
    {
        return view('mobile-upload-monitoring');
    }

    public function stream(Request $request): JsonResponse
    {
        if (! $this->safeHasTable('mobile_upload_batches')) {
            return response()->json([
                'enabled' => false,
                'message' => 'Tabel mobile_upload_batches belum tersedia. Jalankan migration API terlebih dahulu.',
                'snapshot_at' => Carbon::now('Asia/Makassar')->toDateTimeString(),
            ]);
        }

        $windowMinutes = max(15, min(1440, (int) $request->query('window_minutes', 360)));
        $since = Carbon::now('Asia/Makassar')->subMinutes($windowMinutes);

        $statusRows = MobileUploadBatch::query()
            ->where(function ($query) use ($since) {
                $query->where('received_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(payload_bytes_total), 0) as bytes_total')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        $bytesByStatus = [];
        foreach ($statusRows as $row) {
            $status = (string) ($row->status ?: 'unknown');
            $byStatus[$status] = (int) $row->total;
            $bytesByStatus[$status] = (int) $row->bytes_total;
        }

        $recentBatches = MobileUploadBatch::query()
            ->withCount('chunks')
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(fn (MobileUploadBatch $batch): array => $this->formatBatch($batch))
            ->values();

        $recentChunks = collect();
        if ($this->safeHasTable('mobile_upload_chunks')) {
            $recentChunks = MobileUploadChunk::query()
                ->orderByDesc('received_at')
                ->orderByDesc('created_at')
                ->limit(40)
                ->get()
                ->map(fn (MobileUploadChunk $chunk): array => $this->formatChunk($chunk))
                ->values();
        }
        $storageWrite = $this->storageWriteSummary($recentChunks->all(), $recentBatches->all());

        return response()->json([
            'enabled' => true,
            'snapshot_at' => Carbon::now('Asia/Makassar')->toDateTimeString(),
            'window_minutes' => $windowMinutes,
            'summary' => [
                'total' => array_sum($byStatus),
                'received' => (int) ($byStatus['received'] ?? 0),
                'queued' => (int) ($byStatus['queued'] ?? 0),
                'processing' => (int) ($byStatus['processing'] ?? 0),
                'completed' => (int) ($byStatus['completed'] ?? 0),
                'failed' => (int) ($byStatus['failed'] ?? 0),
                'pending' => (int) ($byStatus['received'] ?? 0) + (int) ($byStatus['queued'] ?? 0),
                'bytes_total' => array_sum($bytesByStatus),
                'by_status' => $byStatus,
            ],
            'operations' => $this->operations(),
            'storage_write' => $storageWrite,
            'workers' => $this->workers(),
            'recent_batches' => $recentBatches,
            'recent_chunks' => $recentChunks,
        ]);
    }

    private function operations(): array
    {
        $connection = MobileIngestRuntime::queueConnection('sync');
        $queueName = MobileIngestRuntime::queueName('mobile-metrics');
        $backlog = null;
        $queueStatus = $connection === 'sync' ? 'sync' : 'unknown';
        $queueMessage = $connection === 'sync'
            ? 'Metric JSON diproses langsung di request utama.'
            : 'Queue aktif, menunggu pembacaan backlog.';

        if (MobileIngestRuntime::usesAsyncQueue()) {
            try {
                $backlog = Queue::connection($connection)->size($queueName);
                $queueStatus = MobileIngestRuntime::workerEnabled() ? 'ready' : 'worker_off';
                $queueMessage = MobileIngestRuntime::workerEnabled()
                    ? "Queue {$connection}:{$queueName} ready, backlog {$backlog}."
                    : "Queue {$connection}:{$queueName} aktif, tetapi worker flag off.";
            } catch (Throwable $e) {
                $queueStatus = 'error';
                $queueMessage = $e->getMessage();
            }
        }

        return [
            'dispatch_mode' => MobileIngestRuntime::dispatchMode(),
            'queue_connection' => $connection,
            'queue_name' => $queueName,
            'queue_backlog' => $backlog,
            'queue_status' => $queueStatus,
            'queue_message' => $queueMessage,
            'worker_enabled' => MobileIngestRuntime::workerEnabled(),
            'uses_async_queue' => MobileIngestRuntime::usesAsyncQueue(),
            'storage_disk' => MobileIngestRuntime::storageDisk('local'),
            'cache_store' => MobileIngestRuntime::cacheStore('file'),
        ];
    }

    private function workers(): array
    {
        if (! $this->safeHasTable('worker_heartbeats')) {
            return [];
        }

        return WorkerHeartbeat::query()
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get()
            ->map(function (WorkerHeartbeat $worker): array {
                $lastSeen = $worker->last_seen_at;
                $ageSeconds = $lastSeen ? $lastSeen->diffInSeconds(Carbon::now('Asia/Makassar'), false) : null;

                return [
                    'worker_name' => $worker->worker_name,
                    'queue_connection' => $worker->queue_connection,
                    'queue_name' => $worker->queue_name,
                    'status' => $worker->status,
                    'current_upload_id' => $worker->current_upload_id,
                    'current_source' => $worker->current_source,
                    'processed_count' => (int) $worker->processed_count,
                    'failed_count' => (int) $worker->failed_count,
                    'last_seen_at' => optional($lastSeen)->toDateTimeString(),
                    'age_seconds' => $ageSeconds,
                    'fresh' => $ageSeconds !== null && $ageSeconds <= 900,
                ];
            })
            ->values()
            ->all();
    }

    private function formatBatch(MobileUploadBatch $batch): array
    {
        $extra = is_array($batch->extra_json) ? $batch->extra_json : [];
        $receivedAt = $batch->received_at ?: $batch->created_at;
        $completedAt = $batch->completed_at;
        $failedAt = $batch->failed_at;
        $finishedAt = $completedAt ?: $failedAt;

        return [
            'id' => $batch->id,
            'upload_id' => $batch->upload_id,
            'upload_id_short' => Str::limit((string) $batch->upload_id, 18, ''),
            'source' => $batch->source,
            'company_id' => $batch->company_id,
            'user_id' => $batch->user_id,
            'employee_id' => $batch->employee_id,
            'device_id' => $batch->device_id,
            'upload_date' => optional($batch->upload_date)->toDateString(),
            'status' => $batch->status,
            'chunks_received' => (int) $batch->chunks_received,
            'chunks_total' => (int) $batch->chunks_total,
            'chunks_count' => (int) ($batch->chunks_count ?? 0),
            'payload_bytes_total' => (int) $batch->payload_bytes_total,
            'payload_mb' => round(((int) $batch->payload_bytes_total) / 1048576, 3),
            'summary_id' => $batch->summary_id,
            'app_version' => $extra['app_version'] ?? null,
            'mac_address' => $extra['mac_address'] ?? null,
            'sleep_type' => $extra['sleep_type'] ?? null,
            'device_time' => $extra['device_time'] ?? null,
            'dispatch_mode' => $extra['dispatch_mode'] ?? null,
            'storage_write' => $extra['storage_write'] ?? null,
            'received_at' => optional($receivedAt)->toDateTimeString(),
            'queued_at' => optional($batch->queued_at)->toDateTimeString(),
            'processing_started_at' => optional($batch->processing_started_at)->toDateTimeString(),
            'completed_at' => optional($completedAt)->toDateTimeString(),
            'failed_at' => optional($failedAt)->toDateTimeString(),
            'last_chunk_at' => optional($batch->last_chunk_at)->toDateTimeString(),
            'duration_seconds' => $receivedAt && $finishedAt ? $receivedAt->diffInSeconds($finishedAt, false) : null,
            'age_seconds' => $receivedAt ? $receivedAt->diffInSeconds(Carbon::now('Asia/Makassar'), false) : null,
            'error_code' => $batch->error_code,
            'error_message' => Str::limit((string) $batch->error_message, 180),
        ];
    }

    private function formatChunk(MobileUploadChunk $chunk): array
    {
        $writeDurationMs = null;
        if ($chunk->processing_started_at && $chunk->processed_at) {
            $writeDurationMs = $this->durationMs($chunk->processing_started_at, $chunk->processed_at, null);
        }

        return [
            'id' => $chunk->id,
            'upload_id' => $chunk->upload_id,
            'upload_id_short' => Str::limit((string) $chunk->upload_id, 18, ''),
            'source' => $chunk->source,
            'chunk_index' => (int) $chunk->chunk_index,
            'chunk_count' => (int) $chunk->chunk_count,
            'status' => $chunk->status,
            'payload_size' => (int) $chunk->payload_size,
            'payload_kb' => round(((int) $chunk->payload_size) / 1024, 1),
            'storage_path' => $chunk->storage_path,
            'received_at' => optional($chunk->received_at)->toDateTimeString(),
            'processed_at' => optional($chunk->processed_at)->toDateTimeString(),
            'failed_at' => optional($chunk->failed_at)->toDateTimeString(),
            'write_duration_ms' => $writeDurationMs,
            'error_code' => $chunk->error_code,
            'error_message' => Str::limit((string) $chunk->error_message, 120),
        ];
    }

    /**
     * Ringkasan kecepatan tulis storage. Utama dari tabel chunks, fallback dari
     * cache file-store yang ditulis middleware/request monitoring.
     *
     * @param array<int, array<string, mixed>> $chunks
     * @param array<int, array<string, mixed>> $batches
     * @return array<string, mixed>
     */
    private function storageWriteSummary(array $chunks, array $batches = []): array
    {
        $durations = [];
        $source = 'cache';
        $sourceLabel = 'write cache';

        try {
            $cached = Cache::store(MobileIngestRuntime::cacheStore('file'))->get('storage_durations', []);
            if (is_array($cached)) {
                foreach ($cached as $value) {
                    if (is_numeric($value) && (float) $value > 0) {
                        $durations[] = round((float) $value, 2);
                    }
                }
            }
        } catch (Throwable) {
            // Cache hanya fallback; jangan gagalkan dashboard kalau store error.
        }

        if (empty($durations)) {
            $source = 'batch';
            $sourceLabel = 'persistent batch';
            foreach ($batches as $batch) {
                $write = $batch['storage_write'] ?? null;
                if (!is_array($write)) {
                    continue;
                }
                $samples = $write['samples'] ?? [];
                if (is_array($samples) && !empty($samples)) {
                    foreach ($samples as $value) {
                        if (is_numeric($value) && (float) $value > 0) {
                            $durations[] = round((float) $value, 2);
                        }
                    }
                    continue;
                }
                foreach (['last_ms', 'avg_ms', 'max_ms'] as $key) {
                    $value = $write[$key] ?? null;
                    if (is_numeric($value) && (float) $value > 0) {
                        $durations[] = round((float) $value, 2);
                        break;
                    }
                }
            }
        }

        if (empty($durations)) {
            $source = 'worker_process';
            $sourceLabel = 'estimasi proses worker';
            foreach ($chunks as $chunk) {
                $value = $chunk['write_duration_ms'] ?? null;
                if ($value !== null && is_numeric($value) && (float) $value > 0) {
                    $durations[] = round(min((float) $value, 60000.0), 2);
                }
            }
        }

        $durations = array_values(array_slice($durations, 0, 60));
        $count = count($durations);
        $avg = $count > 0 ? round(array_sum($durations) / $count, 2) : null;
        $max = $count > 0 ? round(max($durations), 2) : null;
        $last = $durations[0] ?? null;
        $health = 'waiting';
        if ($avg !== null) {
            if ($source === 'worker_process') {
                $health = $avg > 5000 ? 'slow' : ($avg > 2000 ? 'watch' : 'good');
            } else {
                $health = $avg > 80 ? 'slow' : ($avg > 30 ? 'watch' : 'good');
            }
        }

        return [
            'count' => $count,
            'avg_ms' => $avg,
            'last_ms' => $last,
            'max_ms' => $max,
            'health' => $health,
            'source' => $source,
            'source_label' => $sourceLabel,
            'samples' => $durations,
        ];
    }

    private function durationMs($start, $end, ?float $fallback): ?float
    {
        try {
            if (! $start || ! $end) {
                return $fallback;
            }

            $startMs = (float) Carbon::parse($start)->valueOf();
            $endMs = (float) Carbon::parse($end)->valueOf();

            return round(min(max($endMs - $startMs, $fallback ?? 0.0), 60000.0), 2);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
