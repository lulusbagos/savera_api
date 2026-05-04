<?php

namespace App\Http\Controllers;

use App\Support\MobileIngestRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MobileNetworkController extends Controller
{
    private const CACHE_STORE = 'file';
    private const REPORT_TTL_SECONDS = 7200;

    public function status(Request $request): JsonResponse
    {
        $userId = optional($request->user())->id;
        $mac = (string) ($request->input('mac_address') ?? $request->header('mac') ?? '');
        $ip = $this->resolveClientIp($request);
        $scope = $this->classifyIpScope($ip);
        $networkType = $scope === 'public' ? 'public' : ($scope === 'local' ? 'wifi/local' : 'unknown');

        $last = $this->resolveLastRequestMetrics($userId, $mac, $ip);
        $durationMs = (float) ($last['duration_ms'] ?? 0);
        $speedKbps = $last['speed_kbps_est'] ?? null;

        return response()->json([
            'user_id' => $userId,
            'mac_address' => $mac !== '' ? $mac : null,
            'ip_address' => $ip !== '' ? $ip : null,
            'ip_scope' => $scope,
            'network_type' => $networkType,
            'request_speed' => [
                'duration_ms' => $durationMs > 0 ? round($durationMs, 2) : null,
                'speed_kbps_est' => $speedKbps !== null ? round((float) $speedKbps, 2) : null,
                'tier' => $this->speedTierFromMs($durationMs),
            ],
            'last_route' => $last['route'] ?? $last['uri'] ?? null,
            'captured_at' => now()->toDateTimeString(),
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mac_address' => 'nullable|string|max:64',
            'network_type' => 'nullable|string|max:32', // wifi|cellular|ethernet|vpn|unknown
            'is_metered' => 'nullable|boolean',
            'downlink_mbps' => 'nullable|numeric|min:0|max:100000',
            'uplink_mbps' => 'nullable|numeric|min:0|max:100000',
            'rtt_ms' => 'nullable|numeric|min:0|max:60000',
            'device_signal_level' => 'nullable|integer|min:0|max:5',
        ]);

        $userId = optional($request->user())->id;
        $mac = trim((string) ($payload['mac_address'] ?? $request->header('mac') ?? ''));
        $ip = $this->resolveClientIp($request);
        $now = now()->toDateTimeString();
        $scope = $this->classifyIpScope($ip);

        $report = [
            'user_id' => $userId,
            'mac_address' => $mac !== '' ? $mac : null,
            'ip_address' => $ip !== '' ? $ip : null,
            'ip_scope' => $scope,
            'network_type' => $payload['network_type'] ?? ($scope === 'public' ? 'public' : ($scope === 'local' ? 'wifi/local' : 'unknown')),
            'is_metered' => array_key_exists('is_metered', $payload) ? (bool) $payload['is_metered'] : null,
            'downlink_mbps' => isset($payload['downlink_mbps']) ? (float) $payload['downlink_mbps'] : null,
            'uplink_mbps' => isset($payload['uplink_mbps']) ? (float) $payload['uplink_mbps'] : null,
            'rtt_ms' => isset($payload['rtt_ms']) ? (float) $payload['rtt_ms'] : null,
            'device_signal_level' => isset($payload['device_signal_level']) ? (int) $payload['device_signal_level'] : null,
            'reported_at' => $now,
            'source' => 'mobile_report',
        ];

        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $key = $this->reportCacheKey($userId, $mac, $ip);
        $cache->put($key, $report, self::REPORT_TTL_SECONDS);

        return response()->json([
            'message' => 'Network report stored',
            'data' => $report,
        ]);
    }

    private function resolveLastRequestMetrics(?int $userId, string $mac, string $ip): array
    {
        $cache = Cache::store(MobileIngestRuntime::cacheStore(self::CACHE_STORE));
        $recent = $cache->get('recent_requests', []);
        if (!is_array($recent) || empty($recent)) {
            return [];
        }

        foreach ($recent as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ($userId !== null && isset($entry['user_id']) && (int) $entry['user_id'] === $userId) {
                return $entry;
            }
            if ($mac !== '' && isset($entry['mac']) && strcasecmp((string) $entry['mac'], $mac) === 0) {
                return $entry;
            }
            if ($ip !== '' && isset($entry['ip']) && (string) $entry['ip'] === $ip) {
                return $entry;
            }
        }

        return [];
    }

    private function classifyIpScope(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return 'unknown';
        }
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

    private function reportCacheKey(?int $userId, string $mac, string $ip): string
    {
        if ($userId !== null) {
            return 'mobile_network_report:user:' . $userId;
        }
        if ($mac !== '') {
            return 'mobile_network_report:mac:' . strtoupper($mac);
        }
        return 'mobile_network_report:ip:' . $ip;
    }

    private function resolveClientIp(Request $request): string
    {
        $forwardedFor = (string) $request->headers->get('X-Forwarded-For', '');
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $candidate = trim((string) ($parts[0] ?? ''));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return (string) $request->ip();
    }
}
