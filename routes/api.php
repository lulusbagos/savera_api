<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MobileNotificationController;
use App\Http\Controllers\P5mController;

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
//Route::get('/health', [HealthCheckController::class, 'check'])->middleware([]); // tanpa middleware sama sekali
//Route::get('/health', [HealthCheckController::class, 'check'])->middleware([]);
//Route::get('/health', [HealthCheckController::class, 'check'])->withoutMiddleware(['auth:sanctum']);


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('profile', [ApiController::class, 'profile'])->name('profile');
    Route::get('device/{mac?}', [ApiController::class, 'device'])->name('device');
    Route::any('avatar', [ApiController::class, 'avatar'])->name('avatar');
    Route::post('summary', [ApiController::class, 'summary'])->name('summary');
    Route::post('detail', [ApiController::class, 'detail'])->name('detail');
    Route::get('ticket/{id?}', [ApiController::class, 'ticket'])->name('ticket');
    Route::get('ranking/{id?}', [ApiController::class, 'ranking'])->name('ranking');
    Route::post('leave', [ApiController::class, 'leave'])->name('leave');
    Route::get('banner', [ApiController::class, 'banner'])->name('banner');
    Route::get('p5m', [P5mController::class, 'show'])->name('p5m.show');
    Route::post('p5m', [P5mController::class, 'submit'])->name('p5m.submit');
    Route::get('p5m/scores', [P5mController::class, 'scores'])->name('p5m.scores');
    Route::get('notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [MobileNotificationController::class, 'read'])->name('notifications.read');
});
