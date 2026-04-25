<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\P5m;
use App\Models\P5mPoint;
use App\Models\Quiz;
use App\Models\QuizItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class P5mController extends Controller
{
    public function show(Request $request)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $today = Carbon::now()->toDateString();
        $selectedQuiz = $this->resolveQuizForEmployee($company->id, $employee->department_id);
        $scores = $this->scoreList($company->id, $employee->id);
        $todayScore = $scores->firstWhere('date', $today);

        return response([
            'message' => 'ok',
            'data' => [
                'date' => $today,
                'employee' => [
                    'id' => $employee->id,
                    'code' => $employee->code,
                    'fullname' => $employee->fullname,
                    'job' => $employee->job,
                    'company_id' => $employee->company_id,
                    'department_id' => $employee->department_id,
                ],
                'already_submitted' => $todayScore !== null,
                'today_score' => $todayScore,
                'quiz' => $selectedQuiz ? $selectedQuiz['quiz'] : null,
                'items' => $selectedQuiz ? $selectedQuiz['items'] : [],
                'scores' => $scores->values()->all(),
            ],
        ]);
    }

    public function submit(Request $request)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        try {
            $payload = $request->validate([
                'quiz_id' => 'required|integer',
                'answer' => 'required|array|min:2',
                'answer.*.id' => 'required|integer',
                'answer.*.value' => 'required|string|size:1',
            ]);
        } catch (ValidationException $e) {
            return response([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        }

        $today = Carbon::now()->toDateString();
        $exists = P5m::query()
            ->where('date', $today)
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->exists();
        if ($exists) {
            return response([
                'message' => 'Already submitted',
                'data' => null,
            ]);
        }

        $quiz = $this->findAccessibleQuiz((int) $payload['quiz_id'], $company->id, $employee->department_id);
        if (! $quiz) {
            return response([
                'message' => 'Quiz not found.',
            ], 404);
        }

        $result = DB::transaction(function () use ($payload, $quiz, $today, $company, $employee) {
            $p5m = P5m::create([
                'date' => $today,
                'quiz_id' => $quiz->id,
                'employee_id' => $employee->id,
                'company_id' => $company->id,
                'department_id' => $employee->department_id,
                'code' => $employee->code,
                'fullname' => $employee->fullname,
                'job' => $employee->job,
                'platform' => 'savera',
            ]);

            $itemIds = collect($payload['answer'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $quizItems = QuizItem::query()
                ->where('quiz_id', $quiz->id)
                ->whereIn('id', $itemIds)
                ->get(['id', 'key', 'point', 'seq'])
                ->keyBy('id');

            $answerRows = [];
            $totalScore = 0;

            foreach ($payload['answer'] as $row) {
                $itemId = (int) ($row['id'] ?? 0);
                $answerValue = strtoupper(substr((string) ($row['value'] ?? ''), 0, 1));
                $quizItem = $quizItems->get($itemId);
                if (! $quizItem) {
                    continue;
                }

                $key = strtoupper(substr((string) $quizItem->key, 0, 1));
                $point = $key === $answerValue ? (int) ($quizItem->point ?? 0) : 0;
                $totalScore += $point;

                $answerRows[] = [
                    'p5m_id' => $p5m->id,
                    'quiz_id' => $quiz->id,
                    'item_id' => $itemId,
                    'seq' => $quizItem->seq,
                    'key' => $key,
                    'answer' => $answerValue,
                    'point' => $point,
                ];
            }

            if (! empty($answerRows)) {
                P5mPoint::insert($answerRows);
            }

            $p5m->score = $totalScore;
            $p5m->save();

            return $p5m;
        });

        return response([
            'message' => 'Successfully created',
            'data' => [
                'id' => $result->id,
                'date' => $result->date,
                'score' => $result->score,
                'quiz_id' => $result->quiz_id,
            ],
        ]);
    }

    public function scores(Request $request)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        return response([
            'message' => 'ok',
            'data' => $this->scoreList($company->id, $employee->id)->values()->all(),
        ]);
    }

    /**
     * @return array{0: Company|null, 1: Employee|null}
     */
    private function resolveContext(Request $request): array
    {
        $companyCode = (string) $request->header('company', '');
        $company = $companyCode !== ''
            ? Company::query()
                ->select(['id', 'code'])
                ->where('code', $companyCode)
                ->first()
            : null;

        if (! $company) {
            $companyId = Employee::query()
                ->where('user_id', $request->user()->id)
                ->value('company_id');

            $company = $companyId
                ? Company::query()
                    ->select(['id', 'code'])
                    ->whereKey($companyId)
                    ->first()
                : null;
        }

        if (! $company) {
            $company = Company::query()
                ->select(['id', 'code'])
                ->where('status', 1)
                ->orderBy('id')
                ->first();
        }

        if (! $company) {
            return [null, null];
        }

        $employee = Employee::query()
            ->select(['id', 'company_id', 'department_id', 'code', 'fullname', 'job', 'user_id'])
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return [$company, $employee];
    }

    private function missingContextResponse(?Company $company, ?Employee $employee)
    {
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        if (! $employee) {
            return response([
                'message' => 'Employee not found.',
            ], 404);
        }

        return response([
            'message' => 'Context not found.',
        ], 404);
    }

    /**
     * @return array{quiz: array<string, mixed>, items: array<int, array<string, mixed>>}|null
     */
    private function resolveQuizForEmployee(int $companyId, ?int $departmentId): ?array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $quiz = $this->latestQuizForDate($companyId, $departmentId, $today);
        $source = 'today';
        if (! $quiz) {
            $quiz = $this->latestQuizForDate($companyId, $departmentId, $yesterday);
            $source = 'yesterday';
        }
        if (! $quiz) {
            $quiz = $this->latestActiveQuiz($companyId, $departmentId);
            $source = 'latest_active';
        }
        if (! $quiz) {
            return null;
        }

        $items = QuizItem::query()
            ->where('quiz_id', $quiz->id)
            ->orderByRaw('COALESCE(seq, id)')
            ->orderBy('id')
            ->get(['id', 'question', 'answer_a', 'answer_b', 'answer_c', 'answer_d', 'seq'])
            ->map(function (QuizItem $item) {
                return [
                    'id' => $item->id,
                    'seq' => $item->seq,
                    'question' => $item->question,
                    'options' => [
                        ['key' => 'A', 'label' => $item->answer_a],
                        ['key' => 'B', 'label' => $item->answer_b],
                        ['key' => 'C', 'label' => $item->answer_c],
                        ['key' => 'D', 'label' => $item->answer_d],
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'content' => $quiz->content,
                'status' => (int) $quiz->status,
                'created_at' => optional($quiz->created_at)->toISOString(),
                'source' => $source,
                'source_date' => optional($quiz->created_at)->toDateString(),
                'is_fallback' => $source !== 'today',
            ],
            'items' => $items,
        ];
    }

    private function latestQuizForDate(int $companyId, ?int $departmentId, Carbon $date): ?Quiz
    {
        return $this->quizBaseQuery($companyId, $departmentId)
            ->whereDate('created_at', $date->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    private function latestActiveQuiz(int $companyId, ?int $departmentId): ?Quiz
    {
        return $this->quizBaseQuery($companyId, $departmentId)
            ->orderByDesc('id')
            ->first();
    }

    private function findAccessibleQuiz(int $quizId, int $companyId, ?int $departmentId): ?Quiz
    {
        return $this->quizBaseQuery($companyId, $departmentId)
            ->whereKey($quizId)
            ->first();
    }

    private function quizBaseQuery(int $companyId, ?int $departmentId)
    {
        return Quiz::query()
            ->where('company_id', $companyId)
            ->where('status', 1)
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $query->where(function ($inner) use ($departmentId) {
                    $inner->where('department', 'LIKE', '%"'.$departmentId.'"%')
                        ->orWhereNull('department')
                        ->orWhere('department', '');
                });
            });
    }

    private function scoreList(int $companyId, int $employeeId): Collection
    {
        return P5m::query()
            ->selectRaw('p5m.id, p5m.date, p5m.score, p5m.status, p5m.created_at, quizzes.title as quiz_title')
            ->join('quizzes', 'p5m.quiz_id', '=', 'quizzes.id')
            ->where('p5m.company_id', $companyId)
            ->where('p5m.employee_id', $employeeId)
            ->where('p5m.platform', 'savera')
            ->orderByDesc('p5m.id')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'date' => $row->date,
                    'score' => (int) ($row->score ?? 0),
                    'status' => $row->status,
                    'quiz_title' => $row->quiz_title,
                    'created_at' => optional($row->created_at)->toISOString(),
                ];
            });
    }
}
