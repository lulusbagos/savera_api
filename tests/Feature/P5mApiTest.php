<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\P5m;
use App\Models\P5mPoint;
use App\Models\Quiz;
use App\Models\QuizItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class P5mApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_prefers_today_quiz_for_the_employee_department(): void
    {
        Carbon::setTestNow('2026-04-23 08:00:00');
        [$user, $company, $department, $employee] = $this->createContext();
        Sanctum::actingAs($user);

        $todayQuiz = $this->createQuiz($company->id, $department->id, 'Quiz Hari Ini', '2026-04-23 07:00:00');
        $this->createQuizItem($todayQuiz->id, 1, 'A');
        $this->createQuizItem($todayQuiz->id, 2, 'B');

        $yesterdayQuiz = $this->createQuiz($company->id, $department->id, 'Quiz Kemarin', '2026-04-22 07:00:00');
        $this->createQuizItem($yesterdayQuiz->id, 1, 'A');
        $this->createQuizItem($yesterdayQuiz->id, 2, 'B');

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/p5m')
            ->assertOk()
            ->assertJsonPath('data.quiz.title', 'Quiz Hari Ini')
            ->assertJsonPath('data.quiz.source', 'today')
            ->assertJsonPath('data.already_submitted', false)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_it_falls_back_to_yesterday_quiz_when_today_is_missing(): void
    {
        Carbon::setTestNow('2026-04-23 08:00:00');
        [$user, $company, $department] = $this->createContext();
        Sanctum::actingAs($user);

        $yesterdayQuiz = $this->createQuiz($company->id, $department->id, 'Quiz Fallback', '2026-04-22 07:00:00');
        $this->createQuizItem($yesterdayQuiz->id, 1, 'A');
        $this->createQuizItem($yesterdayQuiz->id, 2, 'B');

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/p5m')
            ->assertOk()
            ->assertJsonPath('data.quiz.title', 'Quiz Fallback')
            ->assertJsonPath('data.quiz.source', 'yesterday')
            ->assertJsonPath('data.quiz.is_fallback', true);
    }

    public function test_submit_saves_score_and_points_and_marks_today_as_submitted(): void
    {
        Carbon::setTestNow('2026-04-23 08:00:00');
        [$user, $company, $department, $employee] = $this->createContext();
        Sanctum::actingAs($user);

        $quiz = $this->createQuiz($company->id, $department->id, 'Quiz P5M', '2026-04-23 07:00:00');
        $firstItem = $this->createQuizItem($quiz->id, 1, 'A', 5);
        $secondItem = $this->createQuizItem($quiz->id, 2, 'C', 10);

        $payload = [
            'quiz_id' => $quiz->id,
            'answer' => [
                ['id' => $firstItem->id, 'value' => 'A'],
                ['id' => $secondItem->id, 'value' => 'B'],
            ],
        ];

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/p5m', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Successfully created')
            ->assertJsonPath('data.score', 5);

        $this->assertSame(1, P5m::count());
        $this->assertSame(2, P5mPoint::count());
        $this->assertDatabaseHas('p5m', [
            'date' => '2026-04-23',
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'score' => 5,
            'platform' => 'savera',
        ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/p5m')
            ->assertOk()
            ->assertJsonPath('data.already_submitted', true)
            ->assertJsonPath('data.today_score.score', 5)
            ->assertJsonPath('data.scores.0.quiz_title', 'Quiz P5M');
    }

    public function test_scores_endpoint_matches_web_style_recent_history(): void
    {
        Carbon::setTestNow('2026-04-23 08:00:00');
        [$user, $company, $department, $employee] = $this->createContext();
        Sanctum::actingAs($user);

        $quiz = $this->createQuiz($company->id, $department->id, 'Quiz Histori', '2026-04-22 07:00:00');

        P5m::create([
            'date' => '2026-04-22',
            'quiz_id' => $quiz->id,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'code' => $employee->code,
            'fullname' => $employee->fullname,
            'job' => $employee->job,
            'platform' => 'savera',
            'score' => 80,
        ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/p5m/scores')
            ->assertOk()
            ->assertJsonPath('data.0.quiz_title', 'Quiz Histori')
            ->assertJsonPath('data.0.score', 80)
            ->assertJsonPath('data.0.date', '2026-04-22');
    }

    /**
     * @return array{0: User, 1: Company, 2: Department, 3: Employee}
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

        return [$user, $company, $department, $employee];
    }

    private function createQuiz(int $companyId, int $departmentId, string $title, string $createdAt): Quiz
    {
        $quiz = Quiz::create([
            'title' => $title,
            'content' => 'Isi briefing',
            'department' => json_encode([(string) $departmentId], JSON_THROW_ON_ERROR),
            'status' => 1,
            'company_id' => $companyId,
        ]);

        $quiz->timestamps = false;
        $quiz->created_at = $createdAt;
        $quiz->updated_at = $createdAt;
        $quiz->save();

        return $quiz->fresh();
    }

    private function createQuizItem(int $quizId, int $seq, string $key, int $point = 5): QuizItem
    {
        return QuizItem::create([
            'question' => 'Pertanyaan '.$seq,
            'answer_a' => 'Jawaban A',
            'answer_b' => 'Jawaban B',
            'answer_c' => 'Jawaban C',
            'answer_d' => 'Jawaban D',
            'key' => $key,
            'seq' => $seq,
            'point' => $point,
            'quiz_id' => $quizId,
        ]);
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
