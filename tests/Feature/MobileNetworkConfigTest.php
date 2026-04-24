<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileNetworkConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_exposes_mobile_network_config_for_public_to_local_sync(): void
    {
        Config::set('mobile_network.public_base_url', 'https://public.example.com/api');
        Config::set('mobile_network.local_base_url', 'http://10.10.10.25:9000/api');
        Config::set('mobile_network.preferred_route', 'local');

        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Savera-Public-Base-Url', 'https://public.example.com/api')
            ->assertHeader('X-Savera-Local-Base-Url', 'http://10.10.10.25:9000/api')
            ->assertHeader('X-Savera-Preferred-Route', 'local')
            ->assertJsonPath('network.public_base_url', 'https://public.example.com/api')
            ->assertJsonPath('network.local_base_url', 'http://10.10.10.25:9000/api')
            ->assertJsonPath('network.preferred_route', 'local');
    }

    public function test_authenticated_mobile_api_response_includes_network_headers(): void
    {
        Config::set('mobile_network.public_base_url', 'https://public.example.com/api');
        Config::set('mobile_network.local_base_url', 'http://10.10.10.25:9000/api');
        Config::set('mobile_network.preferred_route', 'local');

        [$user, $company] = $this->createApiFixtures();
        Sanctum::actingAs($user);

        $this->withHeaders([
            'company' => $company->code,
        ])->getJson('/api/profile')
            ->assertOk()
            ->assertHeader('X-Savera-Public-Base-Url', 'https://public.example.com/api')
            ->assertHeader('X-Savera-Local-Base-Url', 'http://10.10.10.25:9000/api')
            ->assertHeader('X-Savera-Preferred-Route', 'local');
    }

    /**
     * @return array{0: User, 1: Company, 2: Department, 3: Shift, 4: Device, 5: Employee}
     */
    private function createApiFixtures(): array
    {
        $user = User::factory()->create([
            'name' => 'mobile-user',
            'email' => 'mobile@example.test',
        ]);

        $company = Company::create([
            'name' => 'Indexim Coalindo',
            'code' => 'UDU',
        ]);

        $department = Department::create([
            'company_id' => $company->id,
            'name' => 'Operations',
        ]);

        $shift = Shift::create([
            'name' => 'Shift A',
            'time_in' => '06:00:00',
            'time_out' => '18:00:00',
            'late' => '00:15:00',
        ]);

        $device = Device::create([
            'company_id' => $company->id,
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'device_name' => 'Watch Alpha',
            'brand' => 'Garmin',
            'auth_key' => 'sample-auth-key',
            'is_active' => 1,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'device_id' => $device->id,
            'user_id' => $user->id,
            'fullname' => 'Mobile User',
            'code' => 'EMP001',
            'status' => 1,
        ]);

        return [$user, $company, $department, $shift, $device, $employee];
    }
}
