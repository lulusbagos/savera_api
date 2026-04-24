<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                return Limit::perMinute(120)->by('token:' . hash('sha256', $bearerToken));
            }

            return Limit::perMinute(120)->by(optional($request->user())->id ?: $request->ip());
        });

        // Catat query lambat untuk pantau performa DB di dashboard.
        DB::listen(function (QueryExecuted $query) {
            if ($query->time > 200) { // ms
                Log::warning('DB Slow Query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
            }
        });
    }
}
