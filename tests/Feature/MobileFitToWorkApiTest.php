<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Summary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileFitToWorkApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mobile_fit_to_work_can_update_summary_by_summary_id(): void
    {
        Carbon::setTestNow('2026-04-30 08:00:00');
        [$user, $company, $employee] = $this->createContext();
        Sanctum::actingAs($user);

        $summary = Summary::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'send_date' => '2026-04-30',
            'send_time' => '08:00:00',
            'sleep_type' => 'night',
            'active' => 80,
            'steps' => 1200,
            'heart_rate' => 72,
            'distance' => 1500,
            'calories' => 300,
            'spo2' => 98,
            'stress' => 20,
            'sleep' => 420,
            'device_time' => '2026-04-30 07:50:00',
            'app_version' => '1.2.3',
        ]);

        $this->withHeaders($this->apiHeaders($company->code))
            ->postJson('/api/mobile/fit-to-work', [
                'summary_id' => $summary->id,
                'fit_to_work_q1' => 'YA',
                'fit_to_work_q2' => 'TIDAK',
                'fit_to_work_q3' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.summary_id', $summary->id)
            ->assertJsonPath('data.fit_to_work_q1', 1)
            ->assertJsonPath('data.fit_to_work_q2', 0)
            ->assertJsonPath('data.fit_to_work_q3', 1);

        $this->assertDatabaseHas('summaries', [
            'id' => $summary->id,
            'fit_to_work_q1' => 1,
            'fit_to_work_q2' => 0,
            'fit_to_work_q3' => 1,
            'is_fit1' => 1,
            'is_fit2' => 0,
            'is_fit3' => 1,
        ]);
    }

    public function test_legacy_fit_to_work_route_can_update_by_employee_identity(): void
    {
        Carbon::setTestNow('2026-04-30 08:00:00');
        [$user, $company, $employee] = $this->createContext();
        Sanctum::actingAs($user);

        Summary::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'send_date' => '2026-04-30',
            'send_time' => '08:00:00',
            'sleep_type' => 'night',
            'active' => 80,
            'steps' => 1200,
            'heart_rate' => 72,
            'distance' => 1500,
            'calories' => 300,
            'spo2' => 98,
            'stress' => 20,
            'sleep' => 420,
            'device_time' => '2026-04-30 07:50:00',
            'app_version' => '1.2.3',
        ]);

        $this->withHeaders($this->apiHeaders($company->code))
            ->postJson('/api/fit-to-work', [
                'employee_id' => $employee->id,
                'company_id' => $company->id,
                'send_date' => '2026-04-30',
                'is_fit1' => false,
                'is_fit2' => true,
                'is_fit3' => false,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Fit to work saved')
            ->assertJsonPath('data.fit_to_work_q1', 0)
            ->assertJsonPath('data.fit_to_work_q2', 1)
            ->assertJsonPath('data.fit_to_work_q3', 0);

        $this->assertDatabaseHas('summaries', [
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'send_date' => '2026-04-30',
            'fit_to_work_q1' => 0,
            'fit_to_work_q2' => 1,
            'fit_to_work_q3' => 0,
        ]);
    }

    /**
     * @return array{0: User, 1: Company, 2: Employee}
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
        $employee = Employee::create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'user_id' => $user->id,
            'code' => 'EMP-001',
            'fullname' => 'Test Employee',
            'job' => 'Operator',
        ]);

        return [$user, $company, $employee];
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
