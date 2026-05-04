<?php

namespace App\Http\Middleware;

use App\Support\MobileIngestRuntime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogApiRequest
{
    private const CACHE_STORE = 'file';
    private const HIGH_VOLUME_ROUTES = ['summary', 'detail'];
    private const SLOW_SUCCESS_THRESHOLD_MS = 250.0;

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $mac = $request->input('mac_address') ?? $request->route('mac') ?? $request->header('mac') ?? null;
        $routeName = optional($request->route())->getName();
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);

            // Log error
            Log::error('API Error', [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'ip' => $request->ip(),
                'mac' => $mac,
                'user_id' => optional(auth()->user())->id,
                'duration_ms' => $duration,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->storeRecentRequest($request, $duration, 500, $mac, $routeName);

            throw $e; // Penting: tetap lempar agar Laravel bisa kirim respons error ke client
        }

        $duration = round((microtime(true) - $start) * 1000, 2);
        $shouldLogSuccess = ! $this->isHighVolumeRoute($routeName) || $duration >= self::SLOW_SUCCESS_THRESHOLD_MS;
        $isHighVolumeRoute = $this->isHighVolumeRoute($routeName);

        if ($response->getStatusCode() >= 400) {
            Log::warning('API Request Failed', [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'ip' => $request->ip(),
                'mac' => $mac,
                'user_id' => optional(auth()->user())->id,
                'status' => $response->getStatusCode(),
                'duration_ms' => $duration,
            ]);
            $this->storeRecentRequest($request, $duration, $response->getStatusCode(), $mac, $routeName);
        } elseif ($isHighVolumeRoute) {
            // Summary/detail harus selalu tercatat ke cache monitor agar app_version, waktu,
            // dan aktivitas user selalu up to date meskipun request cepat.
            $this->storeRecentRequest($request, $duration, $response->getStatusCode(), $mac, $routeName);
        } elseif ($shouldLogSuccess) {
            Log::info('API Request Success', [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'ip' => $request->ip(),
                'mac' => $mac,
                'user_id' => optional(auth()->user())->id,
                'status' => $response->getStatusCode(),
                'duration_ms' => $duration,
            ]);
            $this->storeRecentRequest($request, $duration, $response->getStatusCode(), $mac, $routeName);
        }

        return $response;
    }

    private function isHighVolumeRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return in_array($routeName, self::HIGH_VOLUME_ROUTES, true);
    }

    private function storeRecentRequest(Request $request, float $duration, int $status, ?string $mac, ?string $routeName): void
    {
        $requestBytes = strlen((string) $request->getContent());
        $appVersionRaw = $request->input('app_version', $request->header('x-app-version', $request->header('app-version')));
        $appVersion = is_string($appVersionRaw) ? trim($appVersionRaw) : null;
        if ($appVersion === '') {
            $appVersion = null;
        }
        $entry = [
            'time' => now()->format('Y-m-d H:i:s'),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'route' => $routeName,
            'status' => $status,
            'duration_ms' => $duration,
            'mac' => $mac,
            'ip' => $request->ip(),
            'user_id' => optional(auth()->user())->id,
            'app_version' => $appVersion,
            'request_bytes' => $requestBytes,
            'speed_kbps_est' => $duration > 0
                ? round(($requestBytes * 8) / $duration, 2)
                : null,
        ];

        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        try {
            $cache->lock('recent-requests', 10)->block(3, function () use ($cache, $entry): void {
                $list = $cache->get('recent_requests', []);
                array_unshift($list, $entry);
                $list = array_slice($list, 0, 100);
                $cache->put('recent_requests', $list, 3600);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Recent request lock timeout', [
                'uri' => $request->getRequestUri(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
