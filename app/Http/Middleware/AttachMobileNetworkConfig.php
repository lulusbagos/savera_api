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
        $preferredRoute = $this->normalizePreferredRoute((string) config('mobile_network.preferred_route', 'public'));

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
}
