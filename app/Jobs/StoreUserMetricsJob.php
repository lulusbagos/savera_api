<?php

namespace App\Jobs;

use App\Services\MobileMetricFileWriter;
use App\Services\MobileMetricPayloadNormalizer;
use App\Support\MobileIngestRuntime;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreUserMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    private const CACHE_STORE = 'file';
    private const CACHE_TTL_SECONDS = 86400;
    private const WRITE_LOCK_SECONDS = 15;
    private const WRITE_LOCK_WAIT_SECONDS = 5;

    public function backoff(): array
    {
        return [1, 2, 5, 10, 20];
    }

    /**
     * @param array<int, array{path: string, contents: string, bucket?: string}> $files
     * @param string $source
     */
    public function __construct(protected array $files, protected string $source)
    {
        $this->onConnection(MobileIngestRuntime::queueConnection());
        $this->onQueue(MobileIngestRuntime::queueName());
    }

    public function handle(): void
    {
        $successfulWrites = 0;
        $failedWrites = 0;
        $durations = [];
        $failedPaths = [];

        foreach ($this->files as $file) {
            $start = microtime(true);

            try {
                $stored = $this->storeMetricFile($file);
                $duration = round((microtime(true) - $start) * 1000, 2);

                Log::debug($stored ? 'Metrics stored' : 'Metrics unchanged', [
                    'source' => $this->source,
                    'path' => $file['path'],
                    'duration_ms' => $duration,
                ]);

                // Tetap rekam durasi write-check meskipun payload tidak berubah,
                // agar panel "Storage Write Speed" tetap punya data realtime.
                $durations[] = $duration;

                if ($stored) {
                    $successfulWrites++;
                }
            } catch (Throwable $e) {
                Log::error('Failed storing user metrics', [
                    'source' => $this->source,
                    'path' => $file['path'],
                    'message' => $e->getMessage(),
                ]);

                $failedWrites++;
                $failedPaths[] = (string) ($file['path'] ?? '');
            }
        }

        $this->recordStorageMetrics($successfulWrites, $failedWrites, $durations);

        if ($failedWrites > 0) {
            throw new \RuntimeException(
                'Metric write failed for ' . $failedWrites . ' file(s): ' . implode(', ', array_slice($failedPaths, 0, 3))
            );
        }
    }

    /**
     * Tulis file secara atomik dan hindari write ulang kalau payload sama.
     */
    private function storeMetricFile(array $file): bool
    {
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $lockName = 'metric-write:' . sha1($file['path']);
        $hashKey = 'metric-hash:' . sha1($file['path']);

        try {
            return $cache->lock($lockName, self::WRITE_LOCK_SECONDS)->block(
                self::WRITE_LOCK_WAIT_SECONDS,
                function () use ($cache, $file, $hashKey): bool {
                    $writer = app(MobileMetricFileWriter::class);
                    $contents = (string) ($file['contents'] ?? '[]');
                    $bucket = (string) ($file['bucket'] ?? '');
                    if ($bucket !== '') {
                        $contents = app(MobileMetricPayloadNormalizer::class)
                            ->normalizeForBucket($bucket, $contents);
                    } else {
                        $contents = $writer->normalize($contents);
                    }
                    $hash = hash('sha256', $contents);
                    $existingHash = $cache->get($hashKey);

                    if (is_string($existingHash) && hash_equals($existingHash, $hash) && $writer->exists($file['path'])) {
                        return false;
                    }

                    $writer->write($file['path'], $contents);
                    $cache->put($hashKey, $hash, self::CACHE_TTL_SECONDS);

                    return true;
                }
            );
        } catch (LockTimeoutException $e) {
            Log::warning('Metric write lock timeout', [
                'path' => $file['path'],
                'source' => $this->source,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Simpan metrik tulis storage ke cache file store,
     * agar tidak menambah beban database saat volume request tinggi.
     */
    private function recordStorageMetrics(int $successfulWrites, int $failedWrites, array $durations): void
    {
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        try {
            $cache->lock('storage-stats', self::WRITE_LOCK_SECONDS)->block(
                self::WRITE_LOCK_WAIT_SECONDS,
                function () use ($cache, $successfulWrites, $failedWrites, $durations): void {
                    $stats = $cache->get('storage_stats', ['writes' => 0, 'errors' => 0]);
                    $stats['writes'] = ($stats['writes'] ?? 0) + $successfulWrites;
                    $stats['errors'] = ($stats['errors'] ?? 0) + $failedWrites;
                    $cache->put('storage_stats', $stats, self::CACHE_TTL_SECONDS);

                    if (!empty($durations)) {
                        $cachedDurations = $cache->get('storage_durations', []);
                        $durations = array_slice(array_merge($durations, $cachedDurations), 0, 200);
                        $cache->put('storage_durations', $durations, self::CACHE_TTL_SECONDS);
                    }
                }
            );
        } catch (LockTimeoutException $e) {
            Log::warning('Storage metrics lock timeout', [
                'source' => $this->source,
                'message' => $e->getMessage(),
            ]);
        }
    }

}
