<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Summary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiControllerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_summary_response_excludes_large_metric_payload_and_upserts_daily_summary(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();

        Sanctum::actingAs($user);

        $payload = $this->summaryPayload($company->id, $department->id, $shift->id, $device->id, $device->mac_address, $employee->id);

        $firstResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/summary', $payload);

        $firstResponse->assertOk()
            ->assertJson(['message' => 'Successfully created']);

        $firstData = $firstResponse->json('data');
        $this->assertIsArray($firstData);
        $this->assertArrayNotHasKey('user_activity', $firstData);
        $this->assertSame($user->id, $firstData['user_id']);
        $this->assertSame($device->id, $firstData['device_id']);
        $this->assertSame('night', $firstData['sleep_type']);
        $this->assertLessThan(strlen(json_encode($payload, JSON_THROW_ON_ERROR)), strlen($firstResponse->getContent()));

        $this->assertDatabaseHas('summaries', [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'send_date' => '2026-03-26',
            'sleep_type' => 'night',
            'steps' => 1200,
        ]);

        $this->assertSame(1, Summary::count());

        $payload['steps'] = 4321;
        $payload['steps_text'] = '4.321 langkah';

        $secondResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/summary', $payload);

        $secondResponse->assertOk();
        $this->assertSame(1, Summary::count());
        $this->assertDatabaseHas('summaries', [
            'user_id' => $user->id,
            'send_date' => '2026-03-26',
            'sleep_type' => 'night',
            'steps' => 4321,
        ]);
    }

    public function test_detail_response_excludes_large_metric_payload_but_keeps_expected_context(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();

        Sanctum::actingAs($user);

        $payload = [
            'device_time' => '2026-03-26 07:30:00',
            'mac_address' => $device->mac_address,
            'app_version' => '1.2.3 (Android)',
            'employee_id' => $employee->id,
            'user_activity' => $this->largeMetricPayload('activity'),
            'user_sleep' => $this->largeMetricPayload('sleep'),
            'user_stress' => $this->largeMetricPayload('stress'),
            'user_spo2' => $this->largeMetricPayload('spo2'),
            'user_heart_rate_max' => $this->largeMetricPayload('hr_max'),
            'user_heart_rate_resting' => $this->largeMetricPayload('hr_rest'),
            'user_heart_rate_manual' => $this->largeMetricPayload('hr_manual'),
        ];

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/detail', $payload);

        $response->assertOk()
            ->assertJson(['message' => 'Successfully created']);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('user_activity', $data);
        $this->assertSame($employee->id, $data['employee_id']);
        $this->assertSame($user->id, $data['user_id']);
        $this->assertSame($device->id, $data['device_id']);
        $this->assertSame($device->mac_address, $data['mac_address']);
        $this->assertLessThan(strlen(json_encode($payload, JSON_THROW_ON_ERROR)), strlen($response->getContent()));
    }

    /**
     * @return array{0: User, 1: Company, 2: Department, 3: Shift, 4: Device, 5: Employee}
     */
    private function createApiFixtures(): array
    {
        $user = User::factory()->create();
        $company = Company::create([
            'code' => 'UDU',
            'name' => 'Unit Demo',
        ]);
        $department = Department::create([
            'company_id' => $company->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);
        $shift = Shift::create([
            'company_id' => $company->id,
            'code' => 'SHIFT-1',
            'name' => 'Shift 1',
        ]);
        $device = Device::create([
            'company_id' => $company->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'device_name' => 'Watch Test',
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'device_id' => $device->id,
            'user_id' => $user->id,
            'code' => 'EMP-001',
            'fullname' => 'Test Employee',
        ]);

        return [$user, $company, $department, $shift, $device, $employee];
    }

    /**
     * @return array<string, int|string>
     */
    private function summaryPayload(int $companyId, int $departmentId, int $shiftId, int $deviceId, string $macAddress, int $employeeId): array
    {
        return [
            'active' => 80,
            'active_text' => '80 menit',
            'steps' => 1200,
            'steps_text' => '1.200 langkah',
            'heart_rate' => 72,
            'heart_rate_text' => '72 bpm',
            'distance' => 1567,
            'distance_text' => '1,5 km',
            'calories' => 321,
            'calories_text' => '321 kkal',
            'spo2' => 98,
            'spo2_text' => '98%',
            'stress' => 21,
            'stress_text' => 'rendah',
            'sleep' => 420,
            'sleep_text' => '07:00',
            'sleep_start' => 1711414800,
            'sleep_end' => 1711440000,
            'sleep_type' => 'night',
            'light_sleep' => 220,
            'deep_sleep' => 120,
            'rem_sleep' => 60,
            'awake' => 20,
            'wakeup' => 0,
            'status' => 0,
            'device_time' => '2026-03-26 07:45:00',
            'mac_address' => $macAddress,
            'app_version' => '1.2.3 (Android)',
            'user_activity' => $this->largeMetricPayload('activity'),
            'user_sleep' => $this->largeMetricPayload('sleep'),
            'user_stress' => $this->largeMetricPayload('stress'),
            'user_spo2' => $this->largeMetricPayload('spo2'),
            'device_id' => $deviceId,
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'department_id' => $departmentId,
            'shift_id' => $shiftId,
            'is_fit1' => 1,
            'is_fit2' => 1,
            'is_fit3' => 1,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'company' => 'UDU',
        ];
    }

    private function largeMetricPayload(string $label): string
    {
        $rows = [];

        for ($i = 0; $i < 40; $i++) {
            $rows[] = [
                'label' => $label,
                'timestamp' => 1711414800 + ($i * 60),
                'value' => $i + 1,
            ];
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }
}
