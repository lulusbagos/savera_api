using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    private static string? LimitText(string? value, int maxLength)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return value;
        }

        var trimmed = value.Trim();
        return trimmed.Length <= maxLength ? trimmed : trimmed[..maxLength];
    }

    private static string PrepareDbJsonPayload(string? value, bool storeRawPayload)
    {
        if (!storeRawPayload)
        {
            return "[]";
        }

        return NormalizeJsonPayload(value);
    }

    private static int ToSafeInt(decimal? value)
    {
        if (!value.HasValue)
        {
            return 0;
        }

        return (int)Math.Round(value.Value, MidpointRounding.AwayFromZero);
    }

    private static async Task<DeviceRow?> ResolveDeviceByMacAsync(NpgsqlDataSource db, int companyId, string macAddress)
    {
        return await db.QuerySingleOrDefaultAsync<DeviceRow>(@"
SELECT id, company_id, brand, device_name, mac_address, auth_key, app_version, updated_at
FROM public.tbl_m_device
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(mac_address)=lower(@MacAddress)
LIMIT 1", new
        {
            CompanyId = companyId,
            MacAddress = NormalizeMac(macAddress)
        });
    }

    private static async Task<EmployeeProfileRow?> ResolveEmployeeByIdAsync(NpgsqlDataSource db, int companyId, int employeeId)
    {
        return await db.QuerySingleOrDefaultAsync<EmployeeProfileRow>(@"
SELECT e.id,
       e.company_id,
       e.department_id,
       e.mess_id,
       e.shift_id,
       e.device_id,
       e.user_id,
       COALESCE(NULLIF(e.code, ''), e.nik) AS code,
       e.nik,
       e.full_name AS fullname,
       e.email,
       e.phone,
       e.photo,
       e.job,
       e.position
FROM public.tbl_r_employee e
WHERE e.deleted_at IS NULL
  AND e.company_id=@CompanyId
  AND e.id=@EmployeeId
LIMIT 1", new
        {
            CompanyId = companyId,
            EmployeeId = employeeId
        });
    }

    private static async Task<int?> FindSummaryIdByEmployeeDateAsync(
        NpgsqlConnection conn,
        NpgsqlTransaction tx,
        int companyId,
        int employeeId,
        DateOnly sendDate)
    {
        return await conn.QuerySingleOrDefaultAsync<int?>(@"
SELECT id
FROM public.tbl_t_summary
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND employee_id=@EmployeeId
  AND send_date=@SendDate
ORDER BY id DESC
LIMIT 1", new
        {
            CompanyId = companyId,
            EmployeeId = employeeId,
            SendDate = sendDate.ToDateTime(TimeOnly.MinValue).Date
        }, tx);
    }

    private static async Task<int> UpsertSummaryAsync(
        NpgsqlConnection conn,
        NpgsqlTransaction tx,
        int companyId,
        int departmentId,
        int deviceId,
        SummaryRequest request,
        int employeeId,
        DateTime now,
        string routeBase,
        int retryCount)
    {
        var sendDate = (ExtractDateOnly(request.DeviceTime) ?? DateOnly.FromDateTime(now)).ToDateTime(TimeOnly.MinValue).Date;
        var sleepType = string.IsNullOrWhiteSpace(request.SleepType) ? "night" : request.SleepType!.Trim().ToLowerInvariant();
        var uploadKey = string.IsNullOrWhiteSpace(request.UploadKey) ? null : request.UploadKey!.Trim();

        int? existingId = null;
        if (!string.IsNullOrWhiteSpace(uploadKey))
        {
            existingId = await conn.QuerySingleOrDefaultAsync<int?>(@"
SELECT id
FROM public.tbl_t_summary
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND upload_key=@UploadKey
ORDER BY id DESC
LIMIT 1", new { CompanyId = companyId, UploadKey = uploadKey }, tx);
        }

        if (!existingId.HasValue)
        {
            existingId = await conn.QuerySingleOrDefaultAsync<int?>(@"
SELECT id
FROM public.tbl_t_summary
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND employee_id=@EmployeeId
  AND send_date=@SendDate
  AND sleep_type=@SleepType
ORDER BY id DESC
LIMIT 1", new
            {
                CompanyId = companyId,
                EmployeeId = employeeId,
                SendDate = sendDate,
                SleepType = sleepType
            }, tx);
        }

        if (existingId.HasValue)
        {
            await conn.ExecuteAsync(@"
UPDATE public.tbl_t_summary
SET department_id=@DepartmentId,
    employee_id=@EmployeeId,
    shift_id=@ShiftId,
    device_id=@DeviceId,
    send_date=@SendDate,
    send_time=@SendTime,
    device_time=@DeviceTime,
    app_version=@AppVersion,
    active=@Active,
    active_text=@ActiveText,
    steps=@Steps,
    steps_text=@StepsText,
    distance=@Distance,
    distance_text=@DistanceText,
    calories=@Calories,
    calories_text=@CaloriesText,
    heart_rate=@HeartRate,
    heart_rate_text=@HeartRateText,
    spo2=@Spo2,
    spo2_text=@Spo2Text,
    stress=@Stress,
    stress_text=@StressText,
    sleep=@Sleep,
    sleep_text=@SleepText,
    sleep_start=@SleepStart,
    sleep_end=@SleepEnd,
    sleep_type=@SleepType,
    deep_sleep=@DeepSleep,
    light_sleep=@LightSleep,
    rem_sleep=@RemSleep,
    awake=@Awake,
    wakeup=@Wakeup,
    is_fit1=@IsFit1,
    is_fit2=@IsFit2,
    is_fit3=@IsFit3,
    status=@Status,
    mac_address=@MacAddress,
    request_id=@RequestId,
    upload_key=@UploadKey,
    route_base=@RouteBase,
    retry_count=@RetryCount,
    upload_status='accepted',
    last_error_message=NULL,
    updated_at=now()
WHERE id=@Id", new
            {
                Id = existingId.Value,
                DepartmentId = departmentId,
                EmployeeId = employeeId,
                ShiftId = request.ShiftId,
                DeviceId = deviceId,
                SendDate = sendDate,
                SendTime = now.TimeOfDay,
                DeviceTime = request.DeviceTime,
                AppVersion = LimitText(request.AppVersion, 64),
                Active = ToSafeInt(request.Active),
                ActiveText = request.ActiveText,
                Steps = ToSafeInt(request.Steps),
                StepsText = request.StepsText,
                Distance = request.Distance ?? 0m,
                DistanceText = request.DistanceText,
                Calories = request.Calories ?? 0m,
                CaloriesText = request.CaloriesText,
                HeartRate = ToSafeInt(request.HeartRate),
                HeartRateText = request.HeartRateText,
                Spo2 = ToSafeInt(request.Spo2),
                Spo2Text = request.Spo2Text,
                Stress = ToSafeInt(request.Stress),
                StressText = request.StressText,
                Sleep = request.Sleep ?? 0m,
                SleepText = request.SleepText,
                SleepStart = ExtractTimeOnly(request.SleepStart)?.ToTimeSpan(),
                SleepEnd = ExtractTimeOnly(request.SleepEnd)?.ToTimeSpan(),
                SleepType = sleepType,
                DeepSleep = request.DeepSleep,
                LightSleep = request.LightSleep,
                RemSleep = request.RemSleep,
                Awake = request.Awake,
                Wakeup = ExtractTimeOnly(request.Wakeup)?.ToTimeSpan(),
                IsFit1 = ToBool(request.IsFit1),
                IsFit2 = ToBool(request.IsFit2),
                IsFit3 = ToBool(request.IsFit3),
                Status = request.Status,
                MacAddress = request.MacAddress,
                RequestId = request.RequestId,
                UploadKey = uploadKey,
                RouteBase = routeBase,
                RetryCount = retryCount
            }, tx);

            return existingId.Value;
        }

        return await conn.QuerySingleAsync<int>(@"
INSERT INTO public.tbl_t_summary
(company_id, department_id, employee_id, shift_id, device_id, send_date, send_time,
 device_time, app_version, active, active_text, steps, steps_text, distance, distance_text,
 calories, calories_text, heart_rate, heart_rate_text, spo2, spo2_text, stress, stress_text,
 sleep, sleep_text, sleep_start, sleep_end, sleep_type, deep_sleep, light_sleep, rem_sleep,
 awake, wakeup, is_fit1, is_fit2, is_fit3, status, mac_address, request_id, upload_key,
 route_base, retry_count, upload_status, created_at, updated_at)
VALUES
(@CompanyId, @DepartmentId, @EmployeeId, @ShiftId, @DeviceId, @SendDate, @SendTime,
 @DeviceTime, @AppVersion, @Active, @ActiveText, @Steps, @StepsText, @Distance, @DistanceText,
 @Calories, @CaloriesText, @HeartRate, @HeartRateText, @Spo2, @Spo2Text, @Stress, @StressText,
 @Sleep, @SleepText, @SleepStart, @SleepEnd, @SleepType, @DeepSleep, @LightSleep, @RemSleep,
 @Awake, @Wakeup, @IsFit1, @IsFit2, @IsFit3, @Status, @MacAddress, @RequestId, @UploadKey,
 @RouteBase, @RetryCount, 'accepted', now(), now())
RETURNING id", new
        {
            CompanyId = companyId,
            DepartmentId = departmentId,
            EmployeeId = employeeId,
            ShiftId = request.ShiftId,
            DeviceId = deviceId,
            SendDate = sendDate,
            SendTime = now.TimeOfDay,
            DeviceTime = request.DeviceTime,
            AppVersion = LimitText(request.AppVersion, 64),
            Active = ToSafeInt(request.Active),
            ActiveText = request.ActiveText,
            Steps = ToSafeInt(request.Steps),
            StepsText = request.StepsText,
            Distance = request.Distance ?? 0m,
            DistanceText = request.DistanceText,
            Calories = request.Calories ?? 0m,
            CaloriesText = request.CaloriesText,
            HeartRate = ToSafeInt(request.HeartRate),
            HeartRateText = request.HeartRateText,
            Spo2 = ToSafeInt(request.Spo2),
            Spo2Text = request.Spo2Text,
            Stress = ToSafeInt(request.Stress),
            StressText = request.StressText,
            Sleep = request.Sleep ?? 0m,
            SleepText = request.SleepText,
            SleepStart = ExtractTimeOnly(request.SleepStart)?.ToTimeSpan(),
            SleepEnd = ExtractTimeOnly(request.SleepEnd)?.ToTimeSpan(),
            SleepType = sleepType,
            DeepSleep = request.DeepSleep,
            LightSleep = request.LightSleep,
            RemSleep = request.RemSleep,
            Awake = request.Awake,
            Wakeup = ExtractTimeOnly(request.Wakeup)?.ToTimeSpan(),
            IsFit1 = ToBool(request.IsFit1),
            IsFit2 = ToBool(request.IsFit2),
            IsFit3 = ToBool(request.IsFit3),
            Status = request.Status,
            MacAddress = request.MacAddress,
            RequestId = request.RequestId,
            UploadKey = uploadKey,
            RouteBase = routeBase,
            RetryCount = retryCount
        }, tx);
    }

    private static async Task UpsertSummaryDetailAsync(
        NpgsqlConnection conn,
        NpgsqlTransaction tx,
        int? summaryId,
        int companyId,
        int? departmentId,
        int employeeId,
        int? shiftId,
        int deviceId,
        string uploadKey,
        DateOnly recordDate,
        DateTime? deviceTime,
        string macAddress,
        string? appVersion,
        string payloadHash,
        string source,
        string userActivity,
        string userSleep,
        string userStress,
        string? userRespiratoryRate,
        string? userPai,
        string userSpo2,
        string? userTemperature,
        string? userCycling,
        string? userWeight,
        string? userHeartRateMax,
        string? userHeartRateResting,
        string? userHeartRateManual,
        string? userHrvSummary,
        string? userHrvValue,
        string? userBodyEnergy,
        bool storeRawPayload)
    {
        var existingId = await conn.QuerySingleOrDefaultAsync<long?>(
            "SELECT id FROM public.tbl_t_summary_detail WHERE upload_key=@UploadKey LIMIT 1",
            new { UploadKey = uploadKey },
            tx);

        var args = new
        {
            Id = existingId,
            SummaryId = summaryId,
            CompanyId = companyId,
            DepartmentId = departmentId,
            EmployeeId = employeeId,
            ShiftId = shiftId,
            DeviceId = deviceId,
            UploadKey = uploadKey,
            RecordDate = recordDate.ToDateTime(TimeOnly.MinValue).Date,
            DeviceTime = deviceTime,
            MacAddress = macAddress,
            AppVersion = appVersion,
            PayloadHash = payloadHash,
            Source = source,
            UserActivity = PrepareDbJsonPayload(userActivity, storeRawPayload),
            UserSleep = PrepareDbJsonPayload(userSleep, storeRawPayload),
            UserStress = PrepareDbJsonPayload(userStress, storeRawPayload),
            UserRespiratoryRate = PrepareDbJsonPayload(userRespiratoryRate, storeRawPayload),
            UserPai = PrepareDbJsonPayload(userPai, storeRawPayload),
            UserSpo2 = PrepareDbJsonPayload(userSpo2, storeRawPayload),
            UserTemperature = PrepareDbJsonPayload(userTemperature, storeRawPayload),
            UserCycling = PrepareDbJsonPayload(userCycling, storeRawPayload),
            UserWeight = PrepareDbJsonPayload(userWeight, storeRawPayload),
            UserHeartRateMax = PrepareDbJsonPayload(userHeartRateMax, storeRawPayload),
            UserHeartRateResting = PrepareDbJsonPayload(userHeartRateResting, storeRawPayload),
            UserHeartRateManual = PrepareDbJsonPayload(userHeartRateManual, storeRawPayload),
            UserHrvSummary = PrepareDbJsonPayload(userHrvSummary, storeRawPayload),
            UserHrvValue = PrepareDbJsonPayload(userHrvValue, storeRawPayload),
            UserBodyEnergy = PrepareDbJsonPayload(userBodyEnergy, storeRawPayload)
        };

        if (existingId.HasValue)
        {
            await conn.ExecuteAsync(@"
UPDATE public.tbl_t_summary_detail
SET summary_id=@SummaryId,
    company_id=@CompanyId,
    department_id=@DepartmentId,
    employee_id=@EmployeeId,
    shift_id=@ShiftId,
    device_id=@DeviceId,
    record_date=@RecordDate,
    device_time=@DeviceTime,
    mac_address=@MacAddress,
    app_version=@AppVersion,
    payload_hash=@PayloadHash,
    source=@Source,
    user_activity=@UserActivity::jsonb,
    user_sleep=@UserSleep::jsonb,
    user_stress=@UserStress::jsonb,
    user_respiratory_rate=@UserRespiratoryRate::jsonb,
    user_pai=@UserPai::jsonb,
    user_spo2=@UserSpo2::jsonb,
    user_temperature=@UserTemperature::jsonb,
    user_cycling=@UserCycling::jsonb,
    user_weight=@UserWeight::jsonb,
    user_heart_rate_max=@UserHeartRateMax::jsonb,
    user_heart_rate_resting=@UserHeartRateResting::jsonb,
    user_heart_rate_manual=@UserHeartRateManual::jsonb,
    user_hrv_summary=@UserHrvSummary::jsonb,
    user_hrv_value=@UserHrvValue::jsonb,
    user_body_energy=@UserBodyEnergy::jsonb,
    updated_at=now()
WHERE id=@Id", args, tx);
            return;
        }

        await conn.ExecuteAsync(@"
INSERT INTO public.tbl_t_summary_detail
(summary_id, company_id, department_id, employee_id, shift_id, device_id, upload_key,
 record_date, device_time, mac_address, app_version, payload_hash, source,
 user_activity, user_sleep, user_stress, user_respiratory_rate, user_pai, user_spo2,
 user_temperature, user_cycling, user_weight, user_heart_rate_max, user_heart_rate_resting,
 user_heart_rate_manual, user_hrv_summary, user_hrv_value, user_body_energy, created_at, updated_at)
VALUES
(@SummaryId, @CompanyId, @DepartmentId, @EmployeeId, @ShiftId, @DeviceId, @UploadKey,
 @RecordDate, @DeviceTime, @MacAddress, @AppVersion, @PayloadHash, @Source,
 @UserActivity::jsonb, @UserSleep::jsonb, @UserStress::jsonb, @UserRespiratoryRate::jsonb, @UserPai::jsonb, @UserSpo2::jsonb,
 @UserTemperature::jsonb, @UserCycling::jsonb, @UserWeight::jsonb, @UserHeartRateMax::jsonb, @UserHeartRateResting::jsonb,
 @UserHeartRateManual::jsonb, @UserHrvSummary::jsonb, @UserHrvValue::jsonb, @UserBodyEnergy::jsonb, now(), now())", args, tx);
    }
}
