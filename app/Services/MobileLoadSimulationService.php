<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Shift;
use App\Support\MobileIngestRuntime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class MobileLoadSimulationService
{
    /**
     * @return array<int, array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}>
     */
    public function createFleet(int $count, ?string $companyCode = null): array
    {
        $company = Company::create([
            'code' => $companyCode ?? 'SIM',
            'name' => 'Simulation Fleet',
        ]);
        $department = Department::create([
            'company_id' => $company->id,
            'code' => 'OPS',
            'name' => 'Operations',
        ]);
        $shift = Shift::create([
            'company_id' => $company->id,
            'code' => 'DAY',
            'name' => 'Day Shift',
        ]);

        $fleet = [];

        for ($i = 1; $i <= $count; $i++) {
            $user = User::factory()->create([
                'name' => 'Mobile '.$i,
                'email' => 'mobile'.$i.'@example.test',
            ]);
            $device = Device::create([
                'company_id' => $company->id,
                'mac_address' => sprintf('AA:BB:CC:DD:EE:%02d', $i),
                'device_name' => 'Watch '.$i,
            ]);
            $employee = Employee::create([
                'company_id' => $company->id,
                'department_id' => $department->id,
                'device_id' => $device->id,
                'user_id' => $user->id,
                'code' => 'EMP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'fullname' => 'Employee '.$i,
            ]);
            $token = $user->createToken('mobile-load-'.$i)->plainTextToken;

            $fleet[] = [
                'user' => $user,
                'company' => $company,
                'department' => $department,
                'shift' => $shift,
                'device' => $device,
                'employee' => $employee,
                'token' => $token,
            ];
        }

        return $fleet;
    }

    /**
     * @param  array<int, array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}>  $fleet
     * @return array{summary_calls: int, detail_calls: int, summary_ok: int, detail_ok: int, file_count: int}
     */
    public function runUploadBatch(array $fleet, int $retries = 2, bool $useFakeStorage = true, array $latencyMs = []): array
    {
        if ($useFakeStorage) {
            Storage::fake(MobileIngestRuntime::storageDisk('local'));
        }

        $summaryCalls = 0;
        $detailCalls = 0;
        $summaryOk = 0;
        $detailOk = 0;

        foreach ($fleet as $member) {
            $headers = $this->apiHeaders($member['company']->code, $member['token'], $latencyMs);
            $summaryPayload = $this->summaryPayload($member);
            $detailPayload = $this->detailPayload($member);

            for ($i = 0; $i < $retries; $i++) {
                $summaryCalls++;
                if ($this->requestJson('POST', '/api/summary', $summaryPayload, $headers)->getStatusCode() < 400) {
                    $summaryOk++;
                }

                $detailCalls++;
                if ($this->requestJson('POST', '/api/detail', $detailPayload, $headers)->getStatusCode() < 400) {
                    $detailOk++;
                }
            }
        }

        return [
            'summary_calls' => $summaryCalls,
            'detail_calls' => $detailCalls,
            'summary_ok' => $summaryOk,
            'detail_ok' => $detailOk,
            'file_count' => count(Storage::disk(MobileIngestRuntime::storageDisk('local'))->allFiles()),
        ];
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, array{calls: int, ok: int, min_ms: float, max_ms: float, avg_ms: float}>
     */
    public function benchmarkEndpoints(array $member, int $iterations = 3, array $latencyMs = []): array
    {
        $results = [];

        $headers = $this->apiHeaders($member['company']->code, $member['token'], $latencyMs);
        $summaryPayload = $this->summaryPayload($member);
        $detailPayload = $this->detailPayload($member);
        $leavePayload = $this->leavePayload($member);

        $results['profile'] = $this->benchmark(fn () => $this->requestJson('GET', '/api/profile', [], $headers));
        $results['summary'] = $this->benchmark(fn () => $this->requestJson('POST', '/api/summary', $summaryPayload, $headers), $iterations);
        $results['detail'] = $this->benchmark(fn () => $this->requestJson('POST', '/api/detail', $detailPayload, $headers), $iterations);
        $results['leave'] = $this->benchmark(fn () => $this->requestJson('POST', '/api/leave', $leavePayload, $headers), $iterations);

        $this->requestJson('POST', '/api/summary', $summaryPayload, $headers);
        $results['ticket'] = $this->benchmark(fn () => $this->requestJson('GET', '/api/ticket/'.$member['employee']->id, [], $headers), $iterations);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function requestJson(string $method, string $uri, array $payload, array $headers): BaseResponse
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }
        $server['HTTP_ACCEPT'] = $server['HTTP_ACCEPT'] ?? 'application/json';
        $server['CONTENT_TYPE'] = 'application/json';

        $content = empty($payload) ? null : json_encode($payload, JSON_THROW_ON_ERROR);
        $request = Request::create($uri, $method, [], [], [], $server, $content);
        $response = app(HttpKernel::class)->handle($request);
        app(HttpKernel::class)->terminate($request, $response);

        return $response;
    }

    /**
     * @param  callable(): Response  $callback
     * @return array{calls: int, ok: int, min_ms: float, max_ms: float, avg_ms: float}
     */
    public function benchmark(callable $callback, int $iterations = 3): array
    {
        $durations = [];
        $ok = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $response = $callback();
            $durations[] = round((microtime(true) - $start) * 1000, 2);

            if ($response->getStatusCode() < 400) {
                $ok++;
            }
        }

        return [
            'calls' => $iterations,
            'ok' => $ok,
            'min_ms' => min($durations),
            'max_ms' => max($durations),
            'avg_ms' => round(array_sum($durations) / max(count($durations), 1), 2),
        ];
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, int|string>
     */
    private function summaryPayload(array $member): array
    {
        return [
            'active' => 80,
            'steps' => 1200 + $member['employee']->id,
            'heart_rate' => 72,
            'distance' => 1567,
            'calories' => 321,
            'spo2' => 98,
            'stress' => 21,
            'sleep' => 420,
            'sleep_start' => 1711414800,
            'sleep_end' => 1711440000,
            'sleep_type' => 'night',
            'light_sleep' => 220,
            'deep_sleep' => 120,
            'rem_sleep' => 60,
            'awake' => 20,
            'wakeup' => 0,
            'status' => 0,
            'device_time' => Carbon::now()->toDateTimeString(),
            'mac_address' => $member['device']->mac_address,
            'app_version' => '1.2.3 (Android)',
            'user_activity' => $this->largeMetricPayload('activity'),
            'user_sleep' => $this->largeMetricPayload('sleep'),
            'user_stress' => $this->largeMetricPayload('stress'),
            'user_spo2' => $this->largeMetricPayload('spo2'),
            'device_id' => $member['device']->id,
            'employee_id' => $member['employee']->id,
            'company_id' => $member['company']->id,
            'department_id' => $member['department']->id,
            'shift_id' => $member['shift']->id,
            'is_fit1' => 1,
            'is_fit2' => 1,
            'is_fit3' => 1,
        ];
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, string>
     */
    private function detailPayload(array $member): array
    {
        return [
            'device_time' => Carbon::now()->toDateTimeString(),
            'mac_address' => $member['device']->mac_address,
            'app_version' => '1.2.3 (Android)',
            'employee_id' => $member['employee']->id,
            'user_activity' => $this->largeMetricPayload('activity'),
            'user_sleep' => $this->largeMetricPayload('sleep'),
            'user_stress' => $this->largeMetricPayload('stress'),
            'user_spo2' => $this->largeMetricPayload('spo2'),
            'user_heart_rate_max' => $this->largeMetricPayload('hr_max'),
            'user_heart_rate_resting' => $this->largeMetricPayload('hr_rest'),
            'user_heart_rate_manual' => $this->largeMetricPayload('hr_manual'),
        ];
    }

    /**
     * @param  array{user: User, company: Company, department: Department, shift: Shift, device: Device, employee: Employee}  $member
     * @return array<string, string>
     */
    private function leavePayload(array $member): array
    {
        return [
            'employee_id' => $member['employee']->id,
            'type' => 'izin',
            'phone' => '081234567890',
            'note' => 'Jaringan lambat, upload dipaksa ulang',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $companyCode, string $token, array $latencyMs = []): array
    {
        $headers = [
            'Accept' => 'application/json',
            'company' => $companyCode,
            'Authorization' => 'Bearer '.$token,
        ];

        $allLatency = max(0, (int) ($latencyMs['all'] ?? 0));
        if ($allLatency > 0) {
            $headers['X-Savera-Simulate-Latency-Ms'] = (string) $allLatency;
        }

        foreach (['summary', 'detail', 'profile', 'leave', 'ticket'] as $route) {
            $routeLatency = max(0, (int) ($latencyMs[$route] ?? 0));
            if ($routeLatency > 0) {
                $headers['X-Savera-Simulate-'.ucfirst($route).'-Latency-Ms'] = (string) $routeLatency;
            }
        }

        return $headers;
    }

    private function largeMetricPayload(string $label): string
    {
        $rows = [];

        for ($i = 0; $i < 40; $i++) {
            $rows[] = [
                'label' => $label,
                'timestamp' => 1711414800 + ($i * 60),
                'value' => $i + 1,
            ];
        }

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }
}
