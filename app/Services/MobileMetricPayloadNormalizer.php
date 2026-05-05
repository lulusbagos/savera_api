<?php

namespace App\Services;

use JsonException;

class MobileMetricPayloadNormalizer
{
    public function normalizeForBucket(string $bucket, mixed $payload): string
    {
        $rows = $this->decodeToRows($payload);
        if ($rows === []) {
            return '[]';
        }

        $normalized = [];
        $indexByKey = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = $this->normalizeRow($bucket, $row);
            $key = $this->rowKey($bucket, $item);

            if (array_key_exists($key, $indexByKey)) {
                $normalized[$indexByKey[$key]] = $item;
                continue;
            }

            $indexByKey[$key] = count($normalized);
            $normalized[] = $item;
        }

        usort($normalized, function (array $a, array $b): int {
            $ta = $this->extractSortTimestamp($a);
            $tb = $this->extractSortTimestamp($b);
            if ($ta === $tb) {
                return strcmp(json_encode($a), json_encode($b));
            }
            return $ta <=> $tb;
        });

        if ($bucket === 'data_sleep') {
            $normalized = $this->compactSleepSessions($normalized);
        }

        try {
            return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '[]';
        }
    }

    private function decodeToRows(mixed $payload): array
    {
        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                return [];
            }
            try {
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        } else {
            $decoded = $payload;
        }

        if (!is_array($decoded)) {
            return [];
        }

        if ($decoded === []) {
            return [];
        }

        $isAssoc = !array_is_list($decoded);
        if ($isAssoc) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }
            return [$decoded];
        }

        return $decoded;
    }

    private function normalizeRow(string $bucket, array $row): array
    {
        $normalized = $row;

        if (array_key_exists('timestamp', $normalized)) {
            $normalized['timestamp'] = $this->normalizeTimestamp($normalized['timestamp']);
        } elseif (array_key_exists('ts', $normalized)) {
            $normalized['timestamp'] = $this->normalizeTimestamp($normalized['ts']);
            unset($normalized['ts']);
        }

        if ($bucket === 'data_sleep') {
            if (!array_key_exists('sleepStart', $normalized) && array_key_exists('start', $normalized)) {
                $normalized['sleepStart'] = $normalized['start'];
            }
            if (!array_key_exists('sleepEnd', $normalized) && array_key_exists('end', $normalized)) {
                $normalized['sleepEnd'] = $normalized['end'];
            }
            if (!array_key_exists('sleep_start', $normalized) && array_key_exists('sleepStart', $normalized)) {
                $normalized['sleep_start'] = $normalized['sleepStart'];
            }
            if (!array_key_exists('sleep_end', $normalized) && array_key_exists('sleepEnd', $normalized)) {
                $normalized['sleep_end'] = $normalized['sleepEnd'];
            }
        }

        if (isset($normalized['sleepStart'])) {
            $normalized['sleepStart'] = $this->normalizeTimestamp($normalized['sleepStart']);
        }
        if (isset($normalized['sleepEnd'])) {
            $normalized['sleepEnd'] = $this->normalizeTimestamp($normalized['sleepEnd']);
        }
        if (isset($normalized['sleep_start'])) {
            $normalized['sleep_start'] = $this->normalizeTimestamp($normalized['sleep_start']);
        }
        if (isset($normalized['sleep_end'])) {
            $normalized['sleep_end'] = $this->normalizeTimestamp($normalized['sleep_end']);
        }

        if ($bucket === 'data_sleep') {
            $sleepStart = $this->normalizeTimestamp($normalized['sleepStart'] ?? $normalized['sleep_start'] ?? 0);
            $sleepEnd = $this->normalizeTimestamp($normalized['sleepEnd'] ?? $normalized['sleep_end'] ?? 0);
            $intervalSeconds = ($sleepStart > 0 && $sleepEnd > $sleepStart) ? ($sleepEnd - $sleepStart) : 0;

            $canonicalMap = [
                'lightSleepDuration' => ['lightSleepDuration', 'light_sleep_duration', 'light_sleep', 'light'],
                'deepSleepDuration' => ['deepSleepDuration', 'deep_sleep_duration', 'deep_sleep', 'deep'],
                'remSleepDuration' => ['remSleepDuration', 'rem_sleep_duration', 'rem_sleep', 'rem'],
                'awakeSleepDuration' => ['awakeSleepDuration', 'awake_sleep_duration', 'awake_sleep', 'awake'],
                'totalSleepDuration' => ['totalSleepDuration', 'total_sleep_duration', 'total_sleep', 'duration', 'duration_seconds'],
            ];

            foreach ($canonicalMap as $canonicalKey => $aliases) {
                foreach ($aliases as $aliasKey) {
                    if (!array_key_exists($aliasKey, $normalized)) {
                        continue;
                    }
                    $seconds = $this->normalizeSleepDurationSeconds($normalized[$aliasKey], $intervalSeconds);
                    foreach ($aliases as $targetKey) {
                        if (array_key_exists($targetKey, $normalized)) {
                            $normalized[$targetKey] = $seconds;
                        }
                    }
                    $normalized[$canonicalKey] = $seconds;
                    break;
                }
            }

            if (!array_key_exists('totalSleepDuration', $normalized) || (int) $normalized['totalSleepDuration'] <= 0) {
                $sumStage = (int) ($normalized['lightSleepDuration'] ?? 0)
                    + (int) ($normalized['deepSleepDuration'] ?? 0)
                    + (int) ($normalized['remSleepDuration'] ?? 0)
                    + (int) ($normalized['awakeSleepDuration'] ?? 0);
                if ($sumStage > 0) {
                    $normalized['totalSleepDuration'] = $sumStage;
                    $normalized['total_sleep_duration'] = $sumStage;
                }
            }
        }

        if ($bucket === 'data_spo2' && array_key_exists('spo2', $normalized)) {
            $normalized['spo2'] = $this->toNumeric($normalized['spo2']);
        }
        if ($bucket === 'data_stress' && array_key_exists('stress', $normalized)) {
            $normalized['stress'] = $this->toNumeric($normalized['stress']);
        }
        if (in_array($bucket, ['data_activity', 'data_heart_rate_manual', 'data_heart_rate_resting', 'data_heart_rate_max'], true)) {
            if (array_key_exists('hr', $normalized) && !array_key_exists('heartRate', $normalized) && !array_key_exists('heart_rate', $normalized)) {
                $normalized['heartRate'] = $normalized['hr'];
            }
            if (array_key_exists('heartRate', $normalized)) {
                $normalized['heartRate'] = $this->toNumeric($normalized['heartRate']);
            }
            if (array_key_exists('heart_rate', $normalized)) {
                $normalized['heart_rate'] = $this->toNumeric($normalized['heart_rate']);
            }
            if (array_key_exists('hr', $normalized)) {
                $normalized['hr'] = $this->toNumeric($normalized['hr']);
            }
        }

        return $normalized;
    }

    private function rowKey(string $bucket, array $row): string
    {
        $sleepStart = $this->normalizeTimestamp((int) ($row['sleepStart'] ?? $row['sleep_start'] ?? 0));
        $sleepEnd = $this->normalizeTimestamp((int) ($row['sleepEnd'] ?? $row['sleep_end'] ?? 0));
        if ($bucket === 'data_sleep' && ($sleepStart > 0 || $sleepEnd > 0)) {
            $startBucket = $sleepStart > 0 ? (int) (floor($sleepStart / 60) * 60) : 0;
            $endBucket = $sleepEnd > 0 ? (int) (ceil($sleepEnd / 60) * 60) : 0;
            return $bucket . ':sleep:' . $startBucket . '-' . $endBucket;
        }

        $ts = $this->extractSortTimestamp($row);
        if ($ts > 0) {
            // Keep distinct points that share timestamp but differ in payload values.
            // This is important for some Mi Band 10 streams that can emit multiple rows per ts.
            return $bucket . ':ts:' . $ts . ':sig:' . sha1(
                json_encode(
                    $this->ksortRecursive($this->stripTimeFields($row)),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );
        }

        return $bucket . ':row:' . sha1(json_encode($this->ksortRecursive($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function stripTimeFields(array $row): array
    {
        unset(
            $row['timestamp'],
            $row['ts'],
            $row['time'],
            $row['device_time'],
            $row['sleepStart'],
            $row['sleep_start'],
            $row['sleepEnd'],
            $row['sleep_end']
        );

        return $row;
    }

    private function extractSortTimestamp(array $row): int
    {
        $candidates = [
            $row['timestamp'] ?? null,
            $row['ts'] ?? null,
            $row['device_time'] ?? null,
            $row['time'] ?? null,
            $row['sleepStart'] ?? null,
            $row['sleep_start'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $ts = $this->normalizeTimestamp($candidate);
                if ($ts > 0) {
                    return $ts;
                }
            }
            if (is_string($candidate) && $candidate !== '') {
                $ts = strtotime($candidate);
                if ($ts !== false && $ts > 0) {
                    return $ts;
                }
            }
        }

        return 0;
    }

    private function normalizeTimestamp(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }
        $ts = (int) $value;
        if ($ts <= 0) {
            return 0;
        }
        if ($ts > 9999999999) {
            return (int) floor($ts / 1000);
        }

        return $ts;
    }

    private function toNumeric(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $num = (float) $value;
        return floor($num) === $num ? (int) $num : $num;
    }

    private function normalizeSleepDurationSeconds(mixed $value, int $intervalSeconds): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $duration = (int) max(0, round((float) $value));
        if ($duration <= 0) {
            return 0;
        }

        // Some legacy payloads send duration in minutes.
        // For current mobile payload (incl. Mi Band 9/10), duration is seconds.
        // If interval exists and value is still plausible as seconds, keep it.
        if ($duration > 0 && $duration < 1000 && ($intervalSeconds <= 0 || $duration > $intervalSeconds)) {
            $duration *= 60;
        }

        if ($intervalSeconds > 0 && $duration > (int) round($intervalSeconds * 1.5)) {
            return $intervalSeconds;
        }

        return $duration;
    }

    private function ksortRecursive(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $row[$key] = $this->ksortRecursive($value);
            }
        }
        ksort($row);
        return $row;
    }

    private function compactSleepSessions(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        usort($rows, function (array $a, array $b): int {
            $aStart = $this->normalizeTimestamp($a['sleepStart'] ?? $a['sleep_start'] ?? 0);
            $bStart = $this->normalizeTimestamp($b['sleepStart'] ?? $b['sleep_start'] ?? 0);
            if ($aStart !== $bStart) {
                return $aStart <=> $bStart;
            }

            $aEnd = $this->normalizeTimestamp($a['sleepEnd'] ?? $a['sleep_end'] ?? 0);
            $bEnd = $this->normalizeTimestamp($b['sleepEnd'] ?? $b['sleep_end'] ?? 0);
            return $bEnd <=> $aEnd;
        });

        $kept = [];
        foreach ($rows as $row) {
            $start = $this->normalizeTimestamp($row['sleepStart'] ?? $row['sleep_start'] ?? 0);
            $end = $this->normalizeTimestamp($row['sleepEnd'] ?? $row['sleep_end'] ?? 0);
            if ($start <= 0 || $end <= $start) {
                $kept[] = $row;
                continue;
            }

            $covered = false;
            foreach ($kept as $existing) {
                $exStart = $this->normalizeTimestamp($existing['sleepStart'] ?? $existing['sleep_start'] ?? 0);
                $exEnd = $this->normalizeTimestamp($existing['sleepEnd'] ?? $existing['sleep_end'] ?? 0);
                if ($exStart <= 0 || $exEnd <= $exStart) {
                    continue;
                }

                if ($exStart <= $start && $exEnd >= $end) {
                    $covered = true;
                    break;
                }
            }

            if (!$covered) {
                $kept[] = $row;
            }
        }

        usort($kept, function (array $a, array $b): int {
            $ta = $this->extractSortTimestamp($a);
            $tb = $this->extractSortTimestamp($b);
            return $ta <=> $tb;
        });

        return $kept;
    }
}
