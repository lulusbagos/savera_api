<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachMobileNetworkConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $publicBaseUrl = $this->normalizeBaseUrl((string) config('mobile_network.public_base_url', ''));
        $localBaseUrl = $this->normalizeBaseUrl((string) config('mobile_network.local_base_url', ''));
        $preferredRoute = $this->resolvePreferredRoute($request, $localBaseUrl);

        if ($publicBaseUrl !== '') {
            $response->headers->set('X-Savera-Public-Base-Url', $publicBaseUrl);
        }

        if ($localBaseUrl !== '') {
            $response->headers->set('X-Savera-Local-Base-Url', $localBaseUrl);
        }

        $response->headers->set('X-Savera-Preferred-Route', $preferredRoute);

        return $response;
    }

    private function normalizeBaseUrl(string $url): string
    {
        return rtrim(trim($url), '/');
    }

    private function normalizePreferredRoute(string $route): string
    {
        $route = strtolower(trim($route));

        return $route === 'local' ? 'local' : 'public';
    }

    private function resolvePreferredRoute(Request $request, string $localBaseUrl): string
    {
        $configured = $this->normalizePreferredRoute((string) config('mobile_network.preferred_route', 'public'));
        if ($localBaseUrl === '') {
            return 'public';
        }

        $scope = $this->classifyIpScope($this->resolveClientIp($request));
        if ($scope === 'public') {
            return 'public';
        }
        if ($scope === 'local') {
            return 'local';
        }

        return $configured;
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
