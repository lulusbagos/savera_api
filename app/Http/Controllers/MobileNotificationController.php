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
            ->where(function ($inner) {
                $windowStart = Carbon::now()->subDays(30);
                $inner
                    ->where(function ($q) use ($windowStart) {
                        $q->whereNotNull('published_at')
                            ->where('published_at', '>=', $windowStart);
                    })
                    ->orWhere(function ($q) use ($windowStart) {
                        $q->whereNull('published_at')
                            ->where('created_at', '>=', $windowStart);
                    });
            })
            ->orderByRaw('CASE WHEN status = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $rows = $query->limit(30)->get()->map(function (MobileNotification $row) {
            $messageHtml = (string) ($row->message_html ?? '');
            $payload = is_array($row->payload_json) ? $row->payload_json : [];

            if (($row->source_type ?? '') === 'attendance_inout' && ! empty($payload)) {
                $nik = trim((string) ($payload['nik'] ?? ''));
                $jamIn = trim((string) ($payload['jam_in'] ?? ''));
                $jamOut = trim((string) ($payload['jam_out'] ?? ''));
                $ipIn = trim((string) ($payload['ip_in'] ?? ''));
                $ipOut = trim((string) ($payload['ip_out'] ?? ''));
                $printerIn = trim((string) ($payload['nama_printer_in'] ?? ''));
                $printerOut = trim((string) ($payload['nama_printer_out'] ?? ''));

                $tanggalRaw = trim((string) ($payload['tanggal'] ?? ''));
                $tanggal = '-';
                if ($tanggalRaw !== '') {
                    try {
                        $tanggal = Carbon::parse($tanggalRaw)->format('d/m/Y');
                    } catch (\Throwable) {
                        $tanggal = $tanggalRaw;
                    }
                }

                $messageHtml = '<font color="#16A34A"><b>ABSENSI HARIAN</b></font><br>'
                    . '<b>Ringkasan In/Out Karyawan</b><br><br>'
                    . '<b>NIK:</b> ' . e($nik !== '' ? $nik : '-') . '<br>'
                    . '<b>Tanggal:</b> ' . e($tanggal) . '<br>'
                    . '<b>Jam In:</b> ' . e($jamIn !== '' ? $jamIn : '-') . '<br>'
                    . '<b>Jam Out:</b> ' . e($jamOut !== '' ? $jamOut : '-') . '<br>'
                    . '<b>Lokasi (IP):</b> ' . e(($ipIn !== '' ? $ipIn : '-') . ' / ' . ($ipOut !== '' ? $ipOut : '-')) . '<br>'
                    . '<b>Lokasi Mesin:</b> ' . e(($printerIn !== '' ? $printerIn : '-') . ' / ' . ($printerOut !== '' ? $printerOut : '-'));

                $messagePlain = implode("\n", [
                    'Ringkasan In/Out Karyawan',
                    'NIK      : ' . ($nik !== '' ? $nik : '-'),
                    'Tanggal  : ' . $tanggal,
                    'Jam In   : ' . ($jamIn !== '' ? $jamIn : '-'),
                    'Jam Out  : ' . ($jamOut !== '' ? $jamOut : '-'),
                    'Lokasi   : ' . ($ipIn !== '' ? $ipIn : '-') . ' / ' . ($ipOut !== '' ? $ipOut : '-'),
                    'Mesin    : ' . ($printerIn !== '' ? $printerIn : '-') . ' / ' . ($printerOut !== '' ? $printerOut : '-'),
                ]);
            } else {
                $messagePlain = trim(strip_tags($messageHtml));
            }
            return [
                'id' => $row->id,
                'title' => $row->title,
                'message_html' => $messageHtml,
                'message' => $messagePlain,
                'content_format' => 'html',
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
        $userCompanyId = Employee::query()
            ->where('user_id', $request->user()->id)
            ->value('company_id');

        $company = null;
        $companyCode = strtoupper(trim((string) $request->header('company', '')));
        if ($companyCode !== '') {
            $companies = Company::query()
                ->select(['id', 'code'])
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
                ->select(['id', 'code'])
                ->whereKey($userCompanyId)
                ->first();
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
