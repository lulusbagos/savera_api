<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MobileAppAssetController;
use App\Http\Controllers\MobileFitToWorkController;
use App\Http\Controllers\MobileNotificationController;
use App\Http\Controllers\MobileNetworkController;
use App\Http\Controllers\P5mController;
use App\Http\Controllers\V2\WearableIngestController;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return response()->json(['message' => 'API Server Ready'], 200);
});

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
Route::get('/health', [HealthCheckController::class, 'check']);
Route::get('mobile/app-update', [MobileAppAssetController::class, 'update'])->name('mobile.app-update');
Route::get('mobile/app-update/download', [MobileAppAssetController::class, 'download'])->name('mobile.app-update.download');
Route::get('mobile/app-update-file', [MobileAppAssetController::class, 'download'])->name('mobile.app-update.file');
Route::get('mobile/splash', [MobileAppAssetController::class, 'splash'])->name('mobile.splash');
Route::get('mobile/splash/image', [MobileAppAssetController::class, 'splashImage'])->name('mobile.splash.image');
Route::get('article-image', [ArticleController::class, 'image'])
    ->name('articles.image');
Route::get('article-image/{path}', [ArticleController::class, 'image'])
    ->where('path', '.*')
    ->name('articles.image.legacy');
//Route::get('/health', [HealthCheckController::class, 'check'])->middleware([]); // tanpa middleware sama sekali
//Route::get('/health', [HealthCheckController::class, 'check'])->middleware([]);
//Route::get('/health', [HealthCheckController::class, 'check'])->withoutMiddleware(['auth:sanctum']);


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('profile', [ApiController::class, 'profile'])->name('profile');
    Route::get('device/{mac?}', [ApiController::class, 'device'])->name('device');
    Route::any('avatar', [ApiController::class, 'avatar'])->name('avatar');
    Route::post('summary', [ApiController::class, 'summary'])->name('summary');
    Route::post('detail', [ApiController::class, 'detail'])->name('detail');
    Route::post('mobile/sleep-snapshot', [ApiController::class, 'sleepSnapshot'])->name('mobile.sleep-snapshot');
    Route::get('mobile/summary-week', [ApiController::class, 'summaryWeek'])->name('mobile.summary-week');
    Route::post('debug/detail-payload', [ApiController::class, 'debugDetailPayload'])->name('debug.detail-payload');
    Route::post('fit-to-work', [MobileFitToWorkController::class, 'store'])->name('mobile.fit-to-work.store.legacy');
    Route::post('mobile/fit-to-work', [MobileFitToWorkController::class, 'store'])->name('mobile.fit-to-work.store');
    Route::get('ticket/{id?}', [ApiController::class, 'ticket'])->name('ticket');
    Route::get('etiket', [ApiController::class, 'etiket'])->name('etiket');
    Route::get('ranking/{id?}', [ApiController::class, 'ranking'])->name('ranking');
    Route::post('leave', [ApiController::class, 'leave'])->name('leave');
    Route::get('banner', [ApiController::class, 'banner'])->name('banner');
    Route::get('p5m', [P5mController::class, 'show'])->name('p5m.show');
    Route::post('p5m', [P5mController::class, 'submit'])->name('p5m.submit');
    Route::get('p5m/scores', [P5mController::class, 'scores'])->name('p5m.scores');
    Route::get('p5m/history', [P5mController::class, 'history'])->name('p5m.history');
    Route::get('p5m/history/{id}', [P5mController::class, 'historyDetail'])->name('p5m.history.detail');
    Route::get('notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [MobileNotificationController::class, 'read'])->name('notifications.read');
    Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('articles/{id}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('network-status', [MobileNetworkController::class, 'status'])->name('network.status');
    Route::post('network-report', [MobileNetworkController::class, 'report'])->name('network.report');
    Route::post('v2/ingest/wearable', WearableIngestController::class)->name('ingest.v2.wearable');
});
