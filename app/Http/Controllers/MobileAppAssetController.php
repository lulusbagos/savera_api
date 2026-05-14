<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class MobileAppAssetController extends Controller
{
    private const APK_EXTENSIONS = ['apk'];
    private const SPLASH_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    public function update(Request $request)
    {
        $apk = $this->latestFile(base_path('apk'), self::APK_EXTENSIONS);

        if (! $apk) {
            return response()->json([
                'available' => false,
                'message' => 'Belum ada file APK di server.',
            ]);
        }

        $metadata = $this->readMetadata($apk);
        $latestVersionCode = $this->intOrNull($metadata['version_code'] ?? null)
            ?? $this->versionCodeFromFilename($apk);
        $currentVersionCode = $this->intOrNull($request->query('version_code'));
        $updateAvailable = $latestVersionCode !== null && $currentVersionCode !== null
            ? $latestVersionCode > $currentVersionCode
            : true;

        return response()->json([
            'available' => true,
            'update_available' => $updateAvailable,
            'latest' => [
                'filename' => basename($apk),
                'version_code' => $latestVersionCode,
                'version_name' => $metadata['version_name'] ?? $this->versionNameFromFilename($apk),
                'notes' => $metadata['notes'] ?? null,
                'size_bytes' => filesize($apk),
                'size_text' => $this->formatBytes((int) filesize($apk)),
                'updated_at' => date(DATE_ATOM, filemtime($apk)),
                'download_url' => $this->absoluteApiUrl('/api/mobile/app-update-file') . '?v=' . filemtime($apk),
            ],
            'current' => [
                'version_code' => $currentVersionCode,
                'version_name' => $request->query('version_name'),
            ],
        ]);
    }

    public function download()
    {
        $apk = $this->latestFile(base_path('apk'), self::APK_EXTENSIONS);

        if (! $apk) {
            return response()->json([
                'message' => 'Belum ada file APK di server.',
            ], 404);
        }

        return Response::download($apk, basename($apk), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    public function splash()
    {
        $image = $this->latestFile(base_path('splash'), self::SPLASH_EXTENSIONS);

        if (! $image) {
            return response()->json([
                'available' => false,
                'message' => 'Belum ada file splash PNG/JPG di server.',
            ]);
        }

        return response()->json([
            'available' => true,
            'image' => [
                'filename' => basename($image),
                'size_bytes' => filesize($image),
                'size_text' => $this->formatBytes((int) filesize($image)),
                'updated_at' => date(DATE_ATOM, filemtime($image)),
                'image_url' => $this->absoluteApiUrl('/api/mobile/splash/image') . '?v=' . filemtime($image),
            ],
        ]);
    }

    public function splashImage()
    {
        $image = $this->latestFile(base_path('splash'), self::SPLASH_EXTENSIONS);

        if (! $image) {
            return response()->json([
                'message' => 'Belum ada file splash PNG/JPG di server.',
            ], 404);
        }

        return response()->file($image, [
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function latestFile(string $directory, array $extensions): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $allowed = array_map('strtolower', $extensions);
        $latest = null;
        $latestTime = -1;

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (! in_array($extension, $allowed, true)) {
                continue;
            }

            $mtime = filemtime($path) ?: 0;
            if ($mtime > $latestTime) {
                $latest = $path;
                $latestTime = $mtime;
            }
        }

        return $latest;
    }

    private function readMetadata(string $apk): array
    {
        $candidates = [
            preg_replace('/\.apk$/i', '.json', $apk),
            dirname($apk) . DIRECTORY_SEPARATOR . 'latest.json',
        ];

        foreach ($candidates as $candidate) {
            if (! $candidate || ! is_file($candidate)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($candidate), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function versionCodeFromFilename(string $file): ?int
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (preg_match('/(?:vc|versioncode|code)[-_]?(\d+)/i', $name, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function versionNameFromFilename(string $file): ?string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (preg_match('/(\d+\.\d+(?:\.\d+)?(?:[-_a-z0-9.]*)?)/i', $name, $match)) {
            return str_replace('_', '-', $match[1]);
        }

        return null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function absoluteApiUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
