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
use App\Models\Ticket;
use App\Support\MobileIngestRuntime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $user = $request->user()->toArray();
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
            $user['employee'] = $employee->toArray();
            $user['shift'] = Shift::query()->whereKey(1)->first()?->toArray();
            $user['device'] = (is_null($employee->device_id)) ? null : Device::query()->whereKey($employee->device_id)->first()?->toArray();
            if (in_array($request->user()->name, ['SAVERA', 'ROMI', 'ANDRE', 'OBIT', 'ANDI', 'HULAEPI', 'FAISAL', 'ROBI', 'IVAN', 'EREN', 'TABLET 1', 'TABLET 2', 'TABLET 3', 'TABLET 4'])) {
                $user['is_admin'] = 1;
            }

            return $user;
        });

        if ($payload === null) {
            return response([
                'message' => 'Employee not found.',
            ], 404);
        }

        return response($payload);
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

        $cacheKey = $this->deviceCacheKey($company->id, (string) $mac);
        $device = Cache::store($this->cacheStore())->remember($cacheKey, 60, function () use ($company, $mac) {
            return Device::query()
                ->select(['id', 'company_id', 'mac_address', 'device_name', 'brand', 'auth_key', 'is_active'])
                ->where('company_id', $company->id)
                ->where('mac_address', $mac)
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
                'distance' => 'required|numeric',
                'calories' => 'required|numeric',
                'spo2' => 'required|numeric',
                'stress' => 'required|numeric',
                'sleep' => 'required|numeric',
                'device_time' => 'required|string',
                'mac_address' => 'required|string',
                'employee_id' => 'required|numeric',
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
            $lineup = DB::table('lineup_operator')
                ->where('tanggal', date('Y-m-d'))
                ->where('company_id', $company->id)
                ->where('nik', $employee->code)
                ->orderBy('no')
                ->first();

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
        $summary['sleep_text'] = '-';
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
        return 'device:' . $companyId . ':' . Str::of($macAddress)->lower()->replace(':', '');
    }

    private function findCompanyByCode(?string $code, ?int $userId = null): ?Company
    {
        if ($code) {
            $company = Company::query()
                ->select(['id', 'code', 'name'])
                ->where('code', $code)
                ->first();

            if ($company) {
                return $company;
            }
        }

        if ($userId) {
            $companyId = Employee::query()
                ->where('user_id', $userId)
                ->value('company_id');

            if ($companyId) {
                return Company::query()
                    ->select(['id', 'code', 'name'])
                    ->whereKey($companyId)
                    ->first();
            }
        }

        return Company::query()
            ->select(['id', 'code', 'name'])
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    private function findDeviceByMac(int $companyId, string $macAddress): ?Device
    {
        return Device::query()
            ->select(['id', 'company_id', 'mac_address'])
            ->where('company_id', $companyId)
            ->where('mac_address', $macAddress)
            ->first();
    }

    private function lockEmployeeByCompanyAndId(int $companyId, int $employeeId): ?Employee
    {
        return Employee::query()
            ->select(['id', 'user_id', 'company_id'])
            ->whereKey($employeeId)
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, string>  $metricMap
     * @return array<int, array{path: string, contents: string}>
     */
    private function buildMetricWriteQueue(Request $request, array $metricMap, string $file): array
    {
        $filesToWrite = [];

        foreach ($metricMap as $pathPrefix => $requestKey) {
            $filesToWrite[] = [
                'path' => $pathPrefix.$file,
                'contents' => $this->normalizeMetricPayload($request->input($requestKey, '[]')),
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

        if ($mode === 'sync') {
            dispatch_sync($job);
            return;
        }

        if ($mode === 'queue' || ($mode === 'auto' && MobileIngestRuntime::usesAsyncQueue())) {
            dispatch($job);
            return;
        }

        dispatch($job)->afterResponse();
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
            $deviceKey = Str::of((string) $request->input('mac_address'))->lower()->replace(':', '');
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
                    return response([
                        'message' => 'Your device\'s MAC address is unavailable.',
                    ], 404);
                }

                $employee = $this->lockEmployeeByCompanyAndId($company->id, (int) $request->input('employee_id'));
                if (! $employee) {
                    return response([
                        'message' => 'Employee not found.',
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
        $req['company_id'] = $company->id;
        $req['device_id'] = $device->id;
        $req['send_date'] = Carbon::now()->toDateString();
        $req['send_time'] = Carbon::now()->toTimeString();
        $req['sleep_type'] = $request->input('sleep_type', 'night') ?? 'night';

        Summary::query()->updateOrCreate(
            [
                'user_id' => $req['user_id'],
                'send_date' => $req['send_date'],
                'sleep_type' => $req['sleep_type'],
            ],
            $req
        );

        $file = Carbon::parse($req['send_date'])->format('/Y/m/d/').Str::padLeft($req['user_id'], 20, '0').'.json';

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
        $req['device_id'] = $device->id;

        $file = '/'.Str::replace('-', '/', Str::substr($req['device_time'], 0, 10)).'/'.Str::padLeft($req['user_id'], 20, '0').'.json';

        return [
            'data' => $req,
            'file' => $file,
        ];
    }
}

