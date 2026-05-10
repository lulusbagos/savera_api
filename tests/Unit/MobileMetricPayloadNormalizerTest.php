<?php

namespace Tests\Unit;

use App\Services\MobileMetricPayloadNormalizer;
use PHPUnit\Framework\TestCase;

class MobileMetricPayloadNormalizerTest extends TestCase
{
    public function test_it_infers_awake_duration_from_session_interval_when_missing(): void
    {
        $service = new MobileMetricPayloadNormalizer();

        $payload = [[
            'sleepStart' => 1746840840,
            'sleepEnd' => 1746864480, // 23640 seconds
            'lightSleepDuration' => 9420,
            'deepSleepDuration' => 5340,
            'remSleepDuration' => 4980,
            'awakeSleepDuration' => 0,
            'totalSleepDuration' => 19740,
        ]];

        $normalized = json_decode($service->normalizeForBucket('data_sleep', $payload), true, 512, JSON_THROW_ON_ERROR);
        $row = $normalized[0] ?? [];

        $this->assertSame(3900, (int) ($row['awakeSleepDuration'] ?? 0));
        $this->assertSame(23640, (int) ($row['totalSleepDuration'] ?? 0));
    }

    public function test_it_keeps_total_sleep_aligned_with_stage_plus_awake(): void
    {
        $service = new MobileMetricPayloadNormalizer();

        $payload = [[
            'sleepStart' => 1746840840,
            'sleepEnd' => 1746864480,
            'light_sleep_duration' => 9420,
            'deep_sleep_duration' => 5340,
            'rem_sleep_duration' => 4980,
            'awake_sleep_duration' => 1800,
            'total_sleep_duration' => 20000,
        ]];

        $normalized = json_decode($service->normalizeForBucket('data_sleep', $payload), true, 512, JSON_THROW_ON_ERROR);
        $row = $normalized[0] ?? [];

        // 9420 + 5340 + 4980 + 1800 = 21540
        $this->assertSame(21540, (int) ($row['totalSleepDuration'] ?? 0));
        $this->assertSame(1800, (int) ($row['awakeSleepDuration'] ?? 0));
    }
}
