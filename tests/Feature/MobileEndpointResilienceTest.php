<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\Ticket;
use App\Models\Summary;
use App\Models\User;
use App\Support\MobileIngestRuntime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileEndpointResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('framework/testing/disks/mobile_metrics'));
        File::ensureDirectoryExists(storage_path('framework/testing/disks/mobile_metrics'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_profile_requires_authentication_for_mobile_session(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_summary_rejects_missing_company_header(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $this->postJson('/api/summary', $this->summaryPayload($employee->id, $company->id, $department->id, $shift->id, $device->mac_address))
            ->assertNotFound()
            ->assertJson([
                'message' => 'Company not found.',
            ]);
    }

    public function test_summary_rejects_unknown_device_mac(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/summary', $this->summaryPayload($employee->id, $company->id, $department->id, $shift->id, '00:11:22:33:44:55'))
            ->assertNotFound()
            ->assertJson([
                'message' => "Your device's MAC address is unavailable.",
            ]);
    }

    public function test_summary_is_safe_to_retry_without_creating_duplicate_rows(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');
        Storage::fake(MobileIngestRuntime::storageDisk('local'));

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $payload = $this->summaryPayload($employee->id, $company->id, $department->id, $shift->id, $device->mac_address);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/summary', $payload)
            ->assertOk()
            ->assertJson([
                'message' => 'Successfully created',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/summary', $payload)
            ->assertOk()
            ->assertJson([
                'message' => 'Successfully created',
            ]);

        $this->assertSame(1, Summary::count());
        $this->assertDatabaseHas('summaries', [
            'send_date' => '2026-03-26',
            'sleep_type' => 'night',
            'steps' => 1200,
        ]);
    }

    public function test_detail_keeps_one_stable_file_set_when_mobile_resends_the_same_payload(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');
        Storage::fake(MobileIngestRuntime::storageDisk('local'));

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $payload = $this->detailPayload($employee->id, $device->mac_address);

        $firstResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/detail', $payload);

        $firstResponse->assertOk()
            ->assertJson([
                'message' => 'Successfully created',
            ]);

        $secondResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/detail', $payload);

        $secondResponse->assertOk()
            ->assertJson([
                'message' => 'Successfully created',
            ]);

        $expectedFiles = [
            'data_activity/2026/03/26/00000000000000000001.json',
            'data_sleep/2026/03/26/00000000000000000001.json',
            'data_stress/2026/03/26/00000000000000000001.json',
            'data_spo2/2026/03/26/00000000000000000001.json',
            'data_heart_rate_max/2026/03/26/00000000000000000001.json',
            'data_heart_rate_resting/2026/03/26/00000000000000000001.json',
            'data_heart_rate_manual/2026/03/26/00000000000000000001.json',
        ];

        $this->assertEqualsCanonicalizing($expectedFiles, Storage::disk(MobileIngestRuntime::storageDisk('local'))->allFiles());
        foreach ($expectedFiles as $path) {
            Storage::disk(MobileIngestRuntime::storageDisk('local'))->assertExists($path);
            $decoded = json_decode(Storage::disk(MobileIngestRuntime::storageDisk('local'))->get($path), true);
            $this->assertIsArray($decoded, "Stored metric file {$path} must be valid JSON array/object.");
        }
    }

    public function test_detail_normalizes_malformed_metric_string_to_safe_json_array_file(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');
        Storage::fake(MobileIngestRuntime::storageDisk('local'));

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $payload = $this->detailPayload($employee->id, $device->mac_address);
        $payload['user_activity'] = 'not-a-json-document';

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/detail', $payload)
            ->assertOk()
            ->assertJson([
                'message' => 'Successfully created',
            ]);

        $path = 'data_activity/2026/03/26/00000000000000000001.json';
        Storage::disk(MobileIngestRuntime::storageDisk('local'))->assertExists($path);
        $this->assertSame('[]', Storage::disk(MobileIngestRuntime::storageDisk('local'))->get($path));
    }

    public function test_avatar_rejects_non_image_upload_from_mobile_gallery(): void
    {
        [$user, $company, , , , $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $this->withHeaders($this->apiHeaders())
            ->post('/api/avatar', [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'photo' => UploadedFile::fake()->create('broken.txt', 12, 'text/plain'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_avatar_upload_refreshes_cached_profile_photo(): void
    {
        [$user, $company, , , , $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);
        Storage::fake(MobileIngestRuntime::storageDisk('local'));

        $initialProfile = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/profile');

        $initialProfile->assertOk();
        $this->assertNull($initialProfile->json('employee.photo'));

        $this->withHeaders($this->apiHeaders())
            ->post('/api/avatar', [
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'photo' => UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5qfMsAAAAASUVORK5CYII=')),
            ])
            ->assertOk();

        $refreshedProfile = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/profile');

        $refreshedProfile->assertOk();
        $this->assertSame('avatar/avatar.png', $refreshedProfile->json('employee.photo'));
    }

    public function test_profile_rate_limits_burst_requests_from_the_same_mobile_user(): void
    {
        [$user] = $this->createApiFixtures();
        Sanctum::actingAs($user);
        RateLimiter::clear((string) $user->id);

        $headers = $this->withHeaders($this->apiHeaders());

        for ($i = 0; $i < 120; $i++) {
            $headers->getJson('/api/profile')->assertOk();
        }

        $headers->getJson('/api/profile')
            ->assertStatus(429);
    }

    public function test_ticket_rejects_request_when_summary_is_not_yet_available(): void
    {
        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/ticket/'.$employee->id)
            ->assertNotFound()
            ->assertJson([
                'message' => 'Summary not found.',
            ]);
    }

    public function test_leave_rejects_invalid_payload_from_a_flaky_mobile_form(): void
    {
        [$user] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/leave', [
                'employee_id' => '',
                'type' => 'izin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'phone', 'note']);
    }

    public function test_leave_is_safe_to_retry_without_creating_duplicate_rows(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $payload = [
            'employee_id' => $employee->id,
            'type' => 'izin',
            'phone' => '081234567890',
            'note' => 'Jaringan putus saat submit',
        ];

        $headers = $this->withHeaders($this->apiHeaders());
        $headers->postJson('/api/leave', $payload)->assertOk();
        $headers->postJson('/api/leave', $payload)->assertOk();

        $this->assertSame(1, Leave::count());
        $this->assertDatabaseHas('leaves', [
            'employee_id' => $employee->id,
            'date' => '2026-03-26',
            'type' => 'izin',
        ]);
    }

    public function test_ticket_is_safe_to_retry_after_summary_is_available(): void
    {
        Carbon::setTestNow('2026-03-26 08:00:00');

        [$user, $company, $department, $shift, $device, $employee] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $summaryPayload = $this->summaryPayload($employee->id, $company->id, $department->id, $shift->id, $device->mac_address);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/summary', $summaryPayload)
            ->assertOk();

        $headers = $this->withHeaders($this->apiHeaders());
        $firstTicket = $headers->getJson('/api/ticket/'.$employee->id);
        $secondTicket = $headers->getJson('/api/ticket/'.$employee->id);

        $firstTicket->assertOk();
        $secondTicket->assertOk();

        $this->assertSame(1, Ticket::count());
        $this->assertDatabaseHas('tickets', [
            'employee_id' => $employee->id,
            'date' => '2026-03-26',
        ]);
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
     * @param  string  $macAddress
     * @return array<string, int|string>
     */
    private function summaryPayload(int $employeeId, int $companyId, int $departmentId, int $shiftId, string $macAddress = 'AA:BB:CC:DD:EE:FF'): array
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
    private function detailPayload(int $employeeId, string $macAddress = 'AA:BB:CC:DD:EE:FF'): array
    {
        return [
            'device_time' => '2026-03-26 07:30:00',
            'mac_address' => $macAddress,
            'app_version' => '1.2.3 (Android)',
            'employee_id' => $employeeId,
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
