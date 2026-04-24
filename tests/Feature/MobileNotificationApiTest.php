<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MobileNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_notification_list_is_filtered_by_authenticated_username_and_returns_unread_count(): void
    {
        Carbon::setTestNow('2026-04-23 10:30:00');
        [$user, $company] = $this->createContext('operator.a');
        Sanctum::actingAs($user);

        MobileNotification::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'username' => 'operator.a',
            'title' => 'Briefing Pagi',
            'message_html' => '<p>Gunakan APD lengkap.</p>',
            'status' => 0,
            'published_at' => Carbon::now()->subMinutes(5),
        ]);

        MobileNotification::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'username' => 'operator.a',
            'title' => 'Shift Reminder',
            'message_html' => '<p>Mulai shift 11.00.</p>',
            'status' => 1,
            'read_at' => Carbon::now()->subMinute(),
            'published_at' => Carbon::now()->subMinutes(10),
        ]);

        MobileNotification::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'username' => 'operator.b',
            'title' => 'Harus Tersembunyi',
            'message_html' => '<p>Tidak untuk user ini.</p>',
            'status' => 0,
            'published_at' => Carbon::now()->subMinutes(2),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Briefing Pagi');
    }

    public function test_read_endpoint_marks_notification_as_read(): void
    {
        Carbon::setTestNow('2026-04-23 10:30:00');
        [$user, $company] = $this->createContext('operator.a');
        Sanctum::actingAs($user);

        $notification = MobileNotification::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'username' => 'operator.a',
            'title' => 'Notification',
            'message_html' => '<p>Isi</p>',
            'status' => 0,
            'published_at' => Carbon::now()->subMinutes(3),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas('mobile_notifications', [
            'id' => $notification->id,
            'status' => 1,
        ]);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function createContext(string $username): array
    {
        $user = User::factory()->create([
            'name' => $username,
        ]);
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
            'fullname' => 'Operator',
            'job' => 'Operator',
        ]);

        return [$user, $company];
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
}
