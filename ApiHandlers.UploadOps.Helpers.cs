using System.Globalization;
using System.Net.Sockets;
using System.Text.Json;
using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    private sealed record SummaryPayload(string Activity, string Sleep, string Stress, string Spo2);

    private sealed record DetailPayload(
        string Activity,
        string Sleep,
        string Stress,
        string RespiratoryRate,
        string Pai,
        string Spo2,
        string Temperature,
        string Cycling,
        string Weight,
        string HeartRateMax,
        string HeartRateResting,
        string HeartRateManual,
        string HrvSummary,
        string HrvValue,
        string BodyEnergy
    );

    private sealed record NetworkState(
        string? Transport,
        string? Quality,
        bool? IsNetworkAvailable,
        bool? IsApiReachable,
        bool? IsApiSlow,
        int? LatencyMs,
        string? ApiBase,
        string? ApiEndpoint,
        string? Note
    );

    private static SummaryPayload NormalizeSummaryPayload(SummaryRequest request)
        => new(
            NormalizeJsonPayload(request.UserActivity),
            NormalizeJsonPayload(request.UserSleep),
            NormalizeJsonPayload(request.UserStress),
            NormalizeJsonPayload(request.UserSpo2)
        );

    private static DetailPayload NormalizeDetailPayload(DetailRequest request)
        => new(
            NormalizeJsonPayload(request.UserActivity),
            NormalizeJsonPayload(request.UserSleep),
            NormalizeJsonPayload(request.UserStress),
            NormalizeJsonPayload(request.UserRespiratoryRate),
            NormalizeJsonPayload(request.UserPai),
            NormalizeJsonPayload(request.UserSpo2),
            NormalizeJsonPayload(request.UserTemperature),
            NormalizeJsonPayload(request.UserCycling),
            NormalizeJsonPayload(request.UserWeight),
            NormalizeJsonPayload(request.UserHeartRateMax),
            NormalizeJsonPayload(request.UserHeartRateResting),
            NormalizeJsonPayload(request.UserHeartRateManual),
            NormalizeJsonPayload(request.UserHrvSummary),
            NormalizeJsonPayload(request.UserHrvValue),
            NormalizeJsonPayload(request.UserBodyEnergy)
        );

    private static string ResolveRequestKey(string? uploadKey, string? requestId, string? idempotencyKey)
    {
        if (!string.IsNullOrWhiteSpace(uploadKey))
        {
            return uploadKey.Trim();
        }

        if (!string.IsNullOrWhiteSpace(requestId))
        {
            return requestId.Trim();
        }

        if (!string.IsNullOrWhiteSpace(idempotencyKey))
        {
            return idempotencyKey.Trim();
        }

        return Guid.NewGuid().ToString("N");
    }

    private static int ParseRetryCount(HttpContext context)
    {
        var value = context.Request.Headers["X-Retry-Count"].FirstOrDefault();
        if (int.TryParse(value, NumberStyles.Integer, CultureInfo.InvariantCulture, out var parsed) && parsed >= 0)
        {
            return parsed;
        }

        return 0;
    }

    private static NetworkState ResolveNetworkState(
        string? transport,
        string? quality,
        bool? isNetworkAvailable,
        bool? isApiReachable,
        bool? isApiSlow,
        int? latencyMs,
        string? apiBase,
        string? apiEndpoint,
        string? note,
        Dictionary<string, JsonElement>? extra)
    {
        return new NetworkState(
            transport ?? GetExtraString(extra, "network_transport"),
            quality ?? GetExtraString(extra, "network_quality"),
            isNetworkAvailable ?? GetExtraBool(extra, "is_network_available"),
            isApiReachable ?? GetExtraBool(extra, "is_api_reachable"),
            isApiSlow ?? GetExtraBool(extra, "is_api_slow"),
            latencyMs ?? GetExtraInt(extra, "latency_ms"),
            apiBase ?? GetExtraString(extra, "api_base"),
            apiEndpoint ?? GetExtraString(extra, "api_endpoint"),
            note ?? GetExtraString(extra, "note")
        );
    }

    private static UploadLogInput BuildUploadLog(
        string traceId,
        string requestType,
        string endpoint,
        string? routeUrl,
        string? routeBase,
        string? requestKey,
        int statusCode,
        int durationMs,
        int retryCount,
        string? errorType,
        string? errorMessage,
        string? note,
        int? companyId,
        int? departmentId,
        int? employeeId,
        int? deviceId,
        string? macAddress,
        string? appVersion,
        string? networkTransport,
        string? networkQuality,
        bool? isApiReachable,
        bool? isApiSlow,
        int? payloadSize)
    {
        return new UploadLogInput
        {
            TraceId = traceId,
            RequestType = requestType,
            Endpoint = endpoint,
            RouteUrl = routeUrl,
            RouteBase = routeBase,
            RequestKey = requestKey,
            StatusCode = statusCode,
            DurationMs = durationMs,
            Attempts = Math.Max(1, retryCount + 1),
            ErrorType = errorType,
            ErrorMessage = errorMessage,
            Note = note,
            CompanyId = companyId,
            DepartmentId = departmentId,
            EmployeeId = employeeId,
            DeviceId = deviceId,
            MacAddress = macAddress,
            AppVersion = appVersion,
            NetworkTransport = networkTransport,
            NetworkQuality = networkQuality,
            IsApiReachable = isApiReachable,
            IsApiSlow = isApiSlow,
            PayloadSize = payloadSize
        };
    }

    private static async Task SafeInsertLogAsync(NpgsqlDataSource db, UploadLogInput input)
    {
        try
        {
            await InsertUploadLogAsync(db, input);
        }
        catch
        {
            // Prevent logging failure from breaking upload flow.
        }
    }

    private static async Task TrySideEffectAsync(
        Func<Task> action,
        Action<Exception>? onError,
        Func<Exception, Task>? onErrorAsync)
    {
        try
        {
            await action();
        }
        catch (Exception ex)
        {
            onError?.Invoke(ex);
            if (onErrorAsync is not null)
            {
                try
                {
                    await onErrorAsync(ex);
                }
                catch
                {
                    // Ignore secondary failures from side-effect logging.
                }
            }
        }
    }

    private static bool IsTransientDbException(Exception ex)
    {
        if (ex is OperationCanceledException)
        {
            return false;
        }

        if (ex is NpgsqlException npgEx && npgEx.IsTransient)
        {
            return true;
        }

        if (ex is TimeoutException || ex is IOException || ex is SocketException)
        {
            return true;
        }

        return ex.InnerException is not null && IsTransientDbException(ex.InnerException);
    }

    private static async Task<T> ExecuteWithDbRetryAsync<T>(
        Func<Task<T>> action,
        CancellationToken cancellationToken,
        int maxRetries = 2)
    {
        for (var attempt = 0; ; attempt++)
        {
            cancellationToken.ThrowIfCancellationRequested();
            try
            {
                return await action();
            }
            catch (Exception ex) when (attempt < maxRetries && IsTransientDbException(ex) && !cancellationToken.IsCancellationRequested)
            {
                var delayMs = (150 * (attempt + 1)) + Random.Shared.Next(40, 180);
                await Task.Delay(delayMs, cancellationToken);
            }
        }
    }

    private static async Task EnqueueSummaryFiles(
        FileWriterQueue queue,
        CancellationToken cancellationToken,
        int employeeId,
        string requestKey,
        DateOnly recordDate,
        string activity,
        string sleep,
        string stress,
        string spo2)
    {
        var tasks = new[]
        {
            new UploadFileTask(BuildMetricRelativePath("data_activity", recordDate, employeeId, requestKey), activity, "summary", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_sleep", recordDate, employeeId, requestKey), sleep, "summary", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_stress", recordDate, employeeId, requestKey), stress, "summary", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_spo2", recordDate, employeeId, requestKey), spo2, "summary", requestKey, employeeId, recordDate)
        };

        foreach (var item in tasks)
        {
            await queue.EnqueueAsync(item, cancellationToken);
        }
    }

    private static async Task EnqueueDetailFiles(
        FileWriterQueue queue,
        CancellationToken cancellationToken,
        int employeeId,
        string requestKey,
        DateOnly recordDate,
        DetailPayload payload)
    {
        var tasks = new[]
        {
            new UploadFileTask(BuildMetricRelativePath("data_activity", recordDate, employeeId, requestKey), payload.Activity, "detail", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_sleep", recordDate, employeeId, requestKey), payload.Sleep, "detail", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_stress", recordDate, employeeId, requestKey), payload.Stress, "detail", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_spo2", recordDate, employeeId, requestKey), payload.Spo2, "detail", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_heart_rate_max", recordDate, employeeId, requestKey), payload.HeartRateMax, "detail", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_heart_rate_resting", recordDate, employeeId, requestKey), payload.HeartRateResting, "detail", requestKey, employeeId, recordDate),
            new UploadFileTask(BuildMetricRelativePath("data_heart_rate_manual", recordDate, employeeId, requestKey), payload.HeartRateManual, "detail", requestKey, employeeId, recordDate)
        };

        foreach (var item in tasks)
        {
            await queue.EnqueueAsync(item, cancellationToken);
        }
    }

    private static async Task MaybeInsertNetworkProbeAsync(
        NpgsqlDataSource db,
        int companyId,
        int employeeId,
        int? deviceId,
        string? macAddress,
        string? appVersion,
        NetworkState state,
        string traceId)
    {
        if (!state.IsNetworkAvailable.HasValue &&
            !state.IsApiReachable.HasValue &&
            !state.IsApiSlow.HasValue &&
            !state.LatencyMs.HasValue &&
            string.IsNullOrWhiteSpace(state.Transport) &&
            string.IsNullOrWhiteSpace(state.ApiBase) &&
            string.IsNullOrWhiteSpace(state.ApiEndpoint))
        {
            return;
        }

        await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_network_probe
(measured_at, company_id, employee_id, device_id, mac_address, app_version,
 network_transport, is_network_available, is_api_reachable, is_api_slow,
 latency_ms, api_base, api_endpoint, trace_id, note, created_at)
VALUES
(now(), @CompanyId, @EmployeeId, @DeviceId, @MacAddress, @AppVersion,
 @NetworkTransport, @IsNetworkAvailable, @IsApiReachable, @IsApiSlow,
 @LatencyMs, @ApiBase, @ApiEndpoint, @TraceId, @Note, now())", new
        {
            CompanyId = companyId,
            EmployeeId = employeeId,
            DeviceId = deviceId,
            MacAddress = macAddress,
            AppVersion = appVersion,
            NetworkTransport = state.Transport,
            IsNetworkAvailable = state.IsNetworkAvailable,
            IsApiReachable = state.IsApiReachable,
            IsApiSlow = state.IsApiSlow,
            LatencyMs = state.LatencyMs,
            ApiBase = state.ApiBase,
            ApiEndpoint = state.ApiEndpoint,
            TraceId = traceId,
            Note = state.Note
        });
    }

    private static async Task MaybeInsertGoogleActivityFromExtraAsync(
        NpgsqlDataSource db,
        int companyId,
        int employeeId,
        int? deviceId,
        string source,
        Dictionary<string, JsonElement>? extra)
    {
        if (extra is null)
        {
            return;
        }

        if (!extra.TryGetValue("google_activity", out var activities) &&
            !extra.TryGetValue("user_google_activity", out activities) &&
            !extra.TryGetValue("googleActivity", out activities))
        {
            return;
        }

        var items = new List<JsonElement>();
        if (activities.ValueKind == JsonValueKind.Array)
        {
            items.AddRange(activities.EnumerateArray());
        }
        else if (activities.ValueKind == JsonValueKind.Object)
        {
            items.Add(activities);
        }
        else if (activities.ValueKind == JsonValueKind.String)
        {
            try
            {
                using var doc = JsonDocument.Parse(activities.GetString() ?? "[]");
                if (doc.RootElement.ValueKind == JsonValueKind.Array)
                {
                    items.AddRange(doc.RootElement.EnumerateArray().Select(x => x.Clone()));
                }
                else if (doc.RootElement.ValueKind == JsonValueKind.Object)
                {
                    items.Add(doc.RootElement.Clone());
                }
            }
            catch
            {
                return;
            }
        }

        if (items.Count == 0)
        {
            return;
        }

        await using var conn = await db.OpenConnectionAsync();
        await using var tx = await conn.BeginTransactionAsync();

        foreach (var item in items)
        {
            var activityType = item.TryGetProperty("activity_type", out var v) ? v.ToString() :
                item.TryGetProperty("type", out v) ? v.ToString() : "unknown";

            short? confidence = null;
            if (item.TryGetProperty("confidence", out var c))
            {
                if (c.ValueKind == JsonValueKind.Number && c.TryGetInt16(out var n))
                {
                    confidence = n;
                }
                else if (c.ValueKind == JsonValueKind.String && short.TryParse(c.GetString(), out n))
                {
                    confidence = n;
                }
            }

            DateTime? activityTime = null;
            if (item.TryGetProperty("activity_time", out var t))
            {
                activityTime = ExtractDateTime(t.ToString());
            }
            else if (item.TryGetProperty("timestamp", out t))
            {
                activityTime = ExtractDateTime(t.ToString());
            }

            await conn.ExecuteAsync(@"
INSERT INTO public.tbl_t_google_activity
(activity_time, company_id, employee_id, device_id, activity_type, confidence, source, raw_payload, created_at)
VALUES
(@ActivityTime, @CompanyId, @EmployeeId, @DeviceId, @ActivityType, @Confidence, @Source, @RawPayload::jsonb, now())", new
            {
                ActivityTime = activityTime ?? DateTime.Now,
                CompanyId = companyId,
                EmployeeId = employeeId,
                DeviceId = deviceId,
                ActivityType = string.IsNullOrWhiteSpace(activityType) ? "unknown" : activityType,
                Confidence = confidence,
                Source = source,
                RawPayload = item.GetRawText()
            }, tx);
        }

        await tx.CommitAsync();
    }
}
