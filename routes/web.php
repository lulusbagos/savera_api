<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LogDashboardController;

// =========================
// 🔹 Default Views
// =========================
Route::get('/', fn() => view('welcome'));
Route::get('/docs', fn() => view('docs'));
Route::get('/sleep', fn() => view('sleep'));

// =========================
// 🔹 Universal Image Route
// =========================
Route::get('image/{any}', function ($path) {
    if ($path === 'null') abort(404);

    // Kalau path adalah URL langsung, redirect saja
    if (Str::isUrl($path)) return redirect($path);

    // Coba cari file di storage/public
    $file = Storage::disk('public')->path($path);

    // Kalau file nggak ditemukan, kasih fallback image
    if (!file_exists($file)) {
        $fallback = public_path('default-avatar.png');
        if (!file_exists($fallback)) {
            abort(404, 'Image not found.');
        }
        return response()->file($fallback);
    }

    return response()->file($file);
})->name('image')->where('any', '.*');

// =========================
// 🔹 App Proxy Routes
// =========================
Route::get('app/p5m/{company?}/{employee?}', function () {
    $segments = request()->segments();
    $params = [
        'app'  => request('app', ''),
        'code' => request('code', ''),
        'name' => request('name', ''),
        'job'  => request('job', ''),
    ];
    return proxyExternal('https://savera_admin.idcapps.net/app/p5m', $segments, $params);
})->name('app-p5m');

Route::get('app/score/{company?}/{employee?}', function () {
    $segments = request()->segments();
    $params = [
        'app'  => request('app', ''),
        'code' => request('code', ''),
        'name' => request('name', ''),
        'job'  => request('job', ''),
    ];
    return proxyExternal('https://savera_admin.idcapps.net/app/score', $segments, $params);
})->name('app-score');

Route::get('app/article/{company?}/{employee?}', function ($company = 0, $employee = 0) {
    try {
        $type = request('type', 'Zona Operator Pintar');
        $id   = request('id');
        $url  = "https://savera_admin.idcapps.net/app/article/{$company}/{$employee}";
        $url .= $id ? "/{$id}" : ('?type=' . urlencode($type));

        $context = stream_context_create([
            'http' => [
                'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n",
                'timeout' => 10,
            ],
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) abort(503);

        return response($content, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    } catch (Exception $e) {
        abort(404);
    }
})->name('app-article');

// =========================
// 🔹 Serve PDF Articles
// =========================
Route::get('/image/articles/{filename}', function ($filename) {
    $path = storage_path('app/public/articles/' . $filename);

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . basename($path) . '"'
    ]);
})->name('pdf-view');

// =========================
// 🔹 Logs Route
// =========================
Route::get('logs/{data}/{year}/{month}/{day}/{user}', function () {
    $segments = request()->segments();
    $file = 'data_' . ($segments[1] ?? 'x') . '/'
        . ($segments[2] ?? 'x') . '/'
        . ($segments[3] ?? 'x') . '/'
        . ($segments[4] ?? 'x') . '/'
        . sprintf('%020d', ($segments[5] ?? '0')) . '.json';

    if (Storage::exists($file)) {
        return response()->json(
            json_decode(Storage::get($file), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    return response(['message' => 'Data not found.'], 404);
})->name('logs');

Route::get('/logs/stream', [LogDashboardController::class, 'stream'])->name('logs.stream');
Route::get('/logs/users', [LogDashboardController::class, 'users'])->name('logs.users');

// =========================
// 🔹 Google Sheets API
// =========================
Route::get('spreadsheets', [GoogleController::class, 'spreadSheets']);
