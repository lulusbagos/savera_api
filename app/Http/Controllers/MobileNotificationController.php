<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\MobileNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MobileNotificationController extends Controller
{
    public function index(Request $request)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $query = MobileNotification::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('username', $request->user()->name)
            ->where(function ($inner) {
                $inner->whereNull('published_at')
                    ->orWhere('published_at', '<=', Carbon::now());
            })
            ->orderByRaw('CASE WHEN status = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $rows = $query->limit(30)->get()->map(function (MobileNotification $row) {
            return [
                'id' => $row->id,
                'title' => $row->title,
                'message_html' => $row->message_html,
                'status' => (int) $row->status,
                'published_at' => optional($row->published_at ?? $row->created_at)->toISOString(),
                'read_at' => optional($row->read_at)->toISOString(),
            ];
        })->values();

        return response([
            'message' => 'ok',
            'data' => $rows->all(),
            'meta' => [
                'unread_count' => $rows->where('status', 0)->count(),
            ],
        ]);
    }

    public function read(Request $request, int $id)
    {
        [$company, $employee] = $this->resolveContext($request);
        if (! $company || ! $employee) {
            return $this->missingContextResponse($company, $employee);
        }

        $notification = MobileNotification::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('username', $request->user()->name)
            ->whereKey($id)
            ->first();

        if (! $notification) {
            return response([
                'message' => 'Notification not found.',
            ], 404);
        }

        if ((int) $notification->status === 0) {
            $notification->status = 1;
            $notification->read_at = Carbon::now();
            $notification->save();
        }

        return response([
            'message' => 'Successfully updated',
            'data' => [
                'id' => $notification->id,
                'status' => (int) $notification->status,
                'read_at' => optional($notification->read_at)->toISOString(),
            ],
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
            ->select(['id', 'company_id', 'user_id'])
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
}
