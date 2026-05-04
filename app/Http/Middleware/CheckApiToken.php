<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass untuk endpoint publik yang tidak butuh header Accept
        if (
            $request->is('api/health') ||
            $request->is('api/login') ||
            $request->is('api/register') ||
            $request->is('api/article-image') ||
            $request->is('api/article-image/*')
        ) {
            return $next($request);
        }

        // Case-insensitive check untuk Accept header
        $accept = strtolower($request->headers->get('accept', ''));
        if (strpos($accept, 'application/json') === false) {
            return response([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        return $next($request);
    }
}
