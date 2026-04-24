<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\Summary;
use App\Models\Ticket;
use App\Models\User;
use App\Support\MobileIngestRuntime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use App\Services\MobileLoadSimulationService;
use Tests\TestCase;

class MobileLoadSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        File::deleteDirectory(storage_path('framework/cache/data'));
        File::ensureDirectoryExists(storage_path('framework/cache/data'));
        File::deleteDirectory(storage_path('framework/testing/disks/mobile_metrics'));
        File::ensureDirectoryExists(storage_path('framework/testing/disks/mobile_metrics'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_many_mobile_users_forcing_retry_uploads_keep_backend_stable(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');
        Storage::fake(MobileIngestRuntime::storageDisk('local'));

        $fleetSize = 15;
        $retries = 3;
        $fleet = $this->createFleet($fleetSize);

        foreach ($fleet as $member) {
            Sanctum::actingAs($member['user']);

            $headers = $this->apiHeaders($member['company']->code);
            $summaryPayload = $this->summaryPayload($member);
            $detailPayload = $this->detailPayload($member);
            $leavePayload = $this->leavePayload($member);

            for ($i = 0; $i < $retries; $i++) {
                $this->withHeaders($headers)
                    ->postJson('/api/summary', $summaryPayload)
                    ->assertOk();

                $this->withHeaders($headers)
                    ->postJson('/api/detail', $detailPayload)
                    ->assertOk();
            }

            $this->withHeaders($headers)
                ->postJson('/api/leave', $leavePayload)
                ->assertOk();

            $this->withHeaders($headers)
                ->postJson('/api/leave', $leavePayload)
                ->assertOk();

            $this->withHeaders($headers)
                ->getJson('/api/ticket/'.$member['employee']->id)
                ->assertOk();

            $this->withHeaders($headers)
                ->getJson('/api/ticket/'.$member['employee']->id)
                ->assertOk();
        }

        $this->assertSame($fleetSize, Summary::count());
        $this->assertSame($fleetSize, Leave::count());
        $this->assertSame($fleetSize, Ticket::count());

        $files = Storage::disk(MobileIngestRuntime::storageDisk('local'))->allFiles();
        $this->assertNotEmpty($files);
        $this->assertGreaterThanOrEqual($fleetSize, count($files));

        $expectedTokens = collect($fleet)
            ->map(fn (array $member): string => str_pad((string) $member['user']->id, 20, '0', STR_PAD_LEFT))
            ->all();
        $actualTokens = collect($files)
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->filter(fn (string $filename): bool => preg_match('/^\d{20}$/', $filename) === 1)
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($expectedTokens, array_values(array_intersect($actualTokens, $expectedTokens)));
    }

    public function test_hundred_mobile_users_forcing_retry_uploads_keep_backend_stable(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        $service = app(MobileLoadSimulationService::class);
        $fleet = $service->createFleet(100, 'SIM100');
        $result = $service->runUploadBatch($fleet, 2, true);

        $this->assertSame(200, $result['summary_calls']);
        $this->assertSame(200, $result['detail_calls']);
        $this->assertSame(200, $result['summary_ok']);
        $this->assertSame(200, $result['detail_ok']);

        $this->assertSame(100, Summary::count());
        $this->assertGreaterThanOrEqual(100, $result['file_count']);
    }

    public function test_simulated_slow_summary_and_detail_uploads_stay_stable(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        $service = app(MobileLoadSimulationService::class);
        $fleet = $service->createFleet(40, 'SIMSLOW');
        $result = $service->runUploadBatch($fleet, 2, true, [
            'summary' => 150,
            'detail' => 250,
        ]);

        $this->assertSame(80, $result['summary_calls']);
        $this->assertSame(80, $result['detail_calls']);
        $this->assertSame(80, $result['summary_ok']);
        $this->assertSame(80, $result['detail_ok']);

        $this->assertSame(40, Summary::count());
        $this->assertGreaterThanOrEqual(40, $result['file_count']);
    }

    /**
     * @return array<int, array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}>
     */
    private function createFleet(int $count): array
    {
        $company = Company::create([
            'code' => 'SIM',
            'name' => 'Simulation Fleet',
        ]);
        $department = Department::create([
            'company_id' => $company->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);
        $shift = Shift::create([
            'company_id' => $company->id,
            'code' => 'DAY',
            'name' => 'Day Shift',
        ]);

        $fleet = [];

        for ($i = 1; $i <= $count; $i++) {
            $user = User::factory()->create([
                'name' => 'Mobile '.$i,
                'email' => 'mobile'.$i.'@example.test',
            ]);
            $device = Device::create([
                'company_id' => $company->id,
                'mac_address' => sprintf('AA:BB:CC:DD:EE:%02d', $i),
                'device_name' => 'Watch '.$i,
            ]);
            $employee = Employee::create([
                'company_id' => $company->id,
                'department_id' => $department->id,
                'device_id' => $device->id,
                'user_id' => $user->id,
                'code' => 'EMP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'fullname' => 'Employee '.$i,
            ]);

            $fleet[] = [
                'user' => $user,
                'company' => $company,
                'department' => $department,
                'shift' => $shift,
                'device' => $device,
                'employee' => $employee,
            ];
        }

        return $fleet;
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, int|string>
     */
    private function summaryPayload(array $member): array
    {
        return [
            'active' => 80,
            'active_text' => '80 menit',
            'steps' => 1200 + $member['employee']->id,
            'steps_text' => 'langkah',
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
            'mac_address' => $member['device']->mac_address,
            'app_version' => '1.2.3 (Android)',
            'user_activity' => $this->largeMetricPayload('activity'),
            'user_sleep' => $this->largeMetricPayload('sleep'),
            'user_stress' => $this->largeMetricPayload('stress'),
            'user_spo2' => $this->largeMetricPayload('spo2'),
            'device_id' => $member['device']->id,
            'employee_id' => $member['employee']->id,
            'company_id' => $member['company']->id,
            'department_id' => $member['department']->id,
            'shift_id' => $member['shift']->id,
            'is_fit1' => 1,
            'is_fit2' => 1,
            'is_fit3' => 1,
        ];
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, string>
     */
    private function detailPayload(array $member): array
    {
        return [
            'device_time' => '2026-03-26 07:30:00',
            'mac_address' => $member['device']->mac_address,
            'app_version' => '1.2.3 (Android)',
            'employee_id' => $member['employee']->id,
            'user_activity' => $this->largeMetricPayload('activity'),
            'user_sleep' => $this->largeMetricPayload('sleep'),
            'user_stress' => $this->largeMetricPayload('stress'),
            'user_spo2' => $this->largeMetricPayload('spo2'),
            'user_heart_rate_max' => $this->largeMetricPayload('hr_max'),
            'user_heart_rate_resting' => $this->largeMetricPayload('hr_rest'),
            'user_heart_rate_manual' => $this->largeMetricPayload('hr_manual'),
        ];
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, string>
     */
    private function leavePayload(array $member): array
    {
        return [
            'employee_id' => $member['employee']->id,
            'type' => 'izin',
            'phone' => '081234567890',
            'note' => 'Jaringan lambat, upload dipaksa ulang',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $companyCode): array
    {
        return [
            'Accept' => 'application/json',
            'company' => $companyCode,
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
