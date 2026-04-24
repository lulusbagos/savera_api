<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Support\MobileIngestRuntime;
use App\Services\MobileLoadSimulationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Bersihkan log utama tiap 5 jam agar tidak menumpuk di memori/disk.
Artisan::command('logs:prune', function () {
    $logPath = storage_path('logs/laravel.log');

    if (!file_exists($logPath)) {
        $this->info('Tidak ada log untuk dibersihkan.');
        return;
    }

    // Pertahankan file kosong agar Laravel tetap bisa menulis ulang.
    try {
        File::put($logPath, '');
        $this->info("Log dibersihkan: {$logPath}");
    } catch (\Throwable $e) {
        $this->error("Gagal membersihkan log: {$e->getMessage()}");
    }
// Laravel belum menyediakan helper everyFiveHours, jadi pakai cron expression.
})->purpose('Prune laravel.log setiap 5 jam')->cron('0 */5 * * *');

// Hapus folder temp yang aman setiap 5 jam agar tidak membebani memori/disk.
Artisan::command('storage:prune-app', function () {
    $base = storage_path('app');
    if (!is_dir($base)) {
        $this->info('storage/app tidak ditemukan.');
        return;
    }

    $removed = 0;
    $failed = 0;

    // Hanya hapus folder yang memang dipakai sebagai temp/cache lokal.
    $targets = [
        $base . DIRECTORY_SEPARATOR . 'tmp',
        $base . DIRECTORY_SEPARATOR . 'temp',
        $base . DIRECTORY_SEPARATOR . 'cache',
        $base . DIRECTORY_SEPARATOR . 'exports',
        $base . DIRECTORY_SEPARATOR . 'imports',
    ];

    foreach ($targets as $path) {
        if (!is_dir($path) && !is_file($path)) {
            continue;
        }

        try {
            if (is_dir($path)) {
                \Illuminate\Support\Facades\File::deleteDirectory($path);
            } else {
                unlink($path);
            }
            $removed++;
        } catch (\Throwable $e) {
            $failed++;
        }
    }

    $this->info("storage/app temp dibersihkan. Berhasil: {$removed}, gagal: {$failed}");
})->purpose('Bersihkan storage/app setiap 5 jam')->cron('0 */5 * * *');

Artisan::command('mobile:simulate-load {--users=100} {--upload-retries=2} {--benchmark} {--benchmark-iterations=3} {--persist-storage} {--migrate-fresh} {--delay-ms=0} {--summary-delay-ms=0} {--detail-delay-ms=0} {--profile-delay-ms=0} {--leave-delay-ms=0} {--ticket-delay-ms=0}', function () {
    $users = max(1, (int) $this->option('users'));
    $uploadRetries = max(1, (int) $this->option('upload-retries'));
    $benchmarkIterations = max(1, (int) $this->option('benchmark-iterations'));
    $persistStorage = (bool) $this->option('persist-storage');
    $migrateFresh = (bool) $this->option('migrate-fresh');
    $latencyMs = [
        'all' => max(0, (int) $this->option('delay-ms')),
        'summary' => max(0, (int) $this->option('summary-delay-ms')),
        'detail' => max(0, (int) $this->option('detail-delay-ms')),
        'profile' => max(0, (int) $this->option('profile-delay-ms')),
        'leave' => max(0, (int) $this->option('leave-delay-ms')),
        'ticket' => max(0, (int) $this->option('ticket-delay-ms')),
    ];

    $service = app(MobileLoadSimulationService::class);

    if ($migrateFresh) {
        $this->info('Menjalankan migrate:fresh untuk menyiapkan database simulasi.');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->line(trim(Artisan::output()));
    }

    if (! $persistStorage) {
        Storage::fake('local');
        $this->info('local storage sedang di-fake agar tidak menulis data real.');
    }

    $this->info("Membuat fleet {$users} user.");
    $fleet = $service->createFleet($users, 'SIM');

    $this->info("Menjalankan upload summary/detail sebanyak {$uploadRetries} putaran.");
    $report = $service->runUploadBatch($fleet, $uploadRetries, false, $latencyMs);

    $this->line("Summary calls: {$report['summary_calls']} | OK: {$report['summary_ok']}");
    $this->line("Detail calls: {$report['detail_calls']} | OK: {$report['detail_ok']}");
    $this->line("Files tersimpan: {$report['file_count']}");
    $this->line('Summary rows: '.\App\Models\Summary::count());

    if ($this->option('benchmark')) {
        $this->info("Menjalankan benchmark endpoint untuk 1 user, {$benchmarkIterations} iterasi per endpoint.");
        $benchmark = $service->benchmarkEndpoints($fleet[0], $benchmarkIterations, $latencyMs);

        $rows = [];
        foreach ($benchmark as $endpoint => $metrics) {
            $rows[] = [
                ucfirst($endpoint),
                $metrics['calls'],
                $metrics['ok'],
                number_format($metrics['avg_ms'], 2),
                number_format($metrics['min_ms'], 2),
                number_format($metrics['max_ms'], 2),
            ];
        }

        $this->table(
            ['Endpoint', 'Calls', 'OK', 'Avg ms', 'Min ms', 'Max ms'],
            $rows
        );
    }
})->purpose('Simulasikan load mobile dengan banyak user dan retry upload');

if (MobileIngestRuntime::workerEnabled() && MobileIngestRuntime::usesAsyncQueue()) {
    Schedule::command(
        'queue:work ' . MobileIngestRuntime::queueConnection() .
        ' --queue=' . MobileIngestRuntime::queueName() .
        ' --stop-when-empty --tries=1 --sleep=1 --timeout=120 --max-time=8'
    )
        ->everyTenSeconds()
        ->withoutOverlapping();
}
