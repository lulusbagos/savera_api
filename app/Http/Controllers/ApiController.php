<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use App\Jobs\StoreUserMetricsJob;
use App\Models\Banner;
use App\Models\Company;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Mess;
use App\Models\Shift;
use App\Models\Summary;
use App\Services\MobileMetricPayloadNormalizer;
use App\Models\Ticket;
use App\Support\MobileIngestRuntime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use JsonException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApiController extends Controller
{
    public function profile(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        $cacheKey = $this->profileCacheKey($company->id, $request->user()->id);
        $payload = Cache::store($this->cacheStore())->remember($cacheKey, 60, function () use ($company, $request) {
            return $this->buildProfilePayload($company, $request);
        });

        if ($payload === null) {
            $fallbackCompany = $this->findCompanyByCode(null, $request->user()->id);
            if ($fallbackCompany && (int) $fallbackCompany->id !== (int) $company->id) {
                $fallbackKey = $this->profileCacheKey($fallbackCompany->id, $request->user()->id);
                $payload = Cache::store($this->cacheStore())->remember($fallbackKey, 60, function () use ($fallbackCompany, $request) {
                    return $this->buildProfilePayload($fallbackCompany, $request);
                });
            }
        }

        if ($payload === null) {
            return response([
                'message' => 'Employee not found.',
            ], 404);
        }

        return response($payload);
    }

    private function buildProfilePayload(Company $company, Request $request): ?array
    {
        $user = $request->user()->toArray();
        $isSleepUploader = $this->isSleepUploaderUser($request->user());
        $employee = Employee::query()
            ->select(['id', 'code', 'fullname', 'department_id', 'mess_id', 'device_id', 'company_id', 'user_id', 'photo', 'job', 'status'])
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (! $employee) {
            return null;
        }

        $employee['department_name'] = (is_null($employee->department_id)) ? null : Department::query()->whereKey($employee->department_id)->value('name');
        $employee['mess_name'] = (is_null($employee->mess_id)) ? null : Mess::query()->whereKey($employee->mess_id)->value('name');
        $employee['is_sleep_uploader'] = $isSleepUploader ? 1 : 0;
        $user['is_sleep_uploader'] = $isSleepUploader ? 1 : 0;
        $user['employee'] = $employee->toArray();
        $user['shift'] = Shift::query()->whereKey(1)->first()?->toArray();
        $user['device'] = (is_null($employee->device_id)) ? null : Device::query()->whereKey($employee->device_id)->first()?->toArray();
        $user['network_sync'] = $this->resolveNetworkSyncStatus($request, $employee);
        if (in_array($request->user()->name, ['SAVERA', 'ROMI', 'ANDRE', 'OBIT', 'ANDI', 'HULAEPI', 'FAISAL', 'ROBI', 'IVAN', 'EREN', 'TABLET 1', 'TABLET 2', 'TABLET 3', 'TABLET 4'])) {
            $user['is_admin'] = 1;
        }

        return $user;
    }

    public function avatar(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        if ($request->isMethod('post')) {
            try {
                $request->validate([
                    'company_id' => 'required',
                    'employee_id' => 'required',
                    'photo' => 'required|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
                ]);

                $photo = null;
                if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                    $photo = $request->file('photo')->storeAs(
                        'avatar',
                        $request->file('photo')->getClientOriginalName()
                    );
                }

                $employee = Employee::where('company_id', $company->id)
                    ->whereId($request->employee_id)
                    ->first();
                if (! $employee) {
                    return response([
                        'message' => 'Employee not found.',
                    ], 404);
                }

                $employee->photo = $photo;
                $employee->save();
                Cache::store($this->cacheStore())->forget($this->profileCacheKey($company->id, $request->user()->id));

                return response([
                    'message' => 'Successfully created',
                    'data' => $employee,
                ]);
            } catch (ValidationException $e) {
                return response([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }
        }

        $photo = Employee::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->value('photo');
        if (! $photo) {
            return response([
                'message' => 'Photo not found.',
            ], 404);
        }

        return redirect()->route('image', [$photo]);
    }

    public function device(Request $request, $mac = null)
    {
        if (is_null($mac)) {
            return response([
                'message' => 'Mac address not found.',
            ], 404);
        }

        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        $normalizedMac = $this->normalizeMacAddress((string) $mac);
        $cacheKey = $this->deviceCacheKey($company->id, $normalizedMac);
        $device = Cache::store($this->cacheStore())->remember($cacheKey, 60, function () use ($company, $normalizedMac) {
            $select = ['id', 'company_id', 'mac_address', 'device_name', 'brand', 'auth_key', 'is_active'];
            $normalizedSql = "LOWER(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), ' ', ''))";

            $device = Device::query()
                ->select($select)
                ->where('company_id', $company->id)
                ->whereRaw("{$normalizedSql} = ?", [$normalizedMac])
                ->first();

            if ($device) {
                return $device;
            }

            // Fallback: some legacy device rows were stored under the wrong company,
            // but the employee binding in the current company points to the correct device id.
            return Device::query()
                ->select($select)
                ->whereRaw("{$normalizedSql} = ?", [$normalizedMac])
                ->whereExists(function ($query) use ($company) {
                    $query->select(DB::raw(1))
                        ->from('employees')
                        ->whereColumn('employees.device_id', 'devices.id')
                        ->where('employees.company_id', $company->id)
                        ->whereNull('employees.deleted_at');
                })
                ->first();
        });
        if (! $device) {
            return response([
                'message' => 'Your device\'s MAC address is unavailable.',
            ], 404);
        }

        $employee = Cache::store($this->cacheStore())->remember($cacheKey.':employee', 60, function () use ($company, $device) {
            return Employee::query()
                ->select(['id', 'code', 'fullname', 'department_id', 'mess_id', 'device_id', 'company_id', 'user_id', 'photo', 'job', 'status'])
                ->where('company_id', $company->id)
                ->where('device_id', $device->id)
                ->first();
        });
        if (! $employee) {
            return response([
                'message' => 'Your device is not yet bound to an account.',
            ], 404);
        }

        $device['employee'] = $employee;
        $device['employee']['department_name'] = (is_null($employee->department_id)) ? null : Department::query()->whereKey($employee->department_id)->value('name');
        $device['employee']['mess_name'] = (is_null($employee->mess_id)) ? null : Mess::query()->whereKey($employee->mess_id)->value('name');

        return response($device);
    }

    public function summary(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        return $this->ingestMetrics(
            request: $request,
            company: $company,
            source: 'summary',
            validationRules: [
                'active' => 'required|numeric',
                'steps' => 'required|numeric',
                'heart_rate' => 'required|numeric',
                'heart_rate_valid' => 'nullable|boolean',
                'distance' => 'required|numeric',
                'calories' => 'required|numeric',
                'spo2' => 'required|numeric',
                'stress' => 'required|numeric',
                'sleep' => 'required|numeric',
                'device_time' => 'required|string',
                'mac_address' => 'required|string',
                'employee_id' => 'required|numeric',
                'is_fit1' => 'nullable',
                'is_fit2' => 'nullable',
                'is_fit3' => 'nullable',
                'fit_to_work_q1' => 'nullable',
                'fit_to_work_q2' => 'nullable',
                'fit_to_work_q3' => 'nullable',
                'fit_to_work_submitted_at' => 'nullable|date',
            ],
            metricMap: [
                'data_activity' => 'user_activity',
                'data_sleep' => 'user_sleep',
                'data_stress' => 'user_stress',
                'data_spo2' => 'user_spo2',
            ],
            payloadBuilder: fn (Request $request, Company $company, Device $device, Employee $employee): array => $this->buildSummaryIngestPayload($request, $company, $device, $employee)
        );
    }

    public function detail(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            // Logging error harian
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        return $this->ingestMetrics(
            request: $request,
            company: $company,
            source: 'detail',
            validationRules: [
                'device_time' => 'required|string',
                'mac_address' => 'required|string',
                'employee_id' => 'required|numeric',
            ],
            metricMap: [
                'data_activity' => 'user_activity',
                'data_sleep' => 'user_sleep',
                'data_stress' => 'user_stress',
                'data_spo2' => 'user_spo2',
                'data_heart_rate_max' => 'user_heart_rate_max',
                'data_heart_rate_resting' => 'user_heart_rate_resting',
                'data_heart_rate_manual' => 'user_heart_rate_manual',
            ],
            payloadBuilder: fn (Request $request, Company $company, Device $device, Employee $employee): array => $this->buildDetailIngestPayload($request, $company, $device, $employee)
        );
    }

    public function sleepSnapshot(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        return $this->ingestMetrics(
            request: $request,
            company: $company,
            source: 'sleep-snapshot',
            validationRules: [
                'device_time' => 'required|string',
                'mac_address' => 'required|string',
                'employee_id' => 'required|numeric',
                'sleep' => 'nullable|numeric',
                'sleep_start' => 'nullable|numeric',
                'sleep_end' => 'nullable|numeric',
                'sleep_type' => 'nullable|string',
                'light_sleep' => 'nullable|numeric',
                'deep_sleep' => 'nullable|numeric',
                'rem_sleep' => 'nullable|numeric',
                'awake' => 'nullable|numeric',
            ],
            metricMap: [
                'data_activity' => 'user_activity',
                'data_sleep' => 'user_sleep',
                'data_stress' => 'user_stress',
                'data_spo2' => 'user_spo2',
                'data_heart_rate_max' => 'user_heart_rate_max',
                'data_heart_rate_resting' => 'user_heart_rate_resting',
                'data_heart_rate_manual' => 'user_heart_rate_manual',
            ],
            payloadBuilder: fn (Request $request, Company $company, Device $device, Employee $employee): array => $this->buildSleepSnapshotIngestPayload($request, $company, $device, $employee)
        );
    }

    public function ticket(Request $request, $id = null)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        if (is_null($id)) {
            return response([
                'message' => 'Id not found.',
            ], 404);
        }

        $employee = Employee::where('company_id', $company->id)
            ->whereId($id)
            ->first();
        if (! $employee) {
            return response([
                'message' => 'Employee not found.',
            ], 404);
        }

        $summary = Summary::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('send_date', Carbon::now()->toDateString())
            ->latest('id')
            ->first();
        if (! $summary) {
            return response([
                'message' => 'Summary not found.',
            ], 404);
        }

        $shift = '-';
        $sector = '-';
        $area = '-';
        $type = '-';
        $unit = '-';
        $model = '-';
        $fleet = '-';
        $transport = '-';
        $day = '-';

        try {
            $lineup = null;
            if (Schema::hasTable('lineup_operator')) {
                $lineup = DB::table('lineup_operator')
                    ->where('tanggal', date('Y-m-d'))
                    ->where('company_id', $company->id)
                    ->where('nik', $employee->code)
                    ->orderBy('no')
                    ->first();
            }

            if ($lineup) {
                if ($lineup->shift == null || $lineup->shift == '') {
                    $lineup->shift = '-';
                }
                if ($lineup->shift_detil == null || $lineup->shift_detil == '') {
                    $lineup->shift_detil = '-';
                }
                if ($lineup->unit == null || $lineup->unit == '') {
                    $lineup->unit = '-';
                }
                if ($lineup->tipe_unit == null || $lineup->tipe_unit == '') {
                    $lineup->tipe_unit = '-';
                }
                if ($lineup->model_unit == null || $lineup->model_unit == '') {
                    $lineup->model_unit = '-';
                }
                if ($lineup->fleet == null || $lineup->fleet == '') {
                    $lineup->fleet = '-';
                }
                if ($lineup->no_bus == null || $lineup->no_bus == '') {
                    $lineup->no_bus = '-';
                }
                if ($lineup->pit == null || $lineup->pit == '') {
                    $lineup->pit = '-';
                }
                if ($lineup->area == null || $lineup->area == '') {
                    $lineup->area = '-';
                }

                $shift = trim(str_replace('SHIFT', '', $lineup->shift_detil));
                $sector = trim($lineup->pit);
                $area = trim($lineup->area);
                $type = trim($lineup->tipe_unit);
                $unit = trim($lineup->unit);
                $model = trim($lineup->model_unit);
                $fleet = trim($lineup->fleet);
                $transport = trim($lineup->no_bus);
                $day = trim($lineup->shift);
            }
        } catch (Throwable $e) {
            LogHelper::logError('Proses Ticket Lineup:', $e->getMessage());
        }

        $summary['shift'] = $shift;
        $summary['hauler'] = $unit;
        $summary['loader'] = $fleet;
        $summary['transport'] = $transport;
        $summary['date'] = Carbon::parse($summary['send_date'])->format('d F Y');
        $summary['time'] = $summary['send_time'];
        $summary['sleep_minutes'] = max(0, (int) ($summary['sleep'] ?? 0));
        $summary['sleep_duration_minutes'] = $summary['sleep_minutes'];
        $summary['sleep_duration'] = $this->minutesToTicketSleepText($summary['sleep_minutes']);
        $summary['sleep_text'] = $this->minutesToSleepText($summary['sleep_minutes']);
        $summary['message'] = 'Minum Obat: '.($summary['is_fit1'] == 0 ? 'tidak' : 'ya').'
        Ada Masalah Konsentrasi: '.($summary['is_fit2'] == 0 ? 'tidak' : 'ya').'
        Siap Bekerja: '.($summary['is_fit3'] == 0 ? 'tidak' : 'ya').'
        ';

        try {
            Ticket::query()->updateOrCreate([
                'date' => $summary['send_date'],
                'employee_id' => $employee->id,
            ], [
                'date' => $summary['send_date'],
                'shift' => $shift,
                'code' => $employee->code,
                'fullname' => $employee->fullname,
                'job' => $employee->job,
                'sector' => $sector,
                'area' => $area,
                'type' => $type,
                'unit' => $unit,
                'model' => $model,
                'fleet' => $fleet,
                'transport' => $transport,
                'day' => $day,
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'department_id' => $employee->department_id,
            ]);
        } catch (Throwable $e) {
            // Logging error harian
            LogHelper::logError('Proses Ticket:', $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan absensi',
            ], 500);
        }

        return response($summary);
    }

    public function leave(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        try {
            $request->validate([
                'employee_id' => 'required',
                'type' => 'required|string',
                'phone' => 'required|string',
                'note' => 'required|string',
            ]);

            $employee = Employee::where('company_id', $company->id)
                ->whereId($request->employee_id)
                ->first();
            if (! $employee) {
                return response([
                    'message' => 'Employee not found.',
                ], 404);
            }

            $req = $request->all();
            $req['shift'] = '-';
            $req['date'] = Carbon::now()->toDateString();
            $req['code'] = $employee->code;
            $req['fullname'] = $employee->fullname;
            $req['job'] = $employee->job;
            $req['employee_id'] = $employee->id;
            $req['company_id'] = $employee->company_id;
            $req['department_id'] = $employee->department_id;

            Leave::query()->updateOrCreate([
                'date' => $req['date'],
                'employee_id' => $req['employee_id'],
                'type' => $req['type'],
                'phone' => $req['phone'],
                'note' => $req['note'],
            ], $req);

            return response([
                'message' => 'Successfully created',
                'data' => $req,
            ]);
        } catch (ValidationException $e) {
            return response([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        }
    }

    public function etiket(Request $request)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        $tanggal = trim((string) $request->input('tanggal', ''));
        $nik = trim((string) $request->input('nik', ''));

        if ($nik === '') {
            $nik = (string) Employee::query()
                ->where('company_id', $company->id)
                ->where('user_id', $request->user()->id)
                ->value('code');
        }

        $connectionName = (string) config('database.default', 'pgsql');
        $configuredConnection = (string) env('ETIKET_DB_CONNECTION', $connectionName);
        if (array_key_exists($configuredConnection, (array) config('database.connections', []))) {
            $connectionName = $configuredConnection;
        }

        $rows = collect();
        $availableColumns = [];

        try {
            $conn = DB::connection($connectionName);
            $schema = $conn->getSchemaBuilder();

            if ($schema->hasTable('lineup_operator')) {
                $requestedColumns = [
                    'no',
                    'tanggal',
                    'unit',
                    'nik',
                    'nama',
                    'shift',
                    'keterangan',
                    'shift_detil',
                    'pit',
                    'area',
                    'region',
                    'tipe_unit',
                    'model_unit',
                    'fleet',
                    'no_bus',
                    'company_id',
                    'updated_at',
                ];

                $availableColumns = $schema->getColumnListing('lineup_operator');
                $selectColumns = array_values(array_intersect($requestedColumns, $availableColumns));

                $query = $conn->table('lineup_operator')
                    ->select($selectColumns)
                    ->when(in_array('company_id', $availableColumns, true), function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->when($tanggal !== '' && in_array('tanggal', $availableColumns, true), function ($q) use ($tanggal) {
                        $q->whereDate('tanggal', $tanggal);
                    })
                    ->when($nik !== '' && in_array('nik', $availableColumns, true), function ($q) use ($nik) {
                        $q->where('nik', $nik);
                    })
                    ->when(in_array('tanggal', $availableColumns, true), function ($q) {
                        $q->orderByDesc('tanggal');
                    })
                    ->when(in_array('no', $availableColumns, true), function ($q) {
                        $q->orderBy('no');
                    });

                $rows = $query->limit(300)->get();
            }
        } catch (Throwable) {
            $rows = collect();
        }

        return response([
            'message' => 'ok',
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'company_id' => $company->id,
                'nik' => $nik !== '' ? $nik : null,
                'tanggal' => $tanggal !== '' ? $tanggal : null,
                'source_connection' => $connectionName,
                'table_available' => !empty($availableColumns),
            ],
        ]);
    }

    public function banner(Request $request)
    {
        try {
            $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
            if (! $company) {
                return response([
                    'message' => 'Company not found.',
                ], 404);
            }

            /*$images = array_map(function ($val) {
                return 'http://savera_admin.idcapps.net/image/' . $val;*/
            $banners = Cache::store($this->cacheStore())->remember("banner:{$company->id}", 300, function () use ($company) {
                return Banner::where('company_id', $company->id)
                    ->orderBy('seq')
                    ->orderBy('id')
                    ->pluck('image')
                    ->toArray();
            });

            $images = array_map(function ($val) {
                return 'https://adminsavera.indexim.id/image/'.$val;
            }, $banners);

            return response($images)->header('Cache-Control', 'public, max-age=300');
        } catch (Throwable $e) {
            // Logging error harian
            LogHelper::logError('Proses Banner:', $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan absensi',
            ], 500);
        }
    }

    public function ranking(Request $request, $id = null)
    {
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        if (! $company) {
            return response([
                'message' => 'Company not found.',
            ], 404);
        }

        $cacheKey = 'ranking:' . $company->id . ':' . Carbon::now()->format('Y-m') . ':' . (string) $id;

        $cached = Cache::store($this->cacheStore())->get($cacheKey);
        if (is_array($cached)) {
            return response($cached);
        }

        // $query = "SELECT
        //     employee_id,
        //     EXTRACT(YEAR FROM send_date) AS year,
        //     EXTRACT(MONTH FROM send_date) AS month,
        //     SUM(sleep) AS total_sleep
        // FROM
        //     summaries
        // WHERE
        //     EXTRACT(YEAR FROM send_date) = EXTRACT(YEAR FROM CURRENT_DATE)
        //     AND EXTRACT(MONTH FROM send_date) = EXTRACT(MONTH FROM CURRENT_DATE)
        // GROUP BY
        //     employee_id,
        //     EXTRACT(YEAR FROM send_date),
        //     EXTRACT(MONTH FROM send_date)
        // ORDER BY
        //     total_sleep DESC;";

        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $startOfNextMonth = Carbon::now()->startOfMonth()->addMonth()->toDateString();

        $data = DB::table('summaries')
            ->join('employees', 'summaries.employee_id', '=', 'employees.id')
            ->select(
                'summaries.employee_id',
                'employees.code',
                'employees.fullname',
                DB::raw('EXTRACT(YEAR FROM send_date) AS year'),
                DB::raw('EXTRACT(MONTH FROM send_date) AS month'),
                DB::raw('SUM(sleep) AS total_sleep'),
                DB::raw('AVG(sleep) AS average_sleep'),
                DB::raw('COUNT(*) AS count_data')
            )
            ->where('summaries.company_id', $company->id)
            ->where('employees.company_id', $company->id)
            ->whereNull('summaries.deleted_at')
            ->whereNull('employees.deleted_at')
            ->where('send_date', '>=', $startOfMonth)
            ->where('send_date', '<', $startOfNextMonth)
            ->groupBy('summaries.employee_id', 'employees.code', 'employees.fullname', DB::raw('EXTRACT(YEAR FROM send_date)'), DB::raw('EXTRACT(MONTH FROM send_date)'))
            ->orderByDesc(DB::raw('AVG(sleep)'))
            ->get();

        $total = 0;
        $rank = 0;
        $total_average_sleep = 0;
        foreach ($data as $item) {
            $average_sleep = $item->average_sleep;
            $hours = floor($average_sleep / 60);
            $minutes = $average_sleep % 60;
            $item->average_sleep_hour = sprintf('%02d:%02d', $hours, $minutes);
            $total++;
            if ($id == $item->employee_id) {
                $rank = $total;
            }
            $total_average_sleep = $total_average_sleep + $average_sleep;
        }

        // Hindari division by zero saat tidak ada data
        if ($total === 0) {
            $payload = [
                'message' => 'ok',
                'total' => 0,
                'rank' => 0,
                'average' => '00:00',
                'date' => Carbon::now()->format('d M Y'),
                'data' => [],
            ];

            Cache::store($this->cacheStore())->put($cacheKey, $payload, 300);

            return response($payload);
        }

        $data = $data->take(10);
        $average_total = $total_average_sleep / $total;
        $hours = floor($average_total / 60);
        $minutes = $average_total % 60;

        $payload = [
            'message' => 'ok',
            'total' => $total,
            'rank' => $rank,
            'average' => sprintf('%02d:%02d', $hours, $minutes),
            'date' => Carbon::now()->format('d M Y'),
            'data' => $data,
        ];

        Cache::store($this->cacheStore())->put($cacheKey, $payload, 300);

        return response($payload);
    }

    private function profileCacheKey(int $companyId, int $userId): string
    {
        return "profile:{$companyId}:{$userId}";
    }

    private function deviceCacheKey(int $companyId, string $macAddress): string
    {
        return 'device:' . $companyId . ':' . $this->normalizeMacAddress($macAddress);
    }

    private function normalizeMacAddress(string $macAddress): string
    {
        return (string) Str::of(trim($macAddress))->lower()->replace(':', '')->replace('-', '')->replace(' ', '');
    }

    private function findCompanyByCode(?string $code, ?int $userId = null): ?Company
    {
        $userCompanyId = null;
        if ($userId) {
            $userCompanyId = Employee::query()
                ->where('user_id', $userId)
                ->value('company_id');
        }

        $normalizedCode = strtoupper(trim((string) $code));
        if ($normalizedCode !== '') {
            $companies = Company::query()
                ->select(['id', 'code', 'name'])
                ->whereRaw('UPPER(code) = ?', [$normalizedCode])
                ->orderBy('id')
                ->get();

            if ($companies->isNotEmpty()) {
                if ($userCompanyId) {
                    $preferred = $companies->firstWhere('id', (int) $userCompanyId);
                    if ($preferred) {
                        return $preferred;
                    }
                }

                return $companies->first();
            }
        }

        if ($userCompanyId) {
            return Company::query()
                ->select(['id', 'code', 'name'])
                ->whereKey($userCompanyId)
                ->first();
        }

        return Company::query()
            ->select(['id', 'code', 'name'])
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    private function findDeviceByMac(int $companyId, string $macAddress): ?Device
    {
        $normalizedMac = $this->normalizeMacAddress($macAddress);
        $normalizedSql = "LOWER(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), ' ', ''))";

        $device = Device::query()
            ->select(['id', 'company_id', 'mac_address'])
            ->where('company_id', $companyId)
            ->whereRaw("{$normalizedSql} = ?", [$normalizedMac])
            ->first();

        if ($device) {
            return $device;
        }

        return Device::query()
            ->select(['id', 'company_id', 'mac_address'])
            ->whereRaw("{$normalizedSql} = ?", [$normalizedMac])
            ->whereExists(function ($query) use ($companyId) {
                $query->select(DB::raw(1))
                    ->from('employees')
                    ->whereColumn('employees.device_id', 'devices.id')
                    ->where('employees.company_id', $companyId)
                    ->whereNull('employees.deleted_at');
            })
            ->first();
    }

    private function lockEmployeeByCompanyAndId(int $companyId, int $employeeId): ?Employee
    {
        return Employee::query()
            ->select(['id', 'user_id', 'company_id', 'device_id', 'department_id'])
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();
    }

    private function lockEmployeeByCompanyAndDevice(int $companyId, int $deviceId): ?Employee
    {
        return Employee::query()
            ->select(['id', 'user_id', 'company_id', 'device_id', 'department_id'])
            ->where('company_id', $companyId)
            ->where('device_id', $deviceId)
            ->whereNull('deleted_at')
            ->orderByDesc('status')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function isSleepUploaderUser($user): bool
    {
        if (! $user || ! Schema::hasColumn('users', 'is_sleep_uploader')) {
            return false;
        }

        return filter_var($user->is_sleep_uploader ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function logIngestDeviceFailure(Request $request, Company $company, string $source, string $reason, ?Device $device = null, ?Employee $employee = null): void
    {
        LogHelper::logError('Mobile ingest device check failed:', json_encode([
            'reason' => $reason,
            'source' => $source,
            'company_header' => (string) $request->header('company', ''),
            'company_id' => $company->id,
            'user_id' => $request->user()?->id,
            'employee_id_payload' => $request->input('employee_id'),
            'employee_id_found' => $employee?->id,
            'employee_device_id' => $employee?->device_id,
            'mac_address_payload' => $request->input('mac_address'),
            'mac_address_normalized' => $this->normalizeMacAddress((string) $request->input('mac_address', '')),
            'device_id_found' => $device?->id,
            'device_company_id' => $device?->company_id,
            'device_mac_address' => $device?->mac_address,
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, string>  $metricMap
     * @return array<int, array{path: string, contents: string}>
     */
    private function buildMetricWriteQueue(Request $request, array $metricMap, string $file): array
    {
        $filesToWrite = [];
        /** @var MobileMetricPayloadNormalizer $normalizer */
        $normalizer = app(MobileMetricPayloadNormalizer::class);

        foreach ($metricMap as $pathPrefix => $requestKey) {
            $payload = $request->input($requestKey, null);
            if ($payload === null) {
                $payload = $request->input($pathPrefix, '[]');
            }

            $filesToWrite[] = [
                'path' => $pathPrefix.$file,
                'contents' => $normalizer->normalizeForBucket($pathPrefix, $payload),
            ];
        }

        return $filesToWrite;
    }

    private function normalizeMetricPayload(mixed $payload): string
    {
        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                return '[]';
            }

            try {
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($decoded)) {
                    return '[]';
                }

                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[]';
            }
        }

        if (is_array($payload)) {
            try {
                return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[]';
            }
        }

        return '[]';
    }

    private function cacheStore(): string
    {
        return MobileIngestRuntime::cacheStore('file');
    }

    private function resolveNetworkSyncStatus(Request $request, Employee $employee): array
    {
        $cache = Cache::store($this->cacheStore());
        $userId = (int) ($request->user()?->id ?? 0);
        $report = [];

        if ($userId > 0) {
            $cached = $cache->get('mobile_network_report:user:' . $userId, []);
            if (is_array($cached)) {
                $report = $cached;
            }
        }

        $localBaseUrl = rtrim((string) config('mobile_network.local_base_url', ''), '/');
        $localHost = $this->extractHost($localBaseUrl);
        $ipScope = strtolower((string) ($report['ip_scope'] ?? 'unknown'));
        $reportedAt = (string) ($report['reported_at'] ?? '');
        $reportedAtValid = false;

        if ($reportedAt !== '') {
            try {
                $reportedAtValid = Carbon::parse($reportedAt)->gt(now()->subHours(8));
            } catch (Throwable) {
                $reportedAtValid = false;
            }
        }

        $localSynced = $localHost !== '' && $ipScope === 'local' && $reportedAtValid;

        return [
            'is_local_synced' => $localSynced,
            'status_color' => $localSynced ? 'green' : 'red',
            'status_label' => $localSynced ? 'Local IP sinkron dengan backend' : 'Local IP belum sinkron',
            'local_base_url' => $localBaseUrl !== '' ? $localBaseUrl : null,
            'active_ip_scope' => $ipScope,
            'active_network_type' => (string) ($report['network_type'] ?? 'unknown'),
            'reported_at' => $reportedAt !== '' ? $reportedAt : null,
            'employee_id' => $employee->id,
        ];
    }

    private function extractHost(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        return trim($host);
    }

    private function lockStore(): string
    {
        return MobileIngestRuntime::lockStore($this->cacheStore());
    }

    /**
     * @param  array<int, array{path: string, contents: string}>  $filesToWrite
     */
    private function dispatchMetricWriteJob(array $filesToWrite, string $source): void
    {
        $mode = MobileIngestRuntime::dispatchMode();
        $job = new StoreUserMetricsJob($filesToWrite, $source);

        try {
            if (app()->runningUnitTests()) {
                dispatch_sync($job);
                return;
            }

            if ($mode === 'sync') {
                dispatch_sync($job);
                return;
            }

            if ($mode === 'queue' || ($mode === 'auto' && MobileIngestRuntime::usesAsyncQueue())) {
                dispatch($job);
                return;
            }

            dispatch($job)->afterResponse();
        } catch (Throwable $e) {
            LogHelper::logError('Dispatch metric write job fallback:', json_encode([
                'source' => $source,
                'mode' => $mode,
                'queue_connection' => MobileIngestRuntime::queueConnection('sync'),
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES));

            // Fail-open fallback agar upload tidak 500 saat queue sedang terganggu.
            dispatch_sync($job);
        }
    }

    /**
     * @param  array<string, string>  $validationRules
     * @param  array<string, string>  $metricMap
     * @param  callable(Request, Company, Device, Employee): array{data: array, file: string}  $payloadBuilder
     */
    private function ingestMetrics(Request $request, Company $company, string $source, array $validationRules, array $metricMap, callable $payloadBuilder)
    {
        try {
            $request->validate($validationRules);
        } catch (ValidationException $e) {
            LogHelper::logError('Proses '.ucfirst($source).':', $e->getMessage());

            return response([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        }

        try {
            $employeeId = (int) $request->input('employee_id');
            $deviceKey = $this->normalizeMacAddress((string) $request->input('mac_address'));
            $lockKey = "ingest:{$company->id}:{$employeeId}:{$deviceKey}:{$source}";
            $lock = Cache::store($this->lockStore())->lock($lockKey, 30);

            if (! $lock->get()) {
                return response([
                    'message' => 'Data sedang diproses. Silakan coba ulang.',
                ], 429);
            }

            try {
                $result = DB::transaction(function () use ($request, $company, $payloadBuilder, $metricMap, $source) {
                    $device = $this->findDeviceByMac($company->id, (string) $request->input('mac_address'));
                    if (! $device) {
                        $this->logIngestDeviceFailure($request, $company, $source, 'device_mac_not_found');
                        return response([
                            'message' => 'Your device\'s MAC address is unavailable.',
                        ], 404);
                    }

                    $isSleepUploader = $this->isSleepUploaderUser($request->user());
                    if ($isSleepUploader) {
                        $employee = $this->lockEmployeeByCompanyAndDevice($company->id, (int) $device->id);
                        if (! $employee) {
                            $this->logIngestDeviceFailure($request, $company, $source, 'sleep_uploader_device_not_bound', $device);
                            return response([
                                'message' => 'Device belum terhubung ke employee. Hubungi admin untuk mapping MAC Address.',
                            ], 404);
                        }
                    } else {
                        $employee = $this->lockEmployeeByCompanyAndId($company->id, (int) $request->input('employee_id'));
                        if (! $employee) {
                            $this->logIngestDeviceFailure($request, $company, $source, 'employee_not_found', $device);
                            return response([
                                'message' => 'Employee not found.',
                            ], 404);
                        }

                        if ((int) ($employee->user_id ?? 0) !== (int) ($request->user()?->id ?? 0)) {
                            $this->logIngestDeviceFailure($request, $company, $source, 'employee_user_mismatch', $device, $employee);
                            return response([
                                'message' => 'Akun ini tidak boleh upload data untuk employee lain.',
                            ], 403);
                        }
                    }

                    if ((int) ($employee->device_id ?? 0) !== (int) $device->id) {
                        $this->logIngestDeviceFailure($request, $company, $source, 'device_employee_mismatch', $device, $employee);

                        return response([
                            'message' => 'Your device is not bound to this employee.',
                        ], 404);
                    }

                    if ($employee->user_id === null) {
                        return response([
                            'message' => 'User not found.',
                        ], 404);
                    }

                    $payload = $payloadBuilder($request, $company, $device, $employee);
                    if (! isset($payload['data'], $payload['file'])) {
                        return response([
                            'message' => 'Invalid ingest payload.',
                        ], 500);
                    }

                    return [
                        'data' => $payload['data'],
                        'file' => $payload['file'],
                        'source' => $source,
                    ];
                });

                if ($result instanceof \Illuminate\Http\JsonResponse || $result instanceof \Illuminate\Http\Response) {
                    return $result;
                }

                $filesToWrite = $this->buildMetricWriteQueue($request, $metricMap, $result['file']);
                $this->dispatchMetricWriteJob($filesToWrite, $result['source']);

                return response([
                    'message' => 'Successfully created',
                    'data' => $result['data'],
                ]);
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            LogHelper::logError('Proses '.ucfirst($source).':', $e->getMessage());

            return response([
                'message' => 'Terjadi kesalahan saat memproses data.',
            ], 500);
        }
    }

    /**
     * @return array{data: array<string, mixed>, file: string}
     */
    private function buildSummaryIngestPayload(Request $request, Company $company, Device $device, Employee $employee): array
    {
        $req = $request->only((new Summary())->getFillable());
        $req['user_id'] = $employee->user_id;
        $req['employee_id'] = $employee->id;
        $req['company_id'] = $company->id;
        $req['department_id'] = $employee->department_id;
        $req['device_id'] = $device->id;
        $req['send_date'] = Carbon::now()->toDateString();
        $req['send_time'] = Carbon::now()->toTimeString();
        $req['sleep_type'] = $request->input('sleep_type', 'night') ?? 'night';
        $this->applyFitToWorkPayload($request, $req);
        $reportedSleepMinutes = max(0, (int) round((float) ($req['sleep'] ?? 0)));
        $stageSleepMinutes = $this->resolveSleepStageMinutes($req);
        $metricSleep = $this->resolveWindowedSleepFromMetricPayload(
            $request->input('user_sleep', $request->input('data_sleep')),
            (string) ($req['sleep_type'] ?? 'night'),
            (string) ($req['device_time'] ?? '')
        );
        $req['sleep'] = $this->resolveTrustedSleepMinutes(
            $reportedSleepMinutes,
            $stageSleepMinutes,
            (int) ($metricSleep['minutes'] ?? 0)
        );
        $metricSleepStart = (int) ($metricSleep['main_start'] ?? 0);
        $reportedSleepStart = $this->normalizeMetricTs($req['sleep_start'] ?? 0);
        if ($metricSleepStart > 0 && (
            $reportedSleepStart <= 0
            || $reportedSleepStart < ($metricSleepStart - 3600)
            || $reportedSleepStart > ($metricSleepStart + 3600)
        )) {
            $req['sleep_start'] = $metricSleepStart;
        }
        $metricSleepEnd = (int) ($metricSleep['main_end'] ?? 0);
        $reportedSleepEnd = $this->normalizeMetricTs($req['sleep_end'] ?? 0);
        if ($metricSleepEnd > 0 && ($reportedSleepEnd <= 0 || $reportedSleepEnd > ($metricSleepEnd + 3600))) {
            $req['sleep_end'] = $metricSleepEnd;
        }
        $req['sleep_text'] = $this->minutesToSleepText((int) ($req['sleep'] ?? 0));

        $heartRate = (float) ($req['heart_rate'] ?? 0);
        $heartRateValid = $this->resolveHeartRateValidity($request->input('heart_rate_valid'), $heartRate);
        // Nilai mentah tetap disimpan di heart_rate. Validitas diputuskan backend dari flag/range.
        $req['heart_rate_text'] = $heartRateValid ? ((string) ((int) round($heartRate)) . ' bpm') : '-';

        $summary = Summary::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $req['user_id'],
                'send_date' => $req['send_date'],
                'sleep_type' => $req['sleep_type'],
            ],
            $req
        );
        $req['summary_id'] = $summary->id;

        $deviceDate = Str::substr((string) ($req['device_time'] ?? ''), 0, 10);
        $fileDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $deviceDate) === 1
            ? $deviceDate
            : (string) $req['send_date'];
        $file = Carbon::parse($fileDate)->format('/Y/m/d/').Str::padLeft($req['user_id'], 20, '0').'.json';

        return [
            'data' => $req,
            'file' => $file,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, file: string}
     */
    private function buildDetailIngestPayload(Request $request, Company $company, Device $device, Employee $employee): array
    {
        $req = $request->only([
            'device_time',
            'mac_address',
            'app_version',
            'employee_id',
        ]);
        $req['user_id'] = $employee->user_id;
        $req['employee_id'] = $employee->id;
        $req['device_id'] = $device->id;

        $file = '/'.Str::replace('-', '/', Str::substr($req['device_time'], 0, 10)).'/'.Str::padLeft($req['user_id'], 20, '0').'.json';

        return [
            'data' => $req,
            'file' => $file,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, file: string}
     */
    private function buildSleepSnapshotIngestPayload(Request $request, Company $company, Device $device, Employee $employee): array
    {
        $deviceTime = (string) $request->input('device_time', Carbon::now()->toDateTimeString());
        $deviceDate = Str::substr($deviceTime, 0, 10);
        $sendDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $deviceDate) === 1
            ? $deviceDate
            : Carbon::now()->toDateString();
        $sleepType = strtolower(trim((string) $request->input('sleep_type', 'night')));
        if (! in_array($sleepType, ['day', 'night'], true)) {
            $sleepType = 'night';
        }

        $req = [
            'active' => $this->nullableInteger($request->input('active')),
            'steps' => $this->nullableInteger($request->input('steps')),
            'heart_rate' => $this->nullableInteger($request->input('heart_rate')),
            'distance' => $this->nullableFloat($request->input('distance')),
            'calories' => $this->nullableInteger($request->input('calories')),
            'spo2' => $this->nullableInteger($request->input('spo2')),
            'stress' => $this->nullableInteger($request->input('stress')),
            'sleep' => $this->nullableInteger($request->input('sleep')) ?? 0,
            'sleep_start' => $this->nullableInteger($request->input('sleep_start')),
            'sleep_end' => $this->nullableInteger($request->input('sleep_end')),
            'sleep_type' => $sleepType,
            'light_sleep' => $this->nullableInteger($request->input('light_sleep')) ?? 0,
            'deep_sleep' => $this->nullableInteger($request->input('deep_sleep')) ?? 0,
            'rem_sleep' => $this->nullableInteger($request->input('rem_sleep')) ?? 0,
            'awake' => $this->nullableInteger($request->input('awake')) ?? 0,
            'wakeup' => $this->nullableInteger($request->input('wakeup')) ?? 0,
            'status' => $this->nullableInteger($request->input('status')),
            'send_date' => $sendDate,
            'send_time' => Carbon::now()->toTimeString(),
            'user_id' => $employee->user_id,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'department_id' => $employee->department_id,
            'shift_id' => $this->nullableInteger($request->input('shift_id')),
            'device_id' => $device->id,
            'device_time' => $deviceTime,
            'app_version' => $request->input('app_version'),
        ];

        $reportedSleepMinutes = max(0, (int) ($req['sleep'] ?? 0));
        $stageSleepMinutes = $this->resolveSleepStageMinutes($req);
        $metricSleep = $this->resolveWindowedSleepFromMetricPayload(
            $request->input('user_sleep', $request->input('data_sleep')),
            $sleepType,
            $deviceTime
        );
        $req['sleep'] = $this->resolveTrustedSleepMinutes(
            $reportedSleepMinutes,
            $stageSleepMinutes,
            (int) ($metricSleep['minutes'] ?? 0)
        );

        $metricSleepStart = (int) ($metricSleep['main_start'] ?? 0);
        $reportedSleepStart = $this->normalizeMetricTs($req['sleep_start'] ?? 0);
        if ($metricSleepStart > 0 && (
            $reportedSleepStart <= 0
            || $reportedSleepStart < ($metricSleepStart - 3600)
            || $reportedSleepStart > ($metricSleepStart + 3600)
        )) {
            $req['sleep_start'] = $metricSleepStart;
        }

        $metricSleepEnd = (int) ($metricSleep['main_end'] ?? 0);
        $reportedSleepEnd = $this->normalizeMetricTs($req['sleep_end'] ?? 0);
        if ($metricSleepEnd > 0 && ($reportedSleepEnd <= 0 || $reportedSleepEnd > ($metricSleepEnd + 3600))) {
            $req['sleep_end'] = $metricSleepEnd;
        }
        $req['sleep_text'] = $this->minutesToSleepText((int) ($req['sleep'] ?? 0));

        $summary = Summary::query()
            ->where('company_id', $company->id)
            ->where('user_id', $employee->user_id)
            ->whereDate('send_date', $sendDate)
            ->where('sleep_type', $sleepType)
            ->first();

        $canUpdateSummary = ! $summary || (int) ($summary->sleep ?? 0) <= 0;
        if ($canUpdateSummary) {
            $summary = Summary::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'user_id' => $employee->user_id,
                    'send_date' => $sendDate,
                    'sleep_type' => $sleepType,
                ],
                $req
            );
        }

        $req['summary_id'] = $summary?->id;

        $file = Carbon::parse($sendDate)->format('/Y/m/d/').Str::padLeft($employee->user_id, 20, '0').'.json';

        return [
            'data' => $req,
            'file' => $file,
        ];
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function applyFitToWorkPayload(Request $request, array &$req): void
    {
        $answers = [
            1 => $this->toBinaryAnswer($request->input('fit_to_work_q1', $request->input('is_fit1'))),
            2 => $this->toBinaryAnswer($request->input('fit_to_work_q2', $request->input('is_fit2'))),
            3 => $this->toBinaryAnswer($request->input('fit_to_work_q3', $request->input('is_fit3'))),
        ];

        $hasAnyAnswer = false;
        foreach ($answers as $index => $answer) {
            if ($answer === null) {
                continue;
            }

            $hasAnyAnswer = true;
            $req['is_fit' . $index] = $answer;
            $req['fit_to_work_q' . $index] = $answer;
        }

        if ($hasAnyAnswer) {
            $submittedAt = $request->input('fit_to_work_submitted_at');
            $req['fit_to_work_submitted_at'] = $submittedAt ?: Carbon::now()->toDateTimeString();
        }
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

    private function resolveHeartRateValidity(mixed $flag, float $heartRate): bool
    {
        if ($flag !== null && $flag !== '') {
            $validated = filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($validated !== null) {
                return (bool) $validated && $heartRate >= 1 && $heartRate <= 240;
            }
        }

        return $heartRate >= 1 && $heartRate <= 240;
    }

    private function resolveSleepStageMinutes(array $req): int
    {
        $total = 0;
        foreach (['deep_sleep', 'light_sleep', 'rem_sleep'] as $key) {
            $total += max(0, (int) round((float) ($req[$key] ?? 0)));
        }

        return $total;
    }

    private function resolveTrustedSleepMinutes(int $reportedMinutes, int $stageMinutes, int $metricMinutes): int
    {
        if ($reportedMinutes <= 0) {
            return $metricMinutes > 0 ? $metricMinutes : $stageMinutes;
        }

        // Mi Band 10 can send a multi-session sleep payload. Keep summary sleep
        // bounded to the selected shift window instead of summing every raw session.
        if ($metricMinutes > 0 && $reportedMinutes > ($metricMinutes + 90)) {
            return $metricMinutes;
        }

        if ($stageMinutes > 0 && $reportedMinutes > ($stageMinutes + 90)) {
            return min($reportedMinutes, $stageMinutes + 60);
        }

        return $reportedMinutes;
    }

    /**
     * @return array{minutes:int, main_start:int, main_end:int}
     */
    private function resolveWindowedSleepFromMetricPayload(mixed $payload, string $sleepType, string $deviceTime): array
    {
        $rows = $this->decodeMetricRows($payload);
        if ($rows === []) {
            return ['minutes' => 0, 'main_start' => 0, 'main_end' => 0];
        }

        try {
            $baseDate = $deviceTime !== '' ? Carbon::parse($deviceTime) : Carbon::now();
        } catch (Throwable) {
            $baseDate = Carbon::now();
        }

        $baseDate = $baseDate->copy()->startOfDay();
        $sleepType = strtolower($sleepType);

        if ($sleepType === 'night') {
            $mainStart = $baseDate->copy()->subDay()->setTime(18, 0)->timestamp;
            $mainEnd = $baseDate->copy()->setTime(10, 0)->timestamp;
            $restWindows = [
                [
                    $baseDate->copy()->subDay()->setTime(11, 0)->timestamp,
                    $baseDate->copy()->subDay()->setTime(14, 0)->timestamp,
                ],
            ];
        } else {
            $mainStart = $baseDate->copy()->setTime(6, 0)->timestamp;
            $mainEnd = $baseDate->copy()->setTime(18, 0)->timestamp;
            $restWindows = [
                [
                    $baseDate->copy()->subDay()->setTime(23, 0)->timestamp,
                    $baseDate->copy()->setTime(2, 0)->timestamp,
                ],
            ];
        }

        $mainSeconds = 0;
        $restSeconds = 0;
        $detectedMainStart = 0;
        $detectedMainEnd = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sleepStart = $this->normalizeMetricTs($row['sleepStart'] ?? $row['sleep_start'] ?? $row['start'] ?? 0);
            $sleepEnd = $this->normalizeMetricTs($row['sleepEnd'] ?? $row['sleep_end'] ?? $row['end'] ?? 0);
            if ($sleepStart <= 0 || $sleepEnd <= $sleepStart) {
                continue;
            }

            $intervalSeconds = max(1, $sleepEnd - $sleepStart);
            $durationSeconds = $this->resolveSleepDurationSeconds($row, $intervalSeconds);
            if ($durationSeconds <= 0) {
                continue;
            }

            $mainOverlap = $this->weightedOverlapSeconds($sleepStart, $sleepEnd, $durationSeconds, $mainStart, $mainEnd);
            if ($mainOverlap > 0) {
                $mainSeconds += $mainOverlap;
                $overlapStart = max($sleepStart, $mainStart);
                $overlapEnd = min($sleepEnd, $mainEnd);
                $detectedMainStart = $detectedMainStart === 0 ? $overlapStart : min($detectedMainStart, $overlapStart);
                $detectedMainEnd = max($detectedMainEnd, $overlapEnd);
            }

            foreach ($restWindows as [$restStart, $restEnd]) {
                $restSeconds += $this->weightedOverlapOutsideMainSeconds(
                    $sleepStart,
                    $sleepEnd,
                    $durationSeconds,
                    $restStart,
                    $restEnd,
                    [[$mainStart, $mainEnd]]
                );
            }
        }

        $restSeconds = min($restSeconds, 60 * 60);

        return [
            'minutes' => (int) round(($mainSeconds + $restSeconds) / 60),
            'main_start' => $detectedMainStart,
            'main_end' => $detectedMainEnd,
        ];
    }

    private function resolveSleepDurationSeconds(array $row, int $intervalSeconds): int
    {
        $total = $this->normalizeDurationToSeconds(
            $row['totalSleepDuration'] ?? $row['total_sleep_duration'] ?? $row['total_sleep'] ?? $row['duration'] ?? $row['duration_seconds'] ?? 0,
            $intervalSeconds
        );
        if ($total > 0) {
            return min($total, $intervalSeconds);
        }

        $stageSeconds = 0;
        foreach ([
            ['lightSleepDuration', 'light_sleep_duration', 'light_sleep', 'light'],
            ['deepSleepDuration', 'deep_sleep_duration', 'deep_sleep', 'deep'],
            ['remSleepDuration', 'rem_sleep_duration', 'rem_sleep', 'rem'],
        ] as $keys) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $row)) {
                    continue;
                }
                $stageSeconds += $this->normalizeDurationToSeconds($row[$key], $intervalSeconds);
                break;
            }
        }

        if ($stageSeconds > 0) {
            return min($stageSeconds, $intervalSeconds);
        }

        return $intervalSeconds;
    }

    private function weightedOverlapSeconds(int $start, int $end, int $durationSeconds, int $windowStart, int $windowEnd): int
    {
        if ($end <= $windowStart || $start >= $windowEnd) {
            return 0;
        }

        $overlap = max(0, min($end, $windowEnd) - max($start, $windowStart));
        if ($overlap <= 0) {
            return 0;
        }

        $intervalSeconds = max(1, $end - $start);
        return (int) round($durationSeconds * ($overlap / $intervalSeconds));
    }

    private function weightedOverlapOutsideMainSeconds(
        int $start,
        int $end,
        int $durationSeconds,
        int $windowStart,
        int $windowEnd,
        array $mainWindows
    ): int {
        if ($end <= $windowStart || $start >= $windowEnd) {
            return 0;
        }

        $overlapStart = max($start, $windowStart);
        $overlapEnd = min($end, $windowEnd);
        $overlapSeconds = max(0, $overlapEnd - $overlapStart);
        if ($overlapSeconds <= 0) {
            return 0;
        }

        foreach ($mainWindows as $mainWindow) {
            if (!is_array($mainWindow) || count($mainWindow) < 2) {
                continue;
            }

            $mainStart = (int) $mainWindow[0];
            $mainEnd = (int) $mainWindow[1];
            $mainOverlap = max(0, min($overlapEnd, $mainEnd) - max($overlapStart, $mainStart));
            $overlapSeconds -= $mainOverlap;
        }

        if ($overlapSeconds <= 0) {
            return 0;
        }

        $intervalSeconds = max(1, $end - $start);
        return (int) round($durationSeconds * ($overlapSeconds / $intervalSeconds));
    }

    private function decodeMetricRows(mixed $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        if (is_string($payload)) {
            try {
                $decoded = json_decode(trim($payload), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        } else {
            $decoded = $payload;
        }

        if (!is_array($decoded) || $decoded === []) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        return [$decoded];
    }

    private function normalizeDurationToSeconds(mixed $value, int $intervalSeconds): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $duration = (int) max(0, round((float) $value));
        if ($duration <= 0) {
            return 0;
        }

        if ($duration < 1000 && ($intervalSeconds <= 0 || ($duration * 60) <= (int) round($intervalSeconds * 1.5))) {
            $duration *= 60;
        }

        if ($intervalSeconds > 0 && $duration > (int) round($intervalSeconds * 1.5)) {
            return $intervalSeconds;
        }

        return $duration;
    }

    private function normalizeMetricTs(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $ts = (int) $value;
        if ($ts <= 0) {
            return 0;
        }
        if ($ts > 9999999999) {
            return (int) floor($ts / 1000);
        }

        return $ts;
    }

    private function minutesToSleepText(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }

    private function minutesToTicketSleepText(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d jam %02d menit', $hours, $mins);
    }

    public function debugDetailPayload(Request $request)
    {
        $mac = $request->input('mac_address');
        $company = $this->findCompanyByCode($request->header('company'), $request->user()?->id);
        
        if (!$company) {
            return response(['message' => 'Company not found'], 404);
        }

        $payload = [
            'timestamp' => now()->toIso8601String(),
            'user_id' => $request->user()->id,
            'mac_address' => $mac,
            'company' => $company->code,
            'all_input' => $request->all(),
            'raw_content' => $request->getContent(),
            'metric_fields_present' => [
                'user_activity' => !is_null($request->input('user_activity')),
                'user_sleep' => !is_null($request->input('user_sleep')),
                'user_stress' => !is_null($request->input('user_stress')),
                'user_spo2' => !is_null($request->input('user_spo2')),
                'user_heart_rate_max' => !is_null($request->input('user_heart_rate_max')),
                'user_heart_rate_resting' => !is_null($request->input('user_heart_rate_resting')),
                'user_heart_rate_manual' => !is_null($request->input('user_heart_rate_manual')),
                // Alternative field names
                'data_activity' => !is_null($request->input('data_activity')),
                'data_sleep' => !is_null($request->input('data_sleep')),
                'data_stress' => !is_null($request->input('data_stress')),
                'data_spo2' => !is_null($request->input('data_spo2')),
                'data_heart_rate_max' => !is_null($request->input('data_heart_rate_max')),
                'data_heart_rate_resting' => !is_null($request->input('data_heart_rate_resting')),
                'data_heart_rate_manual' => !is_null($request->input('data_heart_rate_manual')),
            ],
            'sample_data' => [
                'user_heart_rate_max' => Str::limit($request->input('user_heart_rate_max', ''), 200),
                'user_heart_rate_resting' => Str::limit($request->input('user_heart_rate_resting', ''), 200),
                'user_heart_rate_manual' => Str::limit($request->input('user_heart_rate_manual', ''), 200),
            ],
        ];

        \Log::channel('miband-debug')->info('Mi Band Detail Payload Debug', $payload);

        return response([
            'message' => 'Payload logged successfully',
            'device' => $mac,
            'metric_count' => array_sum($payload['metric_fields_present']),
        ]);
    }
}

