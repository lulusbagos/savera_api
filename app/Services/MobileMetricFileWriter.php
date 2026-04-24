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
        $normalizedContents = $this->normalize($contents);
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
}
