<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDashboardPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('ops_dashboard_unlocked') === true) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('logs/*') || $request->is('upload-monitoring/stream')) {
            return response()->json([
                'message' => 'Dashboard monitoring membutuhkan password.',
            ], 401);
        }

        return redirect()->guest(route('dashboard-login'));
    }
}
