<?php

namespace App\Http\Controllers;

use App\Models\Summary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileFitToWorkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'summary_id' => 'nullable|integer',
                'user_id' => 'nullable|integer',
                'employee_id' => 'nullable|integer',
                'company_id' => 'nullable|integer',
                'send_date' => 'nullable|date',
                'is_fit1' => 'nullable',
                'is_fit2' => 'nullable',
                'is_fit3' => 'nullable',
                'fit_to_work_q1' => 'nullable',
                'fit_to_work_q2' => 'nullable',
                'fit_to_work_q3' => 'nullable',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $fit1 = $this->toBinaryAnswer($request->input('fit_to_work_q1', $request->input('is_fit1')));
        $fit2 = $this->toBinaryAnswer($request->input('fit_to_work_q2', $request->input('is_fit2')));
        $fit3 = $this->toBinaryAnswer($request->input('fit_to_work_q3', $request->input('is_fit3')));

        if ($fit1 === null || $fit2 === null || $fit3 === null) {
            return response()->json([
                'message' => 'Invalid answer value. Use YA/TIDAK, true/false, or 1/0.',
            ], 422);
        }

        $summary = $this->findSummary($request);
        if (! $summary) {
            return response()->json([
                'message' => 'Summary not found for provided identity/date.',
            ], 404);
        }

        $summary->is_fit1 = $fit1;
        $summary->is_fit2 = $fit2;
        $summary->is_fit3 = $fit3;
        $summary->fit_to_work_q1 = $fit1;
        $summary->fit_to_work_q2 = $fit2;
        $summary->fit_to_work_q3 = $fit3;
        $summary->fit_to_work_submitted_at = now();
        $summary->save();

        return response()->json([
            'message' => 'Fit to work saved',
            'data' => [
                'summary_id' => $summary->id,
                'send_date' => (string) $summary->send_date,
                'is_fit1' => (int) $summary->is_fit1,
                'is_fit2' => (int) $summary->is_fit2,
                'is_fit3' => (int) $summary->is_fit3,
                'fit_to_work_q1' => (int) $summary->fit_to_work_q1,
                'fit_to_work_q2' => (int) $summary->fit_to_work_q2,
                'fit_to_work_q3' => (int) $summary->fit_to_work_q3,
                'fit_to_work_submitted_at' => optional($summary->fit_to_work_submitted_at)->toDateTimeString(),
            ],
        ]);
    }

    private function findSummary(Request $request): ?Summary
    {
        $summaryId = (int) $request->input('summary_id', 0);
        if ($summaryId > 0) {
            return Summary::query()->whereKey($summaryId)->first();
        }

        $sendDate = (string) $request->input('send_date', now()->toDateString());
        $query = Summary::query()->whereDate('send_date', $sendDate);

        $companyId = (int) $request->input('company_id', 0);
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $userId = (int) $request->input('user_id', 0);
        $employeeId = (int) $request->input('employee_id', 0);

        if ($userId > 0) {
            $query->where('user_id', $userId);
        } elseif ($employeeId > 0) {
            $query->where('employee_id', $employeeId);
        } else {
            return null;
        }

        return $query->orderByDesc('id')->first();
    }

    private function toBinaryAnswer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return 1;
            }
            if ((int) $value === 0) {
                return 0;
            }
            return null;
        }

        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 'y', 'ya', 'yes', 'true'], true)) {
            return 1;
        }

        if (in_array($text, ['0', 'n', 'no', 'tidak', 'false'], true)) {
            return 0;
        }

        return null;
    }
}
