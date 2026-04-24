<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HealthService;
use Illuminate\Support\Arr;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    /**
     * Handle health check request.
     */
    protected $healthService;

    public function __construct(HealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    public function check(Request $request)
    {
        $data = $this->healthService->check();
        $hasError = collect((array) Arr::get($data, 'checks', []))
            ->contains(fn ($check) => Arr::get((array) $check, 'status') === 'ERROR');
        $code = $hasError ? 500 : 200;

        return response()->json([
            'status' => $data['status'],
            'statusCode' => $code,
            'message' => $hasError ? 'Some services are unhealthy' : 'Service is healthy with warnings',
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'protocol' => $request->getScheme(),
            'checks' => $data['checks'],
            'network' => $data['network'],
            'ingest' => $data['ingest'],
            'time' => $data['time'],
        ], $code, ['Content-Type' => 'application/json'])->header('Content-Type', 'application/json');
    }
    /*public function check(Request $request): JsonResponse
    {
        try {
            // Tes koneksi database
            DB::connection()->getPdo();

            $scheme = $request->getScheme(); // http atau https
            $host = $request->getHost();     // domain atau IP
            $ip = $request->ip();
            $time = now()->toDateTimeString();

            return response()->json([
                'status' => 'OK',
                'statusCode' => 200,
                'message' => 'Service is healthy',
                'ip' => $ip,
                'host' => $host,
                'protocol' => $scheme,
                'time' => $time,
            ], 200);

        } catch (\Exception $e) {
            $time = now()->toDateTimeString();
            $message = "Health check failed: " . $e->getMessage();

            Log::error($message);

            return response()->json([
                'status' => 'ERROR',
                'statusCode' => 500,
                'message' => 'Service unhealthy: ' . $e->getMessage(),
                'time' => $time,
            ], 500);
        }
    }*/
}
