<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileNetworkReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_network_report_and_status_round_trip_for_mobile_user(): void
    {
        Config::set('mobile_network.local_base_url', 'http://192.168.151.20:2026/api');
        [$user, $company] = $this->createContext();
        Sanctum::actingAs($user);

        $this->withHeaders($this->apiHeaders($company->code))
            ->postJson('/api/network-report', [
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'network_type' => 'cellular',
                'is_metered' => true,
                'downlink_mbps' => 6.5,
                'uplink_mbps' => 1.2,
                'rtt_ms' => 180,
                'device_signal_level' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.network_type', 'cellular')
            ->assertJsonPath('data.is_metered', true);

        $this->withHeaders($this->apiHeaders($company->code))
            ->getJson('/api/network-status?mac_address=AA:BB:CC:DD:EE:FF')
            ->assertOk()
            ->assertJsonPath('mac_address', 'AA:BB:CC:DD:EE:FF')
            ->assertJsonStructure([
                'request_speed' => ['duration_ms', 'speed_kbps_est', 'tier'],
            ]);

        $this->withHeaders($this->apiHeaders($company->code))
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('network_sync.status_color', 'green')
            ->assertJsonStructure([
                'network_sync' => [
                    'is_local_synced',
                    'status_color',
                    'status_label',
                    'local_base_url',
                    'active_ip_scope',
                    'active_network_type',
                    'reported_at',
                    'employee_id',
                ],
            ]);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function createContext(): array
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

        Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'user_id' => $user->id,
            'code' => 'EMP-001',
            'fullname' => 'Test Employee',
            'job' => 'Operator',
        ]);

        return [$user, $company];
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
}
