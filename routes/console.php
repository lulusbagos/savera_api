<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\MobileIngestRuntime;
use App\Services\MobileLoadSimulationService;
use Carbon\Carbon;

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

Artisan::command('mobile:simulate-load {--users=100} {--upload-retries=2} {--benchmark} {--benchmark-iterations=3} {--persist-storage} {--migrate-fresh} {--allow-production} {--delay-ms=0} {--summary-delay-ms=0} {--detail-delay-ms=0} {--profile-delay-ms=0} {--leave-delay-ms=0} {--ticket-delay-ms=0}', function () {
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
    $allowProduction = (bool) $this->option('allow-production');
    $defaultConnection = (string) config('database.default');
    $databaseName = (string) config("database.connections.{$defaultConnection}.database", '');
    $isTestDatabase = $defaultConnection === 'sqlite' || str_contains(strtolower($databaseName), 'test');

    if (app()->environment('production') && ! $allowProduction) {
        $this->error('mobile:simulate-load diblokir di production. Gunakan --allow-production hanya jika benar-benar perlu.');
        return 1;
    }

    if ($migrateFresh && ! $isTestDatabase) {
        $this->error("migrate:fresh diblokir karena database tidak terdeteksi sebagai test DB ({$defaultConnection}: {$databaseName}).");
        return 1;
    }

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

Artisan::command('notifications:sync-attendance {--company-id=2} {--days=31} {--source-connection=} {--source-view=} {--dry-run}', function () {
    $companyId = max(1, (int) $this->option('company-id'));
    $days = max(1, (int) $this->option('days'));
    $sourceConnection = (string) ($this->option('source-connection') ?: env('ATTENDANCE_SOURCE_CONNECTION', 'pgsql_nakula'));
    $sourceView = (string) ($this->option('source-view') ?: env('ATTENDANCE_SOURCE_VIEW', 'vw_in_out_karyawan_new_new'));
    $dryRun = (bool) $this->option('dry-run');
    $windowStart = Carbon::now()->subDays($days)->startOfDay();

    $normalizeNik = static function (?string $val): string {
        $digits = preg_replace('/\D+/', '', (string) ($val ?? '')) ?: '';
        $normalized = ltrim($digits, '0');
        return $normalized === '' ? $digits : $normalized;
    };

    $fallbackRows = static function (Carbon $startDate): array {
        try {
            return DB::table('vw_roster_finger_nakula')
                ->selectRaw('
                    id::text as source_id,
                    working_date::date as tanggal,
                    nik::text as nik,
                    finger_in::time as jam_in,
                    finger_out::time as jam_out,
                    null::text as ip_in,
                    null::text as nama_printer_in,
                    null::text as ip_out,
                    null::text as nama_printer_out,
                    tanggal_in::timestamp as event_in_at,
                    tanggal_out::timestamp as event_out_at
                ')
                ->whereDate('working_date', '>=', $startDate->toDateString())
                ->orderByDesc('working_date')
                ->limit(30000)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Throwable $e) {
            Log::warning('notifications:sync-attendance fallback view failed', [
                'view' => 'vw_roster_finger_nakula',
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    };

    $rows = [];
    try {
        $rows = DB::connection($sourceConnection)
            ->table($sourceView)
            ->selectRaw('
                COALESCE(id::text, md5(COALESCE(nik::text, \'\') || COALESCE(tanggal::text, \'\'))) as source_id,
                tanggal::date as tanggal,
                nik::text as nik,
                jam_in::time as jam_in,
                jam_out::time as jam_out,
                ip_in::text as ip_in,
                nama_printer_in::text as nama_printer_in,
                ip_out::text as ip_out,
                nama_printer_out::text as nama_printer_out,
                null::timestamp as event_in_at,
                null::timestamp as event_out_at
            ')
            ->whereDate('tanggal', '>=', $windowStart->toDateString())
            ->orderByDesc('tanggal')
            ->limit(30000)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    } catch (\Throwable $e) {
        Log::warning('notifications:sync-attendance source connection failed, fallback to local view', [
            'source_connection' => $sourceConnection,
            'source_view' => $sourceView,
            'error' => $e->getMessage(),
        ]);
        $this->warn("Source {$sourceConnection}.{$sourceView} gagal diakses, fallback ke vw_roster_finger_nakula.");
        $rows = $fallbackRows($windowStart);
    }

    if (empty($rows)) {
        $this->info('Tidak ada data absen pada rentang waktu sinkronisasi.');
        return 0;
    }

    $employeeUsers = DB::table('employees as e')
        ->join('users as u', 'u.id', '=', 'e.user_id')
        ->where('e.company_id', $companyId)
        ->whereNotNull('e.user_id')
        ->selectRaw('e.id as employee_id, e.code as nik, e.external_employee_id, u.id as user_id, u.name as username')
        ->get();

    $userByNik = [];
    foreach ($employeeUsers as $row) {
        $candidates = [
            $normalizeNik((string) $row->nik),
            $normalizeNik((string) ($row->external_employee_id ?? '')),
        ];
        foreach ($candidates as $key) {
            if ($key === '') {
                continue;
            }
            if (! isset($userByNik[$key])) {
                $userByNik[$key] = [
                    'user_id' => (int) $row->user_id,
                    'username' => (string) $row->username,
                    'employee_id' => (int) $row->employee_id,
                ];
            }
        }
    }

    $created = 0;
    $skippedNoUser = 0;
    $duplicates = 0;
    $now = now();
    $insertBatch = [];

    foreach ($rows as $row) {
        $nikRaw = (string) ($row['nik'] ?? '');
        $nikKey = $normalizeNik($nikRaw);
        if ($nikKey === '' || ! isset($userByNik[$nikKey])) {
            $skippedNoUser++;
            continue;
        }

        $tanggal = (string) ($row['tanggal'] ?? '');
        if ($tanggal === '') {
            continue;
        }
        $jamIn = trim((string) ($row['jam_in'] ?? ''));
        $jamOut = trim((string) ($row['jam_out'] ?? ''));
        $ipIn = trim((string) ($row['ip_in'] ?? ''));
        $ipOut = trim((string) ($row['ip_out'] ?? ''));
        $printerIn = trim((string) ($row['nama_printer_in'] ?? ''));
        $printerOut = trim((string) ($row['nama_printer_out'] ?? ''));
        $sourceId = trim((string) ($row['source_id'] ?? ''));

        $sourceRef = 'attendance:' . $nikKey . ':' . $tanggal . ':' . ($jamIn !== '' ? $jamIn : '-') . ':' . ($jamOut !== '' ? $jamOut : '-');
        if ($sourceId !== '') {
            $sourceRef .= ':' . $sourceId;
        }
        $sourceRef = Str::limit($sourceRef, 118, '');

        $title = 'Absensi Harian ' . Carbon::parse($tanggal)->format('d M Y');
        $messageHtml = '<div style="font-family:Arial,sans-serif;font-size:13px;line-height:1.5">'
            . '<div style="font-weight:700;color:#0f172a;margin-bottom:6px">Ringkasan In/Out Karyawan</div>'
            . '<table style="width:100%;border-collapse:collapse">'
            . '<tr><td style="padding:3px 0;color:#64748b">NIK</td><td style="padding:3px 0;color:#0f172a;font-weight:600">' . e($nikRaw) . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#64748b">Tanggal</td><td style="padding:3px 0;color:#0f172a">' . e(Carbon::parse($tanggal)->format('d/m/Y')) . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#64748b">Jam In</td><td style="padding:3px 0;color:#0f172a">' . e($jamIn !== '' ? $jamIn : '-') . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#64748b">Jam Out</td><td style="padding:3px 0;color:#0f172a">' . e($jamOut !== '' ? $jamOut : '-') . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#64748b">IP In / Out</td><td style="padding:3px 0;color:#0f172a">' . e(($ipIn !== '' ? $ipIn : '-') . ' / ' . ($ipOut !== '' ? $ipOut : '-')) . '</td></tr>'
            . '<tr><td style="padding:3px 0;color:#64748b">Printer In / Out</td><td style="padding:3px 0;color:#0f172a">' . e(($printerIn !== '' ? $printerIn : '-') . ' / ' . ($printerOut !== '' ? $printerOut : '-')) . '</td></tr>'
            . '</table>'
            . '</div>';

        $recipient = $userByNik[$nikKey];
        $insertBatch[] = [
            'company_id' => $companyId,
            'user_id' => $recipient['user_id'],
            'username' => $recipient['username'],
            'title' => $title,
            'message_html' => $messageHtml,
            'source_type' => 'attendance_inout',
            'source_ref' => $sourceRef,
            'source_event_at' => Carbon::parse($tanggal)->endOfDay()->toDateTimeString(),
            'payload_json' => json_encode([
                'nik' => $nikRaw,
                'tanggal' => $tanggal,
                'jam_in' => $jamIn,
                'jam_out' => $jamOut,
                'ip_in' => $ipIn,
                'nama_printer_in' => $printerIn,
                'ip_out' => $ipOut,
                'nama_printer_out' => $printerOut,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 0,
            'published_at' => Carbon::parse($tanggal)->endOfDay()->toDateTimeString(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    if ($dryRun) {
        $this->info('Dry run mode: tidak ada insert ke database.');
        $this->line('Candidate rows: ' . count($insertBatch));
        $this->line('Skipped (NIK tidak match user): ' . $skippedNoUser);
        return 0;
    }

    if (! empty($insertBatch)) {
        foreach (array_chunk($insertBatch, 500) as $chunk) {
            DB::table('mobile_notifications')->upsert(
                $chunk,
                ['company_id', 'user_id', 'source_ref'],
                ['title', 'message_html', 'source_type', 'source_event_at', 'payload_json', 'published_at', 'updated_at']
            );
            $created += count($chunk);
        }

        $duplicates = max(0, $created - DB::table('mobile_notifications')
            ->where('company_id', $companyId)
            ->where('source_type', 'attendance_inout')
            ->where('source_event_at', '>=', $windowStart)
            ->count());
    }

    $this->info('Sinkron notifikasi absensi selesai.');
    $this->line('Processed rows: ' . count($rows));
    $this->line('Upsert rows: ' . $created);
    $this->line('Skipped (NIK tidak match user): ' . $skippedNoUser);
    $this->line('Potential duplicates avoided: ' . $duplicates);

    return 0;
})->purpose('Sinkron data absensi (in/out) menjadi notifikasi mobile per user');

if (MobileIngestRuntime::workerEnabled() && MobileIngestRuntime::usesAsyncQueue()) {
    Schedule::command(
        'queue:work ' . MobileIngestRuntime::queueConnection() .
        ' --queue=' . MobileIngestRuntime::queueName() .
        ' --stop-when-empty --tries=1 --sleep=1 --timeout=120 --max-time=8'
    )
        ->everyTenSeconds()
        ->withoutOverlapping();
}

Schedule::command('notifications:sync-attendance --company-id=2 --days=31')
    ->everyTenMinutes()
    ->withoutOverlapping();
