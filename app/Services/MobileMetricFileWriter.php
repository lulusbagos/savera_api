<?php

namespace App\Services;

use App\Support\MobileIngestRuntime;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class MobileMetricFileWriter
{
    public function write(string $relativePath, string $contents): void
    {
        $disk = Storage::disk(MobileIngestRuntime::storageDisk());
        $relativePath = ltrim($relativePath, '/\\');
        $incomingContents = $this->normalize($contents);
        $existingContents = $disk->exists($relativePath) ? (string) $disk->get($relativePath) : '';
        $normalizedContents = $this->mergeJsonContents($existingContents, $incomingContents, $relativePath);
        $hash = hash('sha256', $normalizedContents);
        $tempPath = $this->buildTemporaryPath($relativePath, $hash);
        $backupPath = $this->buildBackupPath($relativePath);
        $directory = trim(dirname($relativePath), '/\\');

        if ($directory !== '' && $directory !== '.') {
            $disk->makeDirectory($directory);
        }

        if (! $disk->put($tempPath, $normalizedContents)) {
            throw new \RuntimeException("Failed writing temp metric file for {$relativePath}");
        }

        $hadExistingTarget = $disk->exists($relativePath);
        if ($hadExistingTarget && ! $disk->copy($relativePath, $backupPath)) {
            $disk->delete($tempPath);
            throw new \RuntimeException("Failed creating backup metric file for {$relativePath}");
        }

        try {
            $tempContents = $disk->get($tempPath);
            if (! $disk->put($relativePath, $tempContents)) {
                throw new \RuntimeException("Failed moving metric file into place for {$relativePath}");
            }

            if (! $disk->exists($relativePath) || hash('sha256', (string) $disk->get($relativePath)) !== $hash) {
                throw new \RuntimeException("Failed verifying metric file for {$relativePath}");
            }
        } catch (Throwable $e) {
            if ($hadExistingTarget && $disk->exists($backupPath)) {
                $disk->copy($backupPath, $relativePath);
            } elseif (! $hadExistingTarget && $disk->exists($relativePath)) {
                $disk->delete($relativePath);
            }

            throw $e;
        } finally {
            $disk->delete([$tempPath, $backupPath]);
        }
    }

    public function exists(string $relativePath): bool
    {
        return Storage::disk(MobileIngestRuntime::storageDisk())->exists(ltrim($relativePath, '/\\'));
    }

    public function normalize(string $contents): string
    {
        return $this->normalizeJson($contents);
    }

    private function buildTemporaryPath(string $path, string $hash): string
    {
        $directory = trim(dirname($path), '/\\');
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $tempName = '.tmp-' . $filename . '-' . substr($hash, 0, 12) . '.json';

        return ($directory === '.' ? '' : $directory . '/') . $tempName;
    }

    private function buildBackupPath(string $path): string
    {
        $directory = trim(dirname($path), '/\\');
        $filename = basename($path);
        $backupName = '.bak-' . $filename;

        return ($directory === '.' ? '' : $directory . '/') . $backupName;
    }

    private function normalizeJson(string $contents): string
    {
        $contents = trim($contents);
        if ($contents === '') {
            return '[]';
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return '[]';
            }

            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '[]';
        }
    }

    private function mergeJsonContents(string $existing, string $incoming, string $relativePath = ''): string
    {
        $existingList = $this->decodeToList($existing);
        $incomingList = $this->decodeToList($incoming);

        if ($incomingList === []) {
            return json_encode($existingList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if ($this->isSleepBucketPath($relativePath)) {
            // For sleep sessions we keep latest snapshot only.
            // Merging historical snapshots tends to accumulate overlapping sessions.
            return json_encode($incomingList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if ($existingList === []) {
            return json_encode($incomingList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $merged = [];
        $indexByKey = [];

        foreach ($existingList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = $this->rowMergeKey($row);
            $indexByKey[$key] = count($merged);
            $merged[] = $row;
        }

        foreach ($incomingList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = $this->rowMergeKey($row);
            if (array_key_exists($key, $indexByKey)) {
                $merged[$indexByKey[$key]] = $row;
                continue;
            }
            $indexByKey[$key] = count($merged);
            $merged[] = $row;
        }

        usort($merged, function (array $a, array $b): int {
            $at = $this->rowSortTimestamp($a);
            $bt = $this->rowSortTimestamp($b);
            if ($at === $bt) {
                return strcmp($this->rowMergeKey($a), $this->rowMergeKey($b));
            }
            return $at <=> $bt;
        });

        return json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function decodeToList(string $contents): array
    {
        $contents = trim($contents);
        if ($contents === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            if ($decoded === []) {
                return [];
            }

            $isAssoc = !array_is_list($decoded);
            if ($isAssoc) {
                if (isset($decoded['data']) && is_array($decoded['data'])) {
                    return $decoded['data'];
                }
                return [$decoded];
            }

            return $decoded;
        } catch (JsonException) {
            return [];
        }
    }

    private function rowSortTimestamp(array $row): int
    {
        $timestamp = $this->extractTimestamp($row);
        if ($timestamp <= 0) {
            return PHP_INT_MAX;
        }
        return $timestamp;
    }

    private function rowMergeKey(array $row): string
    {
        $sleepStart = $this->normalizeTimestamp((int) ($row['sleepStart'] ?? $row['sleep_start'] ?? 0));
        $sleepEnd = $this->normalizeTimestamp((int) ($row['sleepEnd'] ?? $row['sleep_end'] ?? 0));
        if ($sleepStart > 0 && $sleepEnd > 0) {
            $startBucket = (int) (floor($sleepStart / 60) * 60);
            $endBucket = (int) (ceil($sleepEnd / 60) * 60);
            return 'sleep:' . $startBucket . '-' . $endBucket;
        }

        $timestamp = $this->extractTimestamp($row);
        if ($timestamp > 0) {
            return 'ts:' . $timestamp . '|sig:' . sha1(
                json_encode(
                    $this->recursiveKeySort($this->stripTimeFields($row)),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );
        }

        return 'row:' . sha1(json_encode($this->recursiveKeySort($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function stripTimeFields(array $row): array
    {
        unset(
            $row['timestamp'],
            $row['ts'],
            $row['time'],
            $row['device_time'],
            $row['sleepStart'],
            $row['sleep_start'],
            $row['sleepEnd'],
            $row['sleep_end']
        );

        return $row;
    }

    private function extractTimestamp(array $row): int
    {
        $candidates = [
            $row['timestamp'] ?? null,
            $row['device_time'] ?? null,
            $row['time'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return $this->normalizeTimestamp((int) $candidate);
            }
            if (is_string($candidate) && $candidate !== '') {
                $ts = strtotime($candidate);
                if ($ts !== false && $ts > 0) {
                    return $ts;
                }
            }
        }

        return 0;
    }

    private function normalizeTimestamp(int $ts): int
    {
        if ($ts > 9999999999) {
            return (int) floor($ts / 1000);
        }
        return $ts;
    }

    private function recursiveKeySort(array $value): array
    {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->recursiveKeySort($v);
            }
        }

        ksort($value);
        return $value;
    }

    private function isSleepBucketPath(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', strtolower(ltrim($relativePath, '/')));
        return str_starts_with($normalized, 'data_sleep/');
    }
}
