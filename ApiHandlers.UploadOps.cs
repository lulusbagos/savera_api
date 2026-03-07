using System.Diagnostics;
using System.Globalization;
using System.Text.Json;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> SummaryAsync(
        HttpContext context,
        SummaryRequest request,
        NpgsqlDataSource db,
        FileWriterQueue fileQueue)
    {
        var traceId = EnsureTraceId(context);
        var watch = Stopwatch.StartNew();

        var auth = await AuthenticateAsync(context, db);
        if (auth is null) return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");

        var validationError = ValidateSummaryRequest(request);
        if (!string.IsNullOrWhiteSpace(validationError))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, validationError);
        }

        var companyId = auth.CompanyId;

        var device = await ResolveDeviceByMacAsync(db, companyId, request.MacAddress!);
        if (device is null) return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");

        var employee = await ResolveEmployeeByIdAsync(db, companyId, request.EmployeeId);
        if (employee is null) return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");

        var departmentId = request.DepartmentId > 0 ? request.DepartmentId : employee.DepartmentId.GetValueOrDefault();
        if (departmentId <= 0) return ErrorMessage(StatusCodes.Status404NotFound, "Department not found.");

        var idempotencyKey = ResolveIdempotencyKey(context);
        var requestKey = ResolveRequestKey(request.UploadKey, request.RequestId, idempotencyKey);
        var recordDate = ExtractDateOnly(request.DeviceTime) ?? DateOnly.FromDateTime(DateTime.Now);
        var deviceTime = ExtractDateTime(request.DeviceTime);
        var routeBase = ResolveRouteBase(context, request.RouteBase);
        var retryCount = ParseRetryCount(context);
        var payload = NormalizeSummaryPayload(request);
        var net = ResolveNetworkState(
            request.NetworkTransport,
            request.NetworkQuality,
            request.IsNetworkAvailable,
            request.IsApiReachable,
            request.IsApiSlow,
            request.LatencyMs,
            request.ApiBase,
            request.ApiEndpoint,
            request.Note,
            request.Extra
        );

        int summaryId;
        try
        {
            summaryId = await ExecuteWithDbRetryAsync(async () =>
            {
                await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
                await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

                var localSummaryId = await UpsertSummaryAsync(conn, tx, companyId, departmentId, device.Id, request, employee.Id, DateTime.Now, routeBase, retryCount);

                await UpsertSummaryDetailAsync(
                    conn, tx, localSummaryId, companyId, departmentId, employee.Id, request.ShiftId, device.Id,
                    requestKey, recordDate, deviceTime, request.MacAddress!, request.AppVersion,
                    ComputeSha256($"{request.EmployeeId}|{request.DeviceTime}|{payload.Activity}|{payload.Sleep}|{payload.Stress}|{payload.Spo2}"),
                    "summary",
                    payload.Activity, payload.Sleep, payload.Stress, null, null, payload.Spo2,
                    null, null, null, null, null, null, null, null, null
                );

                await tx.CommitAsync(context.RequestAborted);
                return localSummaryId;
            }, context.RequestAborted);
        }
        catch (Exception ex)
        {
            watch.Stop();
            var wasCancelled = context.RequestAborted.IsCancellationRequested;
            var statusCode = wasCancelled ? 499 : StatusCodes.Status500InternalServerError;
            await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "summary", "/api/summary", context.Request.Path.Value, routeBase, requestKey,
                statusCode, (int)watch.ElapsedMilliseconds, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, departmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow,
                JsonSizeBytes(payload.Activity) + JsonSizeBytes(payload.Sleep) + JsonSizeBytes(payload.Stress) + JsonSizeBytes(payload.Spo2)
            ));
            if (wasCancelled)
            {
                return Results.Json(new { message = "Request cancelled by client", trace_id = traceId }, statusCode: statusCode);
            }

            return Results.Json(new { message = "Internal server error", trace_id = traceId }, statusCode: 500);
        }

        var sideEffectWarnings = new List<string>();
        await TrySideEffectAsync(
            async () => await EnqueueSummaryFiles(fileQueue, CancellationToken.None, employee.Id, requestKey, recordDate, payload.Activity, payload.Sleep, payload.Stress, payload.Spo2),
            ex => sideEffectWarnings.Add("enqueue:" + ex.GetType().Name),
            async ex => await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "summary_sideeffect_enqueue", "/api/summary", context.Request.Path.Value, routeBase, requestKey,
                500, 0, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, departmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ))
        );
        await TrySideEffectAsync(
            async () => await MaybeInsertNetworkProbeAsync(db, companyId, employee.Id, device.Id, request.MacAddress, request.AppVersion, net, traceId),
            ex => sideEffectWarnings.Add("network_probe:" + ex.GetType().Name),
            async ex => await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "summary_sideeffect_network", "/api/summary", context.Request.Path.Value, routeBase, requestKey,
                500, 0, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, departmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ))
        );
        await TrySideEffectAsync(
            async () => await MaybeInsertGoogleActivityFromExtraAsync(db, companyId, employee.Id, device.Id, "summary", request.Extra),
            ex => sideEffectWarnings.Add("google_activity:" + ex.GetType().Name),
            async ex => await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "summary_sideeffect_google", "/api/summary", context.Request.Path.Value, routeBase, requestKey,
                500, 0, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, departmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ))
        );

        watch.Stop();
        await SafeInsertLogAsync(db, BuildUploadLog(
            traceId, "summary", "/api/summary", context.Request.Path.Value, routeBase, requestKey,
            StatusCodes.Status200OK, (int)watch.ElapsedMilliseconds, retryCount, null, null,
            net.Note, companyId, departmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
            net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow,
            JsonSizeBytes(payload.Activity) + JsonSizeBytes(payload.Sleep) + JsonSizeBytes(payload.Stress) + JsonSizeBytes(payload.Spo2)
        ));

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                summary_id = summaryId,
                upload_key = requestKey,
                record_date = recordDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
                warning_count = sideEffectWarnings.Count
            }
        });
    }

    public static async Task<IResult> DetailAsync(
        HttpContext context,
        DetailRequest request,
        NpgsqlDataSource db,
        FileWriterQueue fileQueue)
    {
        var traceId = EnsureTraceId(context);
        var watch = Stopwatch.StartNew();

        var auth = await AuthenticateAsync(context, db);
        if (auth is null) return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        if (string.IsNullOrWhiteSpace(request.DeviceTime)) return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "device_time is required");
        if (string.IsNullOrWhiteSpace(request.MacAddress)) return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "mac_address is required");
        if (request.EmployeeId <= 0) return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "employee_id is required");

        var companyId = auth.CompanyId;

        var device = await ResolveDeviceByMacAsync(db, companyId, request.MacAddress);
        if (device is null) return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");

        var employee = await ResolveEmployeeByIdAsync(db, companyId, request.EmployeeId);
        if (employee is null) return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");

        var idempotencyKey = ResolveIdempotencyKey(context);
        var requestKey = ResolveRequestKey(request.UploadKey, null, idempotencyKey);
        var recordDate = ExtractDateOnly(request.DeviceTime) ?? DateOnly.FromDateTime(DateTime.Now);
        var deviceTime = ExtractDateTime(request.DeviceTime);
        var routeBase = ResolveRouteBase(context, request.RouteBase);
        var retryCount = ParseRetryCount(context);
        var payload = NormalizeDetailPayload(request);
        var net = ResolveNetworkState(
            request.NetworkTransport,
            request.NetworkQuality,
            request.IsNetworkAvailable,
            request.IsApiReachable,
            request.IsApiSlow,
            request.LatencyMs,
            request.ApiBase,
            request.ApiEndpoint,
            request.Note,
            request.Extra
        );

        int? summaryId;
        try
        {
            summaryId = await ExecuteWithDbRetryAsync<int?>(async () =>
            {
                await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
                await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

                var localSummaryId = await FindSummaryIdByEmployeeDateAsync(conn, tx, companyId, employee.Id, recordDate);

                await UpsertSummaryDetailAsync(
                    conn, tx, localSummaryId, companyId, employee.DepartmentId, employee.Id, employee.ShiftId, device.Id,
                    requestKey, recordDate, deviceTime, request.MacAddress, request.AppVersion,
                    ComputeSha256($"{request.EmployeeId}|{request.DeviceTime}|{payload.Activity}|{payload.Sleep}|{payload.Stress}|{payload.Spo2}|{payload.HeartRateMax}|{payload.HeartRateResting}|{payload.HeartRateManual}"),
                    "detail",
                    payload.Activity, payload.Sleep, payload.Stress, payload.RespiratoryRate, payload.Pai, payload.Spo2,
                    payload.Temperature, payload.Cycling, payload.Weight, payload.HeartRateMax, payload.HeartRateResting,
                    payload.HeartRateManual, payload.HrvSummary, payload.HrvValue, payload.BodyEnergy
                );

                await tx.CommitAsync(context.RequestAborted);
                return localSummaryId;
            }, context.RequestAborted);
        }
        catch (Exception ex)
        {
            watch.Stop();
            var wasCancelled = context.RequestAborted.IsCancellationRequested;
            var statusCode = wasCancelled ? 499 : StatusCodes.Status500InternalServerError;
            await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "detail", "/api/detail", context.Request.Path.Value, routeBase, requestKey,
                statusCode, (int)watch.ElapsedMilliseconds, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, employee.DepartmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ));
            if (wasCancelled)
            {
                return Results.Json(new { message = "Request cancelled by client", trace_id = traceId }, statusCode: statusCode);
            }

            return Results.Json(new { message = "Internal server error", trace_id = traceId }, statusCode: 500);
        }

        var sideEffectWarnings = new List<string>();
        await TrySideEffectAsync(
            async () => await EnqueueDetailFiles(fileQueue, CancellationToken.None, employee.Id, requestKey, recordDate, payload),
            ex => sideEffectWarnings.Add("enqueue:" + ex.GetType().Name),
            async ex => await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "detail_sideeffect_enqueue", "/api/detail", context.Request.Path.Value, routeBase, requestKey,
                500, 0, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, employee.DepartmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ))
        );
        await TrySideEffectAsync(
            async () => await MaybeInsertNetworkProbeAsync(db, companyId, employee.Id, device.Id, request.MacAddress, request.AppVersion, net, traceId),
            ex => sideEffectWarnings.Add("network_probe:" + ex.GetType().Name),
            async ex => await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "detail_sideeffect_network", "/api/detail", context.Request.Path.Value, routeBase, requestKey,
                500, 0, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, employee.DepartmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ))
        );
        await TrySideEffectAsync(
            async () => await MaybeInsertGoogleActivityFromExtraAsync(db, companyId, employee.Id, device.Id, "detail", request.Extra),
            ex => sideEffectWarnings.Add("google_activity:" + ex.GetType().Name),
            async ex => await SafeInsertLogAsync(db, BuildUploadLog(
                traceId, "detail_sideeffect_google", "/api/detail", context.Request.Path.Value, routeBase, requestKey,
                500, 0, retryCount, ex.GetType().Name, ex.Message,
                net.Note, companyId, employee.DepartmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
                net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
            ))
        );

        watch.Stop();
        await SafeInsertLogAsync(db, BuildUploadLog(
            traceId, "detail", "/api/detail", context.Request.Path.Value, routeBase, requestKey,
            StatusCodes.Status200OK, (int)watch.ElapsedMilliseconds, retryCount, null, null,
            net.Note, companyId, employee.DepartmentId, employee.Id, device.Id, request.MacAddress, request.AppVersion,
            net.Transport, net.Quality, net.IsApiReachable, net.IsApiSlow, null
        ));

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                summary_id = summaryId,
                upload_key = requestKey,
                record_date = recordDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
                warning_count = sideEffectWarnings.Count
            }
        });
    }
}
