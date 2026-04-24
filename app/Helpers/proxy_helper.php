<?php

use Illuminate\Support\Facades\Http;

if (!function_exists('proxyExternal')) {
    function proxyExternal($baseUrl, $segments, $queryParams = [])
    {
        try {
            $url = rtrim($baseUrl, '/') . '/' . ($segments[2] ?? 0) . '/' . ($segments[3] ?? 0);

            $response = Http::withOptions([
                    'verify' => false // ⚠️ nonaktifkan SSL verification (sementara)
                ])
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(3, 200)
                ->get($url, $queryParams);

            if ($response->successful()) {
                return response($response->body(), $response->status())
                    ->header('Content-Type', $response->header('Content-Type'));
            }

            abort($response->status());
        } catch (\Throwable $e) {
            abort(504, 'External service timeout');
        }
    }
}
