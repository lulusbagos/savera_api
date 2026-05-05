<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\P5m;
use App\Models\P5mPoint;
use App\Models\Quiz;
use App\Models\QuizItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $activeQuizId = $selectedQuiz['quiz']['id'] ?? null;
        $activeQuizVersionAt = $selectedQuiz['meta']['quiz_version_at']
            ?? $selectedQuiz['meta']['quiz_updated_at']
            ?? null;

        Log::info('p5m.show.quiz_selection', [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'department_id' => $employee->department_id,
            'candidate_before_department_ids' => $selectedQuiz['debug']['candidate_before_department_ids'] ?? [],
            'candidate_after_department_ids' => $selectedQuiz['debug']['candidate_after_department_ids'] ?? [],
            'selected_quiz_id' => $selectedQuiz['quiz']['id'] ?? null,
            'selected_quiz_title' => $selectedQuiz['quiz']['title'] ?? null,
            'selected_quiz_source' => $selectedQuiz['quiz']['source'] ?? null,
            'selected_quiz_version_at' => $activeQuizVersionAt,
            'null_reason' => $selectedQuiz['debug']['null_reason'] ?? null,
        ]);

        $scores = $this->scoreList($company->id, $employee->id);
        $todayScore = $activeQuizId
            ? $this->todayScoreForQuiz($company->id, $employee->id, (int) $activeQuizId, $today, $activeQuizVersionAt)
            : null;

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
                    'company_code' => $company->code,
                    'company_name' => $company->name,
                    'department_id' => $employee->department_id,
                    'department_name' => $employee->department_name,
                    'company' => [
                        'id' => $employee->company_id,
                        'code' => $company->code,
                        'name' => $company->name,
                    ],
                    'department' => [
                        'id' => $employee->department_id,
                        'name' => $employee->department_name,
                    ],
                ],
                'already_submitted' => $todayScore !== null,
                'can_submit' => $todayScore === null,
                'today_score' => $todayScore,
                'quiz' => $selectedQuiz['quiz'] ?? null,
                'items' => $selectedQuiz['items'] ?? [],
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
                'answer' => 'required|array|min:1',
                'answer.*.id' => 'required|integer',
                'answer.*.value' => 'required|string|size:1',
            ]);
        } catch (ValidationException $e) {
            return response([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        }

        $quiz = $this->findAccessibleQuiz((int) $payload['quiz_id'], $company->id, $employee->department_id);
        if (! $quiz) {
            return response([
                'message' => 'Quiz not found.',
            ], 404);
        }

        $today = Carbon::now()->toDateString();
        $quizVersionAt = $this->quizVersionTimestamp($quiz->id, $this->formatTimestampForCompare($quiz->updated_at));
        $existing = P5m::query()
            ->where('date', $today)
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('quiz_id', $quiz->id)
            ->when(! empty($quizVersionAt), function ($query) use ($quizVersionAt) {
                $query->where('created_at', '>=', $quizVersionAt);
            })
            ->orderByDesc('id')
            ->first(['id', 'date', 'score', 'status', 'quiz_id', 'created_at']);
        if ($existing) {
            return response([
                'message' => 'Already submitted',
                'data' => [
                    'id' => $existing->id,
                    'date' => $existing->date,
                    'score' => (int) ($existing->score ?? 0),
                    'status' => $existing->status,
                    'quiz_id' => (int) ($existing->quiz_id ?? 0),
                    'created_at' => $this->toIsoString($existing->created_at),
                ],
            ]);
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

        $today = Carbon::now()->toDateString();
        $selectedQuiz = $this->resolveQuizForEmployee($company->id, $employee->department_id);
        $activeQuizId = $selectedQuiz['quiz']['id'] ?? null;
        $activeQuizVersionAt = $selectedQuiz['meta']['quiz_version_at']
            ?? $selectedQuiz['meta']['quiz_updated_at']
            ?? null;

        $scores = $this->scoreList($company->id, $employee->id);
        $todayScore = $activeQuizId
            ? $this->todayScoreForQuiz($company->id, $employee->id, (int) $activeQuizId, $today, $activeQuizVersionAt)
            : null;

        return response([
            'message' => 'ok',
            'data' => $scores->values()->all(),
            'meta' => [
                'active_quiz_id' => $activeQuizId,
                'already_submitted' => $todayScore !== null,
                'can_submit' => $todayScore === null,
                'today_score' => $todayScore,
            ],
        ]);
    }

    public function history(Request $request)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $filters = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $limit = (int) ($filters['limit'] ?? 30);
        $page = (int) ($filters['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $query = $this->historyQuery($company->id, $employee->id)
            ->when(! empty($filters['from']), function ($builder) use ($filters) {
                $builder->whereDate('p5m.date', '>=', $filters['from']);
            })
            ->when(! empty($filters['to']), function ($builder) use ($filters) {
                $builder->whereDate('p5m.date', '<=', $filters['to']);
            });

        $total = (clone $query)->count('p5m.id');
        $rows = $query
            ->offset($offset)
            ->limit($limit)
            ->get();

        $items = $this->mapHistoryRows($rows);

        return response([
            'message' => 'ok',
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $limit)),
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'summary' => $this->historySummary(collect($items)),
        ]);
    }

    public function historyDetail(Request $request, int $id)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $history = $this->historyQuery($company->id, $employee->id)
            ->where('p5m.id', $id)
            ->first();

        if (! $history) {
            return response([
                'message' => 'History not found.',
            ], 404);
        }

        return response([
            'message' => 'ok',
            'data' => [
                'id' => $history->id,
                'date' => $history->date,
                'score' => (int) ($history->score ?? 0),
                'status' => $history->status,
                'code' => $history->code,
                'fullname' => $history->fullname,
                'job' => $history->job,
                'company' => [
                    'id' => $history->company_id,
                    'code' => $history->company_code,
                    'name' => $history->company_name,
                ],
                'department' => [
                    'id' => $history->department_id,
                    'name' => $history->department_name,
                ],
                'quiz' => [
                    'id' => $history->quiz_id,
                    'title' => $history->quiz_title,
                ],
                'created_at' => $this->toIsoString($history->created_at),
                'answers' => $answers = $this->answerList($history->id),
                'summary' => [
                    'total_questions' => count($answers),
                    'correct_answers' => count(array_filter($answers, fn ($row) => $row['is_correct'])),
                    'wrong_answers' => count(array_filter($answers, fn ($row) => ! $row['is_correct'])),
                    'earned_points' => array_sum(array_column($answers, 'point')),
                ],
            ],
        ]);
    }

    /**
     * @return array{0: Company|null, 1: Employee|null}
     */
    private function resolveContext(Request $request): array
    {
        $userCompanyId = Employee::query()
            ->where('user_id', $request->user()->id)
            ->value('company_id');

        $company = null;
        $companyCode = strtoupper(trim((string) $request->header('company', '')));
        if ($companyCode !== '') {
            $companies = Company::query()
                ->select(['id', 'code', 'name'])
                ->whereRaw('UPPER(code) = ?', [$companyCode])
                ->orderBy('id')
                ->get();

            if ($companies->isNotEmpty()) {
                if ($userCompanyId) {
                    $company = $companies->firstWhere('id', (int) $userCompanyId);
                }
                if (! $company) {
                    $company = $companies->first();
                }
            }
        }

        if (! $company && $userCompanyId) {
            $company = Company::query()
                ->select(['id', 'code', 'name'])
                ->whereKey($userCompanyId)
                ->first();
        }

        if (! $company) {
            $company = Company::query()
                ->select(['id', 'code', 'name'])
                ->where('status', 1)
                ->orderBy('id')
                ->first();
        }

        if (! $company) {
            return [null, null];
        }

        $employee = Employee::query()
            ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
            ->select([
                'employees.id',
                'employees.company_id',
                'employees.department_id',
                'employees.code',
                'employees.fullname',
                'employees.job',
                'employees.user_id',
                'departments.name as department_name',
            ])
            ->where('employees.company_id', $company->id)
            ->where('employees.user_id', $request->user()->id)
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
     * @return array{quiz: array<string, mixed>|null, items: array<int, array<string, mixed>>, debug: array<string, mixed>}
     */
    private function resolveQuizForEmployee(int $companyId, ?int $departmentId): array
    {
        $candidates = $this->activeQuizCandidates($companyId);
        $candidateBeforeDepartmentIds = $candidates->pluck('id')->values()->all();

        $filtered = $this->filterByDepartment($candidates, $companyId, $departmentId);
        $candidateAfterDepartmentIds = $filtered->pluck('id')->values()->all();

        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $quiz = $filtered->first(function (Quiz $row) use ($today) {
            return optional($row->created_at)->toDateString() === $today;
        });
        $source = 'today';
        if (! $quiz) {
            $quiz = $filtered->first(function (Quiz $row) use ($yesterday) {
                return optional($row->created_at)->toDateString() === $yesterday;
            });
            $source = 'yesterday';
        }
        if (! $quiz) {
            $quiz = $filtered->first();
            $source = 'latest_active';
        }

        if (! $quiz) {
            $reason = empty($candidateBeforeDepartmentIds)
                ? 'no_active_quiz_for_company_or_items_lt_1'
                : 'department_not_matched';

            return [
                'quiz' => null,
                'items' => [],
                'meta' => [
                    'quiz_updated_at' => null,
                    'quiz_version_at' => null,
                ],
                'debug' => [
                    'candidate_before_department_ids' => $candidateBeforeDepartmentIds,
                    'candidate_after_department_ids' => $candidateAfterDepartmentIds,
                    'null_reason' => $reason,
                ],
            ];
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

        if (count($items) < 1) {
            return [
                'quiz' => null,
                'items' => [],
                'meta' => [
                    'quiz_updated_at' => null,
                    'quiz_version_at' => null,
                ],
                'debug' => [
                    'candidate_before_department_ids' => $candidateBeforeDepartmentIds,
                    'candidate_after_department_ids' => $candidateAfterDepartmentIds,
                    'null_reason' => 'quiz_items_lt_1',
                ],
            ];
        }

        $quizVersionAt = $this->quizVersionTimestamp($quiz->id, $this->formatTimestampForCompare($quiz->updated_at));

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
            'meta' => [
                'quiz_updated_at' => $this->formatTimestampForCompare($quiz->updated_at),
                'quiz_version_at' => $quizVersionAt,
            ],
            'debug' => [
                'candidate_before_department_ids' => $candidateBeforeDepartmentIds,
                'candidate_after_department_ids' => $candidateAfterDepartmentIds,
            ],
        ];
    }

    private function activeQuizCandidates(int $companyId): Collection
    {
        return $this->activeQuizBaseQuery($companyId)
            ->whereIn('id', function ($query) {
                $query->from('quiz_items')
                    ->select('quiz_id')
                    ->whereNull('deleted_at')
                    ->groupBy('quiz_id')
                    ->havingRaw('COUNT(*) >= 1');
            })
            ->orderByDesc('id')
            ->get();
    }

    private function activeQuizBaseQuery(int $companyId)
    {
        return Quiz::query()
            ->where('company_id', $companyId)
            ->where('status', 1);
    }

    private function quizVersionTimestamp(int $quizId, ?string $quizUpdatedAt = null): ?string
    {
        $itemVersionAt = QuizItem::query()
            ->where('quiz_id', $quizId)
            ->selectRaw('MAX(COALESCE(updated_at, created_at)) as version_at')
            ->value('version_at');

        $timestamps = collect([$quizUpdatedAt, $itemVersionAt])
            ->filter(fn ($value) => ! empty($value))
            ->map(fn ($value) => Carbon::parse($value));

        return $timestamps->isEmpty()
            ? null
            : $timestamps->max()->format('Y-m-d H:i:s');
    }

    private function formatTimestampForCompare($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function filterByDepartment(Collection $quizzes, int $companyId, ?int $departmentId): Collection
    {
        return $quizzes
            ->filter(function (Quiz $quiz) use ($companyId, $departmentId) {
                return $this->quizMatchesDepartment($companyId, $quiz->department, $departmentId);
            })
            ->values();
    }

    private function findAccessibleQuiz(int $quizId, int $companyId, ?int $departmentId): ?Quiz
    {
        return $this->activeQuizBaseQuery($companyId)
            ->whereIn('id', function ($query) {
                $query->from('quiz_items')
                    ->select('quiz_id')
                    ->whereNull('deleted_at')
                    ->groupBy('quiz_id')
                    ->havingRaw('COUNT(*) >= 1');
            })
            ->whereKey($quizId)
            ->get()
            ->first(function (Quiz $quiz) use ($companyId, $departmentId) {
                return $this->quizMatchesDepartment($companyId, $quiz->department, $departmentId);
            });
    }

    private function quizMatchesDepartment(int $companyId, $rawDepartment, ?int $employeeDepartmentId): bool
    {
        if ($employeeDepartmentId === null) {
            return true;
        }

        $departmentList = $this->normalizeDepartmentValues($rawDepartment);
        if (empty($departmentList)) {
            return true;
        }

        if ($this->containsGeneralDepartment($companyId, $departmentList)) {
            return true;
        }

        return in_array((int) $employeeDepartmentId, $departmentList, true);
    }

    private function containsGeneralDepartment(int $companyId, array $departmentIds): bool
    {
        return Department::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $departmentIds)
            ->get(['id', 'name', 'code'])
            ->contains(function (Department $department) {
                $haystack = strtolower(trim((string) ($department->name ?? ''))) . ' ' . strtolower(trim((string) ($department->code ?? '')));

                return str_contains($haystack, 'general') || str_contains($haystack, 'default');
            });
    }

    private function normalizeDepartmentValues($rawDepartment): array
    {
        if ($rawDepartment === null) {
            return [];
        }

        $text = trim((string) $rawDepartment);
        if ($text === '' || strtolower($text) === 'null') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_array($decoded)) {
                return collect($decoded)
                    ->flatMap(function ($value) {
                        if (is_string($value) && str_contains($value, ',')) {
                            return explode(',', $value);
                        }

                        return [$value];
                    })
                    ->filter(fn ($value) => is_numeric($value))
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values()
                    ->all();
            }

            if (is_numeric($decoded)) {
                return [(int) $decoded];
            }

            if (is_string($decoded)) {
                $text = $decoded;
            }
        }

        $normalized = trim($text, "[] ");
        if ($normalized === '') {
            return [];
        }

        return collect(explode(',', $normalized))
            ->map(function ($value) {
                return trim((string) $value, " \"'");
            })
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function scoreList(int $companyId, int $employeeId): Collection
    {
        return $this->historyQuery($companyId, $employeeId)
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'date' => $row->date,
                    'score' => (int) ($row->score ?? 0),
                    'quiz_id' => (int) ($row->quiz_id ?? 0),
                    'status' => $row->status,
                    'code' => $row->code,
                    'fullname' => $row->fullname,
                    'job' => $row->job,
                    'company' => [
                        'id' => $row->company_id,
                        'code' => $row->company_code,
                        'name' => $row->company_name,
                    ],
                    'department' => [
                        'id' => $row->department_id,
                        'name' => $row->department_name,
                    ],
                    'quiz' => [
                        'id' => (int) ($row->quiz_id ?? 0),
                        'title' => $row->quiz_title,
                    ],
                    'quiz_title' => $row->quiz_title,
                    'created_at' => $this->toIsoString($row->created_at),
                ];
            });
    }

    private function todayScoreForQuiz(int $companyId, int $employeeId, int $quizId, string $date, ?string $quizUpdatedAt = null): ?array
    {
        $row = P5m::query()
            ->selectRaw("p5m.id, p5m.date, p5m.score, p5m.status, p5m.created_at, p5m.quiz_id, p5m.code, p5m.fullname, p5m.job, p5m.company_id, p5m.department_id, COALESCE(quizzes.title, 'P5M') as quiz_title, companies.code as company_code, companies.name as company_name, departments.name as department_name")
            ->leftJoin('quizzes', 'p5m.quiz_id', '=', 'quizzes.id')
            ->leftJoin('companies', 'p5m.company_id', '=', 'companies.id')
            ->leftJoin('departments', 'p5m.department_id', '=', 'departments.id')
            ->where('p5m.company_id', $companyId)
            ->where('p5m.employee_id', $employeeId)
            ->where('p5m.quiz_id', $quizId)
            ->where('p5m.date', $date)
            ->when(! empty($quizUpdatedAt), function ($query) use ($quizUpdatedAt) {
                $query->where('p5m.created_at', '>=', $quizUpdatedAt);
            })
            ->orderByDesc('p5m.id')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'date' => $row->date,
            'score' => (int) ($row->score ?? 0),
            'status' => $row->status,
            'code' => $row->code,
            'fullname' => $row->fullname,
            'job' => $row->job,
            'company' => [
                'id' => $row->company_id,
                'code' => $row->company_code,
                'name' => $row->company_name,
            ],
            'department' => [
                'id' => $row->department_id,
                'name' => $row->department_name,
            ],
            'quiz' => [
                'id' => $row->quiz_id,
                'title' => $row->quiz_title,
            ],
            'quiz_title' => $row->quiz_title,
            'created_at' => $this->toIsoString($row->created_at),
        ];
    }

    private function historyList(int $companyId, int $employeeId): Collection
    {
        return $this->mapHistoryRows(
            $this->historyQuery($companyId, $employeeId)
            ->limit(30)
            ->get()
        );
    }

    private function mapHistoryRows($rows): Collection
    {
        return collect($rows)
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'date' => $row->date,
                    'score' => (int) ($row->score ?? 0),
                    'status' => $row->status,
                    'code' => $row->code,
                    'fullname' => $row->fullname,
                    'job' => $row->job,
                    'company' => [
                        'id' => $row->company_id,
                        'code' => $row->company_code,
                        'name' => $row->company_name,
                    ],
                    'department' => [
                        'id' => $row->department_id,
                        'name' => $row->department_name,
                    ],
                    'quiz' => [
                        'id' => $row->quiz_id,
                        'title' => $row->quiz_title,
                    ],
                    'quiz_title' => $row->quiz_title,
                    'created_at' => $this->toIsoString($row->created_at),
                ];
            })
            ->values();
    }

    private function historyQuery(int $companyId, int $employeeId)
    {
        return P5m::query()
            ->selectRaw("p5m.id, p5m.date, p5m.score, p5m.status, p5m.created_at, p5m.quiz_id, p5m.code, p5m.fullname, p5m.job, p5m.company_id, p5m.department_id, COALESCE(quizzes.title, 'P5M') as quiz_title, companies.code as company_code, companies.name as company_name, departments.name as department_name")
            ->leftJoin('quizzes', 'p5m.quiz_id', '=', 'quizzes.id')
            ->leftJoin('companies', 'p5m.company_id', '=', 'companies.id')
            ->leftJoin('departments', 'p5m.department_id', '=', 'departments.id')
            ->where('p5m.company_id', $companyId)
            ->where('p5m.employee_id', $employeeId)
            ->orderByDesc('p5m.id');
    }

    private function answerList(int $p5mId): array
    {
        return P5mPoint::query()
            ->leftJoin('quiz_items', 'p5m_point.item_id', '=', 'quiz_items.id')
            ->where('p5m_point.p5m_id', $p5mId)
            ->orderByRaw('COALESCE(p5m_point.seq, quiz_items.seq, p5m_point.id)')
            ->orderBy('p5m_point.id')
            ->get([
                'p5m_point.id',
                'p5m_point.item_id',
                'p5m_point.seq',
                'p5m_point.key',
                'p5m_point.answer',
                'p5m_point.point',
                'quiz_items.question',
                'quiz_items.answer_a',
                'quiz_items.answer_b',
                'quiz_items.answer_c',
                'quiz_items.answer_d',
            ])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'item_id' => $row->item_id,
                    'seq' => $row->seq,
                    'question' => $row->question,
                    'correct_answer' => $row->key,
                    'selected_answer' => $row->answer,
                    'is_correct' => strtoupper((string) $row->key) === strtoupper((string) $row->answer),
                    'point' => (int) ($row->point ?? 0),
                    'options' => [
                        ['key' => 'A', 'label' => $row->answer_a],
                        ['key' => 'B', 'label' => $row->answer_b],
                        ['key' => 'C', 'label' => $row->answer_c],
                        ['key' => 'D', 'label' => $row->answer_d],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function historySummary(Collection $items): array
    {
        $totalItems = $items->count();
        $scores = $items->pluck('score');

        return [
            'total_records' => $totalItems,
            'average_score' => $totalItems > 0 ? round($scores->avg(), 2) : 0,
            'highest_score' => $totalItems > 0 ? (int) $scores->max() : 0,
            'lowest_score' => $totalItems > 0 ? (int) $scores->min() : 0,
        ];
    }

    private function toIsoString($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->toISOString();
    }
}
