<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;

class LogHelper
{
    public static function logError($proses, $exceptionMessage)
    {
        $date = now()->format('Ymd'); // 20250601
        $logPath = storage_path("logs/{$date}.txt");
        File::ensureDirectoryExists(dirname($logPath));

        $timestamp = now()->format('Y-m-d H:i:s');
        $message = "[{$timestamp}] {$proses} | ERROR: {$exceptionMessage}" . PHP_EOL;

        File::append($logPath, $message);
    }
}
