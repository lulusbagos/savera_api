<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimulateApiLatency
{
    private const MAX_DELAY_MS = 5000;

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        $delayMs = $this->resolveDelayMs($request);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $next($request);
    }

    private function resolveDelayMs(Request $request): int
    {
        $routeName = (string) optional($request->route())->getName();
        $routeHeader = match ($routeName) {
            'summary' => 'X-Savera-Simulate-Summary-Latency-Ms',
            'detail' => 'X-Savera-Simulate-Detail-Latency-Ms',
            'profile' => 'X-Savera-Simulate-Profile-Latency-Ms',
            'leave' => 'X-Savera-Simulate-Leave-Latency-Ms',
            'ticket' => 'X-Savera-Simulate-Ticket-Latency-Ms',
            default => null,
        };

        $specificDelay = $routeHeader ? $this->parseDelayMs((string) $request->header($routeHeader, '0')) : 0;
        if ($specificDelay > 0) {
            return $specificDelay;
        }

        return $this->parseDelayMs((string) $request->header('X-Savera-Simulate-Latency-Ms', '0'));
    }

    private function parseDelayMs(string $value): int
    {
        $delayMs = max(0, (int) $value);

        return min($delayMs, self::MAX_DELAY_MS);
    }
}
