<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use App\Models\Company;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|confirmed|min:6'
        ]);

        $user = User::create($data);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string',
                'password' => 'required|string|min:5'
            ]);

            $input = trim((string) $request->input('email', ''));
            $password = (string) $request->input('password', '');
            $companyCode = trim((string) $request->header('company', ''));

            // Try login by email or NIK
            $user = $this->findUserByEmailOrNik($input, $companyCode !== '' ? $companyCode : null);
            
            if (!$user || !Hash::check($password, $user->password)) {
                return response([
                    'message' => 'Incorrect username or password'
                ], 401);
            }
            $company = $this->resolveLoginCompany($user);
            $employee = Employee::query()
                ->when($company, function ($query) use ($company) {
                    $query->where('company_id', $company->id);
                })
                ->where('user_id', $user->id)
                ->first();

            if (! $employee) {
                return response([
                    'message' => 'Akun belum terdaftar sebagai employee di company ini.'
                ], 403);
            }

            if ((int) $employee->status !== 1) {
                return response([
                    'message' => 'Akun non aktif. Hubungi admin.'
                ], 403);
            }

            $externalStatus = $this->resolveExternalEmployeeStatus((string) $employee->code, $company?->code);
            if ($externalStatus === false) {
                return response([
                    'message' => 'Akun non aktif berdasarkan master karyawan perusahaan.'
                ], 403);
            }

            $isSleepUploader = $this->isSleepUploader($user);
            $requireBoundDevice = filter_var(env('MOBILE_REQUIRE_BOUND_DEVICE', false), FILTER_VALIDATE_BOOL);
            if ($requireBoundDevice && ! $isSleepUploader) {
                if (is_null($employee->device_id)) {
                    return response([
                        'message' => 'Akun belum aktif di mobile. MAC/device belum didaftarkan.'
                    ], 403);
                }

                $device = Device::query()->whereKey($employee->device_id)->first();
                if (! $device || (int) ($device->is_active ?? 0) !== 1) {
                    return response([
                        'message' => 'Device non aktif atau tidak ditemukan. Hubungi admin.'
                    ], 403);
                }
            }

            $this->recordLoginAudit($request, $user);

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->is_sleep_uploader = $isSleepUploader;

            return response([
                'user' => $user,
                'token' => $token,
                'company' => $company ? [
                    'id' => $company->id,
                    'code' => $company->code,
                    'name' => $company->name,
                ] : null,
            ]);
        } catch (Throwable $e) {
            // Logging error harian
            LogHelper::logError('Proses Simpan Absensi', $e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => method_exists($e, 'errors') ? $e->errors() : null
            ], 500);
        }
        
    }

    private function findUserByEmailOrNik(string $input, ?string $companyCode = null): ?User
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        [$prefixCompanyCode, $nikFromPrefixedInput] = $this->extractCompanyAndNikFromInput($input);
        $companyCodes = array_values(array_filter([
            $this->normalizeCompanyCode($companyCode),
            $this->normalizeCompanyCode($prefixCompanyCode),
        ]));
        $companyIds = $this->resolveCompanyIdsByCodes($companyCodes);
        $companyScopes = ! empty($companyIds) ? [$companyIds, []] : [[]];

        // If input contains @, treat as email
        if (str_contains($input, '@')) {
            foreach ($companyScopes as $scopeCompanyIds) {
                $user = User::query()
                    ->when(! empty($scopeCompanyIds), function ($query) use ($scopeCompanyIds) {
                        $query->whereIn('company_id', $scopeCompanyIds);
                    })
                    ->whereRaw('LOWER(email) = ?', [strtolower($input)])
                    ->first();
                if ($user) {
                    return $user;
                }
            }

            return null;
        }

        // Fallback for username-like identifiers from mobile (e.g. UDU24011950928).
        foreach ($companyScopes as $scopeCompanyIds) {
            $directUser = User::query()
                ->when(! empty($scopeCompanyIds), function ($query) use ($scopeCompanyIds) {
                    $query->whereIn('company_id', $scopeCompanyIds);
                })
                ->where(function ($query) use ($input) {
                    $query->whereRaw('LOWER(name) = ?', [strtolower($input)])
                        ->orWhereRaw('LOWER(email) = ?', [strtolower($input)]);
                })
                ->first();
            if ($directUser) {
                return $directUser;
            }
        }

        $nikCandidates = array_values(array_unique(array_filter([
            $input,
            $nikFromPrefixedInput,
        ], static fn ($value) => trim((string) $value) !== '')));

        // Otherwise, treat as NIK (employee code) and find user via employee.
        foreach ($companyScopes as $scopeCompanyIds) {
            $employee = Employee::query()
                ->when(! empty($scopeCompanyIds), function ($query) use ($scopeCompanyIds) {
                    $query->whereIn('company_id', $scopeCompanyIds);
                })
                ->whereIn('code', $nikCandidates)
                ->whereNotNull('user_id')
                ->orderByDesc('status')
                ->orderByRaw('CASE WHEN device_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->first();
            if ($employee && $employee->user_id) {
                return User::where('id', $employee->user_id)->first();
            }
        }

        return null;
    }

    private function resolveCompanyIdsByCode(?string $companyCode): array
    {
        $companyCode = $this->normalizeCompanyCode($companyCode);
        if ($companyCode === '') {
            return [];
        }

        return $this->resolveCompanyIdsByCodes([$companyCode]);
    }

    private function resolveCompanyIdsByCodes(array $companyCodes): array
    {
        $normalizedCodes = collect($companyCodes)
            ->map(fn ($code) => $this->normalizeCompanyCode((string) $code))
            ->filter(fn ($code) => $code !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedCodes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedCodes), '?'));

        return Company::query()
            ->whereRaw('UPPER(code) IN (' . $placeholders . ')', $normalizedCodes)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractCompanyAndNikFromInput(string $input): array
    {
        $compact = strtoupper((string) preg_replace('/\s+/', '', trim($input)));
        if ($compact === '') {
            return ['', ''];
        }

        if (preg_match('/^([A-Z]{2,10})([0-9]{4,})$/', $compact, $matches) !== 1) {
            return ['', ''];
        }

        $companyPrefix = $this->normalizeCompanyCode($matches[1] ?? '');
        $nik = trim((string) ($matches[2] ?? ''));

        return [$companyPrefix, $nik];
    }

    private function recordLoginAudit(Request $request, User $user): void
    {
        $audit = [];
        if (Schema::hasColumn('users', 'last_login_at')) {
            $audit['last_login_at'] = now();
        }
        if (Schema::hasColumn('users', 'last_login_ip')) {
            $audit['last_login_ip'] = $this->resolveClientIp($request);
        }
        if (Schema::hasColumn('users', 'last_login_user_agent')) {
            $audit['last_login_user_agent'] = Str::limit((string) $request->userAgent(), 1000, '');
        }

        if ($audit !== []) {
            $user->forceFill($audit)->save();
        }
    }

    private function isSleepUploader(User $user): bool
    {
        if (! Schema::hasColumn('users', 'is_sleep_uploader')) {
            return false;
        }

        return filter_var($user->is_sleep_uploader ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizeCompanyCode(?string $companyCode): string
    {
        $code = strtoupper(trim((string) $companyCode));
        if ($code === '') {
            return '';
        }

        $aliasMap = [
            'IC' => 'INDEXIM',
        ];

        return $aliasMap[$code] ?? $code;
    }

    private function resolveClientIp(Request $request): string
    {
        $forwardedFor = (string) $request->headers->get('X-Forwarded-For', '');
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $candidate = trim((string) ($parts[0] ?? ''));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return (string) $request->ip();
    }

    private function resolveLoginCompany(User $user): ?Company
    {
        $employeeCompanyId = Employee::query()
            ->where('user_id', $user->id)
            ->value('company_id');

        if ($employeeCompanyId) {
            return Company::query()
                ->select(['id', 'code', 'name'])
                ->whereKey($employeeCompanyId)
                ->first();
        }

        return Company::query()
            ->select(['id', 'code', 'name'])
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    private function resolveExternalEmployeeStatus(string $nik, ?string $companyCode): ?bool
    {
        $nik = trim($nik);
        if ($nik === '') {
            return null;
        }

        $secretKey = trim((string) env('INDEXIM_SECRET_KEY', ''));
        if ($secretKey === '') {
            return null;
        }

        $companyId = $this->resolveExternalCompanyId($companyCode);
        $cacheKey = 'indexim:employee_status:' . $companyId;

        $statusMap = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($companyId, $secretKey) {
            $url = (string) env('INDEXIM_EMPLOYEE_API_URL', 'https://oneev.indexim.id/Employee/CompanyEmployees');
            $response = Http::timeout(45)
                ->acceptJson()
                ->get($url, [
                    'id_perusahaan' => $companyId,
                    'secret_key' => $secretKey,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $payload = $response->json();
            if (! is_array($payload) || ! ((bool) ($payload['success'] ?? false))) {
                return [];
            }

            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return [];
            }

            $map = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rowNik = trim((string) ($row['no_nik'] ?? ''));
                if ($rowNik === '') {
                    continue;
                }

                $map[$rowNik] = (bool) ($row['status_aktif_karyawan'] ?? false);
            }

            return $map;
        });

        if (! is_array($statusMap) || ! array_key_exists($nik, $statusMap)) {
            return null;
        }

        return (bool) $statusMap[$nik];
    }

    private function resolveExternalCompanyId(?string $companyCode): int
    {
        $fallback = (int) env('INDEXIM_ID_PERUSAHAAN', 3);
        $code = strtoupper(trim((string) $companyCode));
        if ($code === '') {
            return $fallback;
        }

        $mapText = trim((string) env('INDEXIM_ID_PERUSAHAAN_MAP', ''));
        if ($mapText === '') {
            return $fallback;
        }

        $pairs = array_filter(array_map('trim', explode(',', $mapText)));
        foreach ($pairs as $pair) {
            [$k, $v] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, '');
            if ($k !== '' && strtoupper($k) === $code && is_numeric($v)) {
                return (int) $v;
            }
        }

        return $fallback;
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response([
            'message' => 'User logged out'
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string|min:6',
            'new_password' => 'required|string|confirmed|min:6'
        ]);

        if (!Hash::check($request->old_password, $request->user()->password)) {
            return response([
                'message' => 'Old password doesn\'t match'
            ], 401);
        }

        User::whereId($request->user()->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response([
            'message' => 'Password changed successfully'
        ]);
    }
}
