<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IngestAudit;
use App\Models\Summary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class WearableIngestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();
        $size = strlen($raw);
        $maxBytes = 8 * 1024 * 1024;

        if ($size > $maxBytes) {
            return $this->error(413, 'PAYLOAD_TOO_LARGE', 'Payload terlalu besar.', 0, [], []);
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->error(422, 'INVALID_JSON', 'JSON payload tidak valid.', 0, [], []);
        }

        try {
            $validated = Validator::make($payload, [
                'upload_id' => 'required|string|max:80',
                'chunk_index' => 'required|integer|min:1',
                'chunk_count' => 'required|integer|min:1',
                'idempotency_key' => 'required|string|max:120',
                'sent_at' => 'required|integer|min:1',
                'device_id' => 'required|integer|min:1',
                'user_id' => 'required|integer|min:1',
                'date' => 'required|date_format:Y-m-d',
                'counts.activity' => 'required|integer|min:0',
                'counts.sleep' => 'required|integer|min:0',
                'counts.spo2' => 'required|integer|min:0',
                'counts.stress' => 'required|integer|min:0',
                'range.min_ts' => 'required|integer|min:1',
                'range.max_ts' => 'required|integer|min:1|gte:range.min_ts',
                'payload_hash_sha256' => 'required|string|max:128',
                'payload_size_bytes' => 'required|integer|min:0',
                'app_version' => 'nullable|string|max:80',
                'is_fit1' => 'nullable',
                'is_fit2' => 'nullable',
                'is_fit3' => 'nullable',
                'fit_to_work_q1' => 'nullable',
                'fit_to_work_q2' => 'nullable',
                'fit_to_work_q3' => 'nullable',
                'fit_to_work_submitted_at' => 'nullable|date',
                'data.activity' => 'required|array',
                'data.sleep_sessions' => 'required|array',
                'data.spo2' => 'required|array',
                'data.stress' => 'required|array',
            ])->validate();
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'reason_code' => 'VALIDATION_FAILED',
                'message' => 'Validasi payload gagal.',
                'parsed_counts' => 0,
                'failed_item_indexes' => [],
                'errors' => $e->errors(),
            ], 422);
        }

        $canonicalPayload = $payload;
        unset($canonicalPayload['payload_hash_sha256'], $canonicalPayload['payload_size_bytes']);
        $canonicalPayload = $this->canonicalize($canonicalPayload);
        $canonicalJson = json_encode(
            $canonicalPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        $canonicalJson = is_string($canonicalJson) ? $canonicalJson : '';

        $rawHash = hash('sha256', $raw);
        $canonicalHash = hash('sha256', $canonicalJson);
        $clientHash = strtolower((string) $validated['payload_hash_sha256']);
        $hashMatched = in_array($clientHash, [strtolower($rawHash), strtolower($canonicalHash)], true);

        if (! $hashMatched) {
            return response()->json([
                'ok' => false,
                'reason_code' => 'HASH_MISMATCH',
                'message' => 'Hash payload tidak sesuai.',
                'parsed_counts' => 0,
                'failed_item_indexes' => [],
                // debug untuk sinkronisasi client-backend
                'debug' => [
                    'client_hash_sha256' => $clientHash,
                    'server_hash_raw_sha256' => $rawHash,
                    'server_hash_canonical_sha256' => $canonicalHash,
                    'server_raw_size_bytes' => $size,
                    'server_canonical_size_bytes' => strlen($canonicalJson),
                    'hash_match_mode' => 'raw_or_canonical_without_hash_and_size',
                ],
            ], 422);
        }

        $clientSize = (int) $validated['payload_size_bytes'];
        $canonicalSize = strlen($canonicalJson);
        $sizeMatched = in_array($clientSize, [$size, $canonicalSize], true);
        if (! $sizeMatched) {
            return response()->json([
                'ok' => false,
                'reason_code' => 'SIZE_MISMATCH',
                'message' => 'Ukuran payload tidak sesuai.',
                'parsed_counts' => 0,
                'failed_item_indexes' => [],
                'debug' => [
                    'client_payload_size_bytes' => $clientSize,
                    'server_raw_size_bytes' => $size,
                    'server_canonical_size_bytes' => $canonicalSize,
                    'size_match_mode' => 'raw_or_canonical_without_hash_and_size',
                ],
            ], 422);
        }

        $existing = IngestAudit::query()
            ->where('idempotency_key', $validated['idempotency_key'])
            ->first();
        if ($existing) {
            if ((string) $existing->payload_hash !== (string) $validated['payload_hash_sha256']) {
                return $this->error(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency key conflict.', 0, [], []);
            }

            return response()->json([
                'ok' => true,
                'upload_id' => $existing->upload_id,
                'chunk_index' => (int) $existing->chunk_index,
                'accepted_counts' => json_decode((string) $existing->accepted_counts_json, true) ?? [],
                'parsed_counts' => json_decode((string) $existing->parsed_counts_json, true) ?? [],
                'stored_counts' => json_decode((string) $existing->stored_counts_json, true) ?? [],
                'server_hash_sha256' => (string) $existing->payload_hash,
                'warnings' => ['duplicate_idempotency_replayed'],
            ]);
        }

        $company = $this->findCompanyByCode($request->header('company'), (int) $validated['user_id']);
        if (! $company) {
            return $this->error(404, 'COMPANY_NOT_FOUND', 'Company not found.', 0, [], []);
        }

        $accepted = [
            'activity' => (int) $validated['counts']['activity'],
            'sleep' => (int) $validated['counts']['sleep'],
            'spo2' => (int) $validated['counts']['spo2'],
            'stress' => (int) $validated['counts']['stress'],
        ];

        $parsed = [
            'activity' => is_array($validated['data']['activity']) ? count($validated['data']['activity']) : 0,
            'sleep' => is_array($validated['data']['sleep_sessions']) ? count($validated['data']['sleep_sessions']) : 0,
            'spo2' => is_array($validated['data']['spo2']) ? count($validated['data']['spo2']) : 0,
            'stress' => is_array($validated['data']['stress']) ? count($validated['data']['stress']) : 0,
        ];

        foreach (['activity', 'sleep', 'spo2', 'stress'] as $k) {
            if ($parsed[$k] < $accepted[$k]) {
                return $this->error(422, 'PARSED_LESS_THAN_ACCEPTED', "Parsed {$k} lebih kecil dari accepted.", $parsed, [], []);
            }
        }

        [$stored, $warnings] = $this->storeByDateUser(
            date: $validated['date'],
            userId: (int) $validated['user_id'],
            data: $validated['data']
        );

        $summary = $this->upsertSummaryFromV2(
            validated: $validated,
            companyId: (int) $company->id,
            warnings: $warnings
        );

        IngestAudit::query()->create([
            'upload_id' => $validated['upload_id'],
            'chunk_index' => (int) $validated['chunk_index'],
            'chunk_count' => (int) $validated['chunk_count'],
            'idempotency_key' => $validated['idempotency_key'],
            'user_id' => (int) $validated['user_id'],
            'company_id' => $company->id,
            'date' => $validated['date'],
            'payload_hash' => $validated['payload_hash_sha256'],
            'payload_size' => $size,
            'accepted_counts_json' => json_encode($accepted, JSON_UNESCAPED_SLASHES),
            'parsed_counts_json' => json_encode($parsed, JSON_UNESCAPED_SLASHES),
            'stored_counts_json' => json_encode($stored, JSON_UNESCAPED_SLASHES),
            'status' => 'accepted',
        ]);

        return response()->json([
            'ok' => true,
            'upload_id' => $validated['upload_id'],
            'chunk_index' => (int) $validated['chunk_index'],
            'accepted_counts' => $accepted,
            'parsed_counts' => $parsed,
            'stored_counts' => $stored,
            'server_hash_sha256' => $canonicalHash,
            'hash_mode' => 'canonical_without_hash_and_size',
            'summary_id' => $summary?->id,
            'warnings' => $warnings,
        ]);
    }

    private function upsertSummaryFromV2(array $validated, int $companyId, array &$warnings): ?Summary
    {
        $userId = (int) $validated['user_id'];
        $date = (string) $validated['date'];
        $deviceTimeTs = (int) ($validated['range']['max_ts'] ?? $validated['sent_at']);
        $deviceTime = date('Y-m-d H:i:s', $deviceTimeTs);
        $sendDate = date('Y-m-d', (int) $validated['sent_at']);
        $sendTime = date('H:i:s', (int) $validated['sent_at']);
        $deviceId = (int) $validated['device_id'];

        $employee = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first(['id', 'department_id']);
        if (! $employee) {
            $warnings[] = 'summary_skipped_employee_not_found';
            return null;
        }

        $activity = is_array($validated['data']['activity'] ?? null) ? $validated['data']['activity'] : [];
        $spo2Rows = is_array($validated['data']['spo2'] ?? null) ? $validated['data']['spo2'] : [];
        $stressRows = is_array($validated['data']['stress'] ?? null) ? $validated['data']['stress'] : [];
        $sleepSessions = is_array($validated['data']['sleep_sessions'] ?? null) ? $validated['data']['sleep_sessions'] : [];

        $sleepMinutes = $this->calcSleepMinutes($sleepSessions, $activity);
        $deep = $this->countStageMinutes($activity, ['DEEP_SLEEP']);
        $light = $this->countStageMinutes($activity, ['LIGHT_SLEEP']);
        $rem = $this->countStageMinutes($activity, ['REM_SLEEP']);
        $awake = $this->countStageMinutes($activity, ['AWAKE', 'UNKNOWN']);
        [$sleepRangeStart, $sleepRangeEnd] = $this->resolveSleepRange($sleepSessions);

        $hrVals = [];
        $steps = 0;
        $active = 0;
        $distance = 0.0;
        $calories = 0.0;
        foreach ($activity as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hr = (float) ($row['heart_rate'] ?? $row['heartRate'] ?? 0);
            if ($hr > 0 && $hr <= 240) {
                $hrVals[] = $hr;
            }
            $steps += (int) ($row['steps'] ?? 0);
            $active += (int) ($row['active'] ?? 0);
            $distance += (float) ($row['distance'] ?? 0);
            $calories += (float) ($row['calories'] ?? 0);
        }

        $spo2Vals = $this->metricValuesForRange($spo2Rows, ['spo2', 'value'], $sleepRangeStart, $sleepRangeEnd);
        if ($spo2Vals === []) {
            $spo2Vals = $this->metricValuesForRange($spo2Rows, ['spo2', 'value']);
        }
        $stressVals = $this->metricValuesForRange($stressRows, ['stress', 'value'], $sleepRangeStart, $sleepRangeEnd);
        if ($stressVals === []) {
            $stressVals = $this->metricValuesForRange($stressRows, ['stress', 'value']);
        }

        $heartRate = !empty($hrVals) ? (int) round(array_sum($hrVals) / count($hrVals)) : 0;
        $spo2 = !empty($spo2Vals) ? (int) round(array_sum($spo2Vals) / count($spo2Vals)) : 0;
        $stress = !empty($stressVals) ? (int) round(array_sum($stressVals) / count($stressVals)) : 0;
        $hour = (int) date('G', $deviceTimeTs);
        $sleepType = ($hour >= 18 || $hour < 6) ? 'night' : 'day';

        $attrs = [
            'active' => $active,
            'steps' => $steps,
            'heart_rate' => $heartRate,
            'heart_rate_text' => $heartRate > 0 ? $heartRate . ' bpm' : null,
            'distance' => (string) round($distance, 2),
            'calories' => (int) round($calories),
            'spo2' => $spo2,
            'spo2_text' => $spo2 > 0 ? $spo2 . '%' : null,
            'stress' => $stress,
            'stress_text' => $stress > 0 ? (string) $stress : null,
            'sleep' => $sleepMinutes,
            'sleep_text' => sprintf('%d:%02d', intdiv($sleepMinutes, 60), $sleepMinutes % 60),
            'sleep_type' => $sleepType,
            'deep_sleep' => $deep,
            'light_sleep' => $light,
            'rem_sleep' => $rem,
            'awake' => $awake,
            'send_date' => $sendDate,
            'send_time' => $sendTime,
            'status' => 0,
            'user_id' => $userId,
            'employee_id' => (int) $employee->id,
            'company_id' => $companyId,
            'department_id' => (int) ($employee->department_id ?? 0),
            'shift_id' => 0,
            'device_id' => $deviceId > 0 ? $deviceId : null,
            'device_time' => $deviceTime,
            'app_version' => (string) ($validated['app_version'] ?? 'N/A'),
            'updated_by' => $userId,
            'created_by' => $userId,
        ];
        $this->applyFitToWorkPayload($validated, $attrs);

        return Summary::query()->updateOrCreate(
            ['company_id' => $companyId, 'user_id' => $userId, 'send_date' => $sendDate, 'sleep_type' => $sleepType],
            $attrs
        );
    }

    private function calcSleepMinutes(array $sleepSessions, array $activity): int
    {
        $seconds = 0;
        foreach ($sleepSessions as $s) {
            if (!is_array($s)) continue;
            $start = (int) ($s['sleep_start'] ?? $s['sleepStart'] ?? 0);
            $end = (int) ($s['sleep_end'] ?? $s['sleepEnd'] ?? 0);
            $stageDur = 0;
            foreach ([
                ['light_sleep_duration', 'lightSleepDuration', 'light_sleep', 'light'],
                ['deep_sleep_duration', 'deepSleepDuration', 'deep_sleep', 'deep'],
                ['rem_sleep_duration', 'remSleepDuration', 'rem_sleep', 'rem'],
            ] as $keys) {
                foreach ($keys as $key) {
                    if (!array_key_exists($key, $s)) {
                        continue;
                    }
                    $stageDur += max(0, (int) $s[$key]);
                    break;
                }
            }
            if ($stageDur > 0) {
                $seconds += $stageDur;
                continue;
            }
            $dur = (int) ($s['total_sleep_duration'] ?? $s['totalSleepDuration'] ?? 0);
            if ($dur > 0) {
                $awakeDur = (int) ($s['awake_sleep_duration'] ?? $s['awakeSleepDuration'] ?? $s['awake_sleep'] ?? $s['awake'] ?? 0);
                if ($awakeDur > 0 && $dur > $awakeDur) {
                    $dur -= $awakeDur;
                }
                $seconds += $dur;
                continue;
            }
            if ($start > 0 && $end > $start) {
                $seconds += ($end - $start);
            }
        }
        if ($seconds > 0) {
            return (int) round($seconds / 60);
        }

        $buckets = [];
        foreach ($activity as $row) {
            if (!is_array($row)) continue;
            $ts = (int) ($row['timestamp'] ?? 0);
            if ($ts <= 0) continue;
            if ($ts > 9999999999) $ts = (int) floor($ts / 1000);
            $kind = strtoupper((string) ($row['kind'] ?? $row['sleep_kind'] ?? 'UNKNOWN'));
            if (in_array($kind, ['DEEP_SLEEP', 'LIGHT_SLEEP', 'REM_SLEEP'], true)) {
                $buckets[(int) floor($ts / 60)] = 1;
            }
        }
        return count($buckets);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveSleepRange(array $sleepSessions): array
    {
        $start = 0;
        $end = 0;
        foreach ($sleepSessions as $session) {
            if (!is_array($session)) {
                continue;
            }
            $sessionStart = $this->normalizeMetricTimestamp($session['sleep_start'] ?? $session['sleepStart'] ?? 0);
            $sessionEnd = $this->normalizeMetricTimestamp($session['sleep_end'] ?? $session['sleepEnd'] ?? 0);
            if ($sessionStart <= 0 || $sessionEnd <= $sessionStart) {
                continue;
            }
            $start = $start === 0 ? $sessionStart : min($start, $sessionStart);
            $end = max($end, $sessionEnd);
        }

        return [$start, $end];
    }

    private function metricValuesForRange(array $rows, array $valueKeys, int $rangeStart = 0, int $rangeEnd = 0): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($rangeStart > 0 && $rangeEnd > $rangeStart) {
                $timestamp = $this->normalizeMetricTimestamp($row['timestamp'] ?? $row['ts'] ?? 0);
                if ($timestamp < $rangeStart || $timestamp > $rangeEnd) {
                    continue;
                }
            }
            foreach ($valueKeys as $key) {
                if (!array_key_exists($key, $row)) {
                    continue;
                }
                $value = (float) $row[$key];
                if ($value > 0) {
                    $values[] = $value;
                }
                break;
            }
        }

        return $values;
    }

    private function countStageMinutes(array $activity, array $kinds): int
    {
        $target = array_flip($kinds);
        $buckets = [];
        foreach ($activity as $row) {
            if (!is_array($row)) continue;
            $ts = (int) ($row['timestamp'] ?? 0);
            if ($ts <= 0) continue;
            if ($ts > 9999999999) $ts = (int) floor($ts / 1000);
            $kind = strtoupper((string) ($row['kind'] ?? $row['sleep_kind'] ?? 'UNKNOWN'));
            if (isset($target[$kind])) {
                $buckets[(int) floor($ts / 60)] = 1;
            }
        }
        return count($buckets);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isAssoc($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[(string) $k] = $this->canonicalize($v);
            }
            ksort($normalized, SORT_STRING);
            return $normalized;
        }

        // array index tetap urutan asli
        return array_map(fn ($v) => $this->canonicalize($v), $value);
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function storeByDateUser(string $date, int $userId, array $data): array
    {
        $stored = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $warnings = [];
        $filename = str_pad((string) $userId, 20, '0', STR_PAD_LEFT) . '.json';
        $root = base_path('storage/app/mobile_metrics');

        $mappings = [
            'activity' => ['bucket' => 'data_activity'],
            'sleep_sessions' => ['bucket' => 'data_sleep'],
            'spo2' => ['bucket' => 'data_spo2'],
            'stress' => ['bucket' => 'data_stress'],
        ];

        foreach ($mappings as $inputKey => $cfg) {
            $rows = is_array($data[$inputKey] ?? null) ? $data[$inputKey] : [];
            $rowsByDate = [];

            foreach ($rows as $rowIndex => $row) {
                if (!is_array($row)) {
                    $stored['skipped']++;
                    $warnings[] = "{$inputKey}[{$rowIndex}] invalid_record";
                    continue;
                }

                $row = $this->normalizeMetricRow($inputKey, $row);
                $k = $this->metricRowKey($inputKey, $row);
                if ($k === '' || $k === '|') {
                    $stored['skipped']++;
                    $warnings[] = "{$inputKey}[{$rowIndex}] missing_unique_key";
                    continue;
                }

                $rowDate = $this->metricRowDate($date, $inputKey, $row);
                $rowsByDate[$rowDate][] = $row;
            }

            foreach ($rowsByDate as $rowDate => $dateRows) {
                $datePath = str_replace('-', DIRECTORY_SEPARATOR, $rowDate);
                $path = $root . DIRECTORY_SEPARATOR . $cfg['bucket'] . DIRECTORY_SEPARATOR . $datePath;
                $file = $path . DIRECTORY_SEPARATOR . $filename;
                if (!File::exists($path)) {
                    File::makeDirectory($path, 0777, true);
                }

                $existing = [];
                if (File::exists($file)) {
                    $decoded = json_decode((string) File::get($file), true);
                    if (is_array($decoded)) {
                        $existing = $this->normalizeMetricFileRows($inputKey, $decoded);
                    }
                }

                $index = [];
                foreach ($existing as $i => $existingRow) {
                    $existingKey = $this->metricRowKey($inputKey, $existingRow);
                    if ($existingKey === '' || $existingKey === '|') {
                        continue;
                    }
                    $index[$existingKey] = $i;
                }

                foreach ($dateRows as $row) {
                    $k = $this->metricRowKey($inputKey, $row);
                    if ($k === '' || $k === '|') {
                        $stored['skipped']++;
                        continue;
                    }

                if (array_key_exists($k, $index)) {
                    $existing[$index[$k]] = array_merge((array) $existing[$index[$k]], $row);
                    $stored['updated']++;
                } else {
                    $existing[] = $row;
                    $index[$k] = count($existing) - 1;
                    $stored['inserted']++;
                }
            }

                $existing = $this->sortMetricRows($inputKey, array_values($existing));
                File::put($file, json_encode($existing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        return [$stored, array_values(array_unique($warnings))];
    }

    private function normalizeMetricFileRows(string $inputKey, array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $this->normalizeMetricRow($inputKey, $row);
        }
        return $normalized;
    }

    private function normalizeMetricRow(string $inputKey, array $row): array
    {
        if (isset($row['ts']) && !isset($row['timestamp'])) {
            $row['timestamp'] = $row['ts'];
        }

        if (isset($row['timestamp'])) {
            $row['timestamp'] = $this->normalizeMetricTimestamp($row['timestamp']);
        }

        if (isset($row['sleepStart'])) {
            $row['sleepStart'] = $this->normalizeMetricTimestamp($row['sleepStart']);
        }
        if (isset($row['sleepEnd'])) {
            $row['sleepEnd'] = $this->normalizeMetricTimestamp($row['sleepEnd']);
        }
        if (isset($row['sleep_start'])) {
            $row['sleep_start'] = $this->normalizeMetricTimestamp($row['sleep_start']);
        }
        if (isset($row['sleep_end'])) {
            $row['sleep_end'] = $this->normalizeMetricTimestamp($row['sleep_end']);
        }

        if ($inputKey === 'activity') {
            if (isset($row['heart_rate']) && !isset($row['heartRate'])) {
                $row['heartRate'] = $row['heart_rate'];
            }
            if (isset($row['spo_2']) && !isset($row['spo2'])) {
                $row['spo2'] = $row['spo_2'];
            }
        }
        if ($inputKey === 'spo2' && isset($row['value']) && !isset($row['spo2'])) {
            $row['spo2'] = $row['value'];
        }
        if ($inputKey === 'stress' && isset($row['value']) && !isset($row['stress'])) {
            $row['stress'] = $row['value'];
        }

        return $row;
    }

    private function metricRowDate(string $fallbackDate, string $inputKey, array $row): string
    {
        $timestamp = $this->metricRowTimestamp($inputKey, $row);
        if ($timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }
        return $fallbackDate;
    }

    private function metricRowKey(string $inputKey, array $row): string
    {
        if ($inputKey === 'sleep_sessions') {
            $start = $this->normalizeMetricTimestamp($row['sleep_start'] ?? $row['sleepStart'] ?? 0);
            $end = $this->normalizeMetricTimestamp($row['sleep_end'] ?? $row['sleepEnd'] ?? 0);
            return ($start > 0 || $end > 0) ? "{$start}|{$end}" : '';
        }

        $timestamp = $this->metricRowTimestamp($inputKey, $row);
        if ($timestamp <= 0) {
            return '';
        }

        if ($inputKey === 'activity') {
            return $timestamp . '|' . (string) ($row['source'] ?? 'wearable');
        }

        return (string) $timestamp;
    }

    private function metricRowTimestamp(string $inputKey, array $row): int
    {
        if ($inputKey === 'sleep_sessions') {
            return $this->normalizeMetricTimestamp($row['sleep_start'] ?? $row['sleepStart'] ?? 0);
        }

        return $this->normalizeMetricTimestamp($row['timestamp'] ?? $row['ts'] ?? 0);
    }

    private function sortMetricRows(string $inputKey, array $rows): array
    {
        usort($rows, function (array $a, array $b) use ($inputKey): int {
            $at = $this->metricRowTimestamp($inputKey, $a);
            $bt = $this->metricRowTimestamp($inputKey, $b);
            if ($at === $bt) {
                return strcmp($this->metricRowKey($inputKey, $a), $this->metricRowKey($inputKey, $b));
            }
            return $at <=> $bt;
        });

        return $rows;
    }

    private function normalizeMetricTimestamp(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $timestamp = (int) $value;
        if ($timestamp > 9999999999) {
            return (int) floor($timestamp / 1000);
        }

        return $timestamp;
    }

    private function applyFitToWorkPayload(array $payload, array &$attrs): void
    {
        $answers = [
            1 => $this->toBinaryAnswer($payload['fit_to_work_q1'] ?? $payload['is_fit1'] ?? null),
            2 => $this->toBinaryAnswer($payload['fit_to_work_q2'] ?? $payload['is_fit2'] ?? null),
            3 => $this->toBinaryAnswer($payload['fit_to_work_q3'] ?? $payload['is_fit3'] ?? null),
        ];

        $hasAnyAnswer = false;
        foreach ($answers as $index => $answer) {
            if ($answer === null) {
                continue;
            }

            $hasAnyAnswer = true;
            $attrs['is_fit' . $index] = $answer;
            $attrs['fit_to_work_q' . $index] = $answer;
        }

        if ($hasAnyAnswer) {
            $attrs['fit_to_work_submitted_at'] = $payload['fit_to_work_submitted_at'] ?? date('Y-m-d H:i:s');
        }
    }

    private function toBinaryAnswer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return 1;
            }
            if ((int) $value === 0) {
                return 0;
            }
            return null;
        }

        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 'y', 'ya', 'yes', 'true'], true)) {
            return 1;
        }

        if (in_array($text, ['0', 'n', 'no', 'tidak', 'false'], true)) {
            return 0;
        }

        return null;
    }

    private function findCompanyByCode(?string $companyCode, ?int $fallbackUserId): ?Company
    {
        if ($companyCode) {
            $company = Company::query()->where('code', $companyCode)->first();
            if ($company) {
                return $company;
            }
        }

        if ($fallbackUserId) {
            $companyId = DB::table('employees')
                ->where('user_id', $fallbackUserId)
                ->whereNull('deleted_at')
                ->value('company_id');
            if ($companyId) {
                return Company::query()->find($companyId);
            }
        }

        return null;
    }

    private function error(int $status, string $reasonCode, string $message, $parsedCounts, array $failedIndexes, array $errors): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'reason_code' => $reasonCode,
            'message' => $message,
            'parsed_counts' => $parsedCounts,
            'failed_item_indexes' => $failedIndexes,
            'errors' => $errors,
        ], $status);
    }
}
