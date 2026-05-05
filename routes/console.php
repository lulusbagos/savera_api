<?php
if (defined('SAVERA_API_CONSOLE_BOOTSTRAPPED')) { return; }
define('SAVERA_API_CONSOLE_BOOTSTRAPPED', true);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\MobileIngestRuntime;
use App\Services\MobileLoadSimulationService;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('codewatch:scan {--force-alert=0}', function () {
    $toBool = static function (mixed $value, bool $default = false): bool {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    };

    $forceAlert = $toBool($this->option('force-alert'));
    $enabled = $toBool(env('CODEWATCH_ENABLED', false));
    if (! $enabled && ! $forceAlert) {
        $this->line('Code watch disabled.');
        return 0;
    }

    $watchPaths = array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CODEWATCH_PATHS',
        'app,bootstrap,config,routes,resources,database/migrations'
    )))));
    $snapshotPath = storage_path('app/codewatch_snapshot_api.json');
    $host = gethostname() ?: php_uname('n');

    $collectFiles = static function (array $paths): array {
        $out = [];
        foreach ($paths as $relative) {
            $absolute = base_path($relative);
            if (! is_dir($absolute) && ! is_file($absolute)) {
                continue;
            }

            if (is_file($absolute)) {
                $files = [new \SplFileInfo($absolute)];
            } else {
                $files = File::allFiles($absolute);
            }

            foreach ($files as $file) {
                $filePath = str_replace('\\', '/', $file->getPathname());
                $relPath = str_replace('\\', '/', Str::after($filePath, str_replace('\\', '/', base_path()) . '/'));

                if (str_starts_with($relPath, 'storage/') || str_starts_with($relPath, 'vendor/') || str_contains($relPath, '/.git/')) {
                    continue;
                }

                $out[$relPath] = [
                    'hash' => @hash_file('sha256', $filePath) ?: '',
                    'mtime' => @filemtime($filePath) ?: 0,
                ];
            }
        }

        ksort($out);
        return $out;
    };

    $current = $collectFiles($watchPaths);
    if ($current === []) {
        $this->warn('No files collected for code watch.');
        return 0;
    }

    $previous = [];
    if (is_file($snapshotPath)) {
        $raw = File::get($snapshotPath);
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $previous = $decoded;
        }
    }

    if ($previous === []) {
        File::ensureDirectoryExists(dirname($snapshotPath));
        File::put($snapshotPath, json_encode($current, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $this->info('Initial snapshot created (API).');
        return 0;
    }

    $added = array_keys(array_diff_key($current, $previous));
    $deleted = array_keys(array_diff_key($previous, $current));
    $modified = [];

    foreach ($current as $path => $meta) {
        if (! isset($previous[$path])) {
            continue;
        }
        if ((string) ($meta['hash'] ?? '') !== (string) ($previous[$path]['hash'] ?? '')) {
            $modified[] = $path;
        }
    }

    if ($added === [] && $deleted === [] && $modified === []) {
        $this->line('No source changes detected.');
        return 0;
    }

    $emails = array_values(array_filter(array_map('trim', explode(',', (string) env('CODEWATCH_ALERT_TO', '')))));
    if ($emails === []) {
        Log::warning('Code watch detected changes but CODEWATCH_ALERT_TO is empty.', [
            'app' => config('app.name'),
            'host' => $host,
            'added' => count($added),
            'modified' => count($modified),
            'deleted' => count($deleted),
        ]);
    } else {
        $maxList = max(1, (int) env('CODEWATCH_LIST_LIMIT', 60));
        $body = "Code change detected on API server.\n"
            . 'App: ' . config('app.name') . "\n"
            . 'Host: ' . $host . "\n"
            . 'Time: ' . now()->toDateTimeString() . "\n\n"
            . 'Added: ' . count($added) . "\n"
            . implode("\n", array_slice($added, 0, $maxList)) . "\n\n"
            . 'Modified: ' . count($modified) . "\n"
            . implode("\n", array_slice($modified, 0, $maxList)) . "\n\n"
            . 'Deleted: ' . count($deleted) . "\n"
            . implode("\n", array_slice($deleted, 0, $maxList)) . "\n";

        foreach ($emails as $email) {
            Mail::raw($body, function ($message) use ($email, $host) {
                $message->to($email)
                    ->subject('[ALERT][API] Source changed on ' . $host);
            });
        }
    }

    File::put($snapshotPath, json_encode($current, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $this->info('Code watch alert processed (API).');
    return 0;
})->purpose('Scan source changes and send email alerts (API)');

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

Artisan::command('notifications:sync-attendance {--company-id=2} {--days=30} {--source-connection=} {--source-view=} {--dry-run}', function () {
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

        // Stabil per user-harian agar update jam in/out tidak membuat notifikasi baru (double).
        $sourceRef = 'attendance:' . $nikKey . ':' . $tanggal;
        $sourceRef = Str::limit($sourceRef, 118, '');

        $title = 'Absensi Harian ' . Carbon::parse($tanggal)->format('d M Y');
        $messageHtml = '<font color="#16A34A"><b>ABSENSI HARIAN</b></font><br>'
            . '<b>Ringkasan In/Out Karyawan</b><br><br>'
            . '<b>NIK:</b> ' . e($nikRaw) . '<br>'
            . '<b>Tanggal:</b> ' . e(Carbon::parse($tanggal)->format('d/m/Y')) . '<br>'
            . '<b>Jam In:</b> ' . e($jamIn !== '' ? $jamIn : '-') . '<br>'
            . '<b>Jam Out:</b> ' . e($jamOut !== '' ? $jamOut : '-') . '<br>'
            . '<b>Lokasi (IP):</b> ' . e(($ipIn !== '' ? $ipIn : '-') . ' / ' . ($ipOut !== '' ? $ipOut : '-')) . '<br>'
            . '<b>Lokasi Mesin:</b> ' . e(($printerIn !== '' ? $printerIn : '-') . ' / ' . ($printerOut !== '' ? $printerOut : '-'));

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

    $deletedOld = DB::table('mobile_notifications')
        ->where('company_id', $companyId)
        ->where(function ($q) use ($windowStart) {
            $q->where(function ($inner) use ($windowStart) {
                $inner->whereNotNull('published_at')
                    ->where('published_at', '<', $windowStart);
            })->orWhere(function ($inner) use ($windowStart) {
                $inner->whereNull('published_at')
                    ->whereNotNull('source_event_at')
                    ->where('source_event_at', '<', $windowStart);
            })->orWhere(function ($inner) use ($windowStart) {
                $inner->whereNull('published_at')
                    ->whereNull('source_event_at')
                    ->where('created_at', '<', $windowStart);
            });
        })
        ->delete();

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
    $this->line('Pruned old rows (< ' . $windowStart->toDateString() . '): ' . $deletedOld);
    $this->line('Potential duplicates avoided: ' . $duplicates);

    return 0;
})->purpose('Sinkron data absensi (in/out) menjadi notifikasi mobile per user');

Artisan::command('notifications:dedupe-attendance {--company-id=2}', function () {
    $companyId = max(1, (int) $this->option('company-id'));

    $deleted = DB::affectingStatement(<<<'SQL'
WITH ranked AS (
    SELECT
        id,
        ROW_NUMBER() OVER (
            PARTITION BY
                company_id,
                user_id,
                COALESCE(
                    NULLIF(payload_json->>'tanggal', ''),
                    TO_CHAR(source_event_at::date, 'YYYY-MM-DD'),
                    SUBSTRING(source_ref FROM 'attendance:[^:]+:([0-9]{4}-[0-9]{2}-[0-9]{2})')
                )
            ORDER BY COALESCE(published_at, source_event_at, created_at) DESC, id DESC
        ) AS rn
    FROM mobile_notifications
    WHERE company_id = ?
      AND source_type = 'attendance_inout'
      AND deleted_at IS NULL
)
DELETE FROM mobile_notifications m
USING ranked r
WHERE m.id = r.id
  AND r.rn > 1
SQL, [$companyId]);

    $this->info('Deduplikasi notifikasi absensi selesai.');
    $this->line('Deleted duplicate rows: ' . $deleted);

    return 0;
})->purpose('Hapus notifikasi absensi harian duplikat (satu user satu tanggal satu notifikasi)');

if (MobileIngestRuntime::workerEnabled() && MobileIngestRuntime::usesAsyncQueue()) {
    Schedule::command(
        'queue:work ' . MobileIngestRuntime::queueConnection() .
        ' --queue=' . MobileIngestRuntime::queueName() .
        ' --stop-when-empty --tries=1 --sleep=1 --timeout=120 --max-time=8'
    )
        ->everyTenSeconds()
        ->withoutOverlapping();
}

Schedule::command('notifications:sync-attendance --company-id=2 --days=30')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('notifications:dedupe-attendance --company-id=2')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('codewatch:scan')
    ->everyMinute()
    ->withoutOverlapping();
