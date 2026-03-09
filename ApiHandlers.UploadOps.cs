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
        FileWriterQueue fileQueue,
        ILoggerFactory loggerFactory)
    {
        var traceId = EnsureTraceId(context);
        var watch = Stopwatch.StartNew();
        var logger = loggerFactory.CreateLogger("UploadSummary");
        var currentStep = "start";

        logger.LogInformation(
            "SUMMARY start traceId={TraceId} uploadKey={UploadKey} employeeId={EmployeeId} macAddress={MacAddress} deviceTime={DeviceTime}",
            traceId, request.UploadKey, request.EmployeeId, request.MacAddress, request.DeviceTime);

        currentStep = "authenticate";
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            logger.LogWarning("SUMMARY unauthenticated traceId={TraceId}", traceId);
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        currentStep = "validate_request";
        var validationError = ValidateSummaryRequest(request);
        if (!string.IsNullOrWhiteSpace(validationError))
        {
            logger.LogWarning("SUMMARY validation_failed traceId={TraceId} message={Message}", traceId, validationError);
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, validationError);
        }

        var companyId = auth.CompanyId;
        logger.LogInformation("SUMMARY auth_ok traceId={TraceId} companyId={CompanyId} userId={UserId}", traceId, companyId, auth.UserId);

        currentStep = "resolve_device";
        var device = await ResolveDeviceByMacAsync(db, companyId, request.MacAddress!);
        if (device is null)
        {
            logger.LogWarning("SUMMARY device_not_found traceId={TraceId} companyId={CompanyId} macAddress={MacAddress}", traceId, companyId, request.MacAddress);
            return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");
        }
        logger.LogInformation("SUMMARY device_ok traceId={TraceId} deviceId={DeviceId}", traceId, device.Id);

        currentStep = "resolve_employee";
        var employee = await ResolveEmployeeByIdAsync(db, companyId, request.EmployeeId);
        if (employee is null)
        {
            logger.LogWarning("SUMMARY employee_not_found traceId={TraceId} companyId={CompanyId} employeeId={EmployeeId}", traceId, companyId, request.EmployeeId);
            return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");
        }
        logger.LogInformation("SUMMARY employee_ok traceId={TraceId} employeeId={EmployeeId} departmentId={DepartmentId} shiftId={ShiftId}",
            traceId, employee.Id, employee.DepartmentId, employee.ShiftId);

        currentStep = "resolve_department";
        var departmentId = request.DepartmentId > 0 ? request.DepartmentId : employee.DepartmentId.GetValueOrDefault();
        if (departmentId <= 0)
        {
            logger.LogWarning("SUMMARY department_not_found traceId={TraceId} requestDepartmentId={RequestDepartmentId} employeeDepartmentId={EmployeeDepartmentId}",
                traceId, request.DepartmentId, employee.DepartmentId);
            return ErrorMessage(StatusCodes.Status404NotFound, "Department not found.");
        }

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

        logger.LogInformation(
            "SUMMARY prepared traceId={TraceId} requestKey={RequestKey} recordDate={RecordDate} routeBase={RouteBase} retryCount={RetryCount}",
            traceId, requestKey, recordDate, routeBase, retryCount);

        int summaryId;
        try
        {
            summaryId = await ExecuteWithDbRetryAsync(async () =>
            {
                currentStep = "db_open_connection";
                await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
                currentStep = "db_begin_transaction";
                await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

                currentStep = "db_upsert_summary";
                var localSummaryId = await UpsertSummaryAsync(conn, tx, companyId, departmentId, device.Id, request, employee.Id, DateTime.Now, routeBase, retryCount);
                logger.LogInformation("SUMMARY upsert_summary_ok traceId={TraceId} summaryId={SummaryId}", traceId, localSummaryId);

                currentStep = "db_upsert_summary_detail";
                await UpsertSummaryDetailAsync(
                    conn, tx, localSummaryId, companyId, departmentId, employee.Id, request.ShiftId, device.Id,
                    requestKey, recordDate, deviceTime, request.MacAddress!, request.AppVersion,
                    ComputeSha256($"{request.EmployeeId}|{request.DeviceTime}|{payload.Activity}|{payload.Sleep}|{payload.Stress}|{payload.Spo2}"),
                    "summary",
                    payload.Activity, payload.Sleep, payload.Stress, null, null, payload.Spo2,
                    null, null, null, null, null, null, null, null, null
                );
                logger.LogInformation("SUMMARY upsert_detail_ok traceId={TraceId} uploadKey={UploadKey}", traceId, requestKey);

                currentStep = "db_commit";
                await tx.CommitAsync(context.RequestAborted);
                logger.LogInformation("SUMMARY commit_ok traceId={TraceId} summaryId={SummaryId}", traceId, localSummaryId);
                return localSummaryId;
            }, context.RequestAborted);
        }
        catch (Exception ex)
        {
            watch.Stop();
            logger.LogError(ex, "SUMMARY failed traceId={TraceId} step={Step} requestKey={RequestKey} companyId={CompanyId} employeeId={EmployeeId} deviceId={DeviceId}",
                traceId, currentStep, requestKey, companyId, employee.Id, device.Id);
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
        currentStep = "sideeffect_enqueue";
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
        currentStep = "sideeffect_network_probe";
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
        currentStep = "sideeffect_google_activity";
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
        logger.LogInformation("SUMMARY success traceId={TraceId} summaryId={SummaryId} requestKey={RequestKey} warnings={WarningCount} durationMs={DurationMs}",
            traceId, summaryId, requestKey, sideEffectWarnings.Count, watch.ElapsedMilliseconds);

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
        FileWriterQueue fileQueue,
        ILoggerFactory loggerFactory)
    {
        var traceId = EnsureTraceId(context);
        var watch = Stopwatch.StartNew();
        var logger = loggerFactory.CreateLogger("UploadDetail");
        var currentStep = "start";

        logger.LogInformation(
            "DETAIL start traceId={TraceId} uploadKey={UploadKey} employeeId={EmployeeId} macAddress={MacAddress} deviceTime={DeviceTime}",
            traceId, request.UploadKey, request.EmployeeId, request.MacAddress, request.DeviceTime);

        currentStep = "authenticate";
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            logger.LogWarning("DETAIL unauthenticated traceId={TraceId}", traceId);
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }
        if (string.IsNullOrWhiteSpace(request.DeviceTime))
        {
            logger.LogWarning("DETAIL validation_failed traceId={TraceId} message=device_time is required", traceId);
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "device_time is required");
        }
        if (string.IsNullOrWhiteSpace(request.MacAddress))
        {
            logger.LogWarning("DETAIL validation_failed traceId={TraceId} message=mac_address is required", traceId);
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "mac_address is required");
        }
        if (request.EmployeeId <= 0)
        {
            logger.LogWarning("DETAIL validation_failed traceId={TraceId} message=employee_id is required", traceId);
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "employee_id is required");
        }

        var companyId = auth.CompanyId;
        logger.LogInformation("DETAIL auth_ok traceId={TraceId} companyId={CompanyId} userId={UserId}", traceId, companyId, auth.UserId);

        currentStep = "resolve_device";
        var device = await ResolveDeviceByMacAsync(db, companyId, request.MacAddress);
        if (device is null)
        {
            logger.LogWarning("DETAIL device_not_found traceId={TraceId} companyId={CompanyId} macAddress={MacAddress}", traceId, companyId, request.MacAddress);
            return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");
        }
        logger.LogInformation("DETAIL device_ok traceId={TraceId} deviceId={DeviceId}", traceId, device.Id);

        currentStep = "resolve_employee";
        var employee = await ResolveEmployeeByIdAsync(db, companyId, request.EmployeeId);
        if (employee is null)
        {
            logger.LogWarning("DETAIL employee_not_found traceId={TraceId} companyId={CompanyId} employeeId={EmployeeId}", traceId, companyId, request.EmployeeId);
            return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");
        }
        logger.LogInformation("DETAIL employee_ok traceId={TraceId} employeeId={EmployeeId} departmentId={DepartmentId} shiftId={ShiftId}",
            traceId, employee.Id, employee.DepartmentId, employee.ShiftId);

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

        logger.LogInformation(
            "DETAIL prepared traceId={TraceId} requestKey={RequestKey} recordDate={RecordDate} routeBase={RouteBase} retryCount={RetryCount}",
            traceId, requestKey, recordDate, routeBase, retryCount);

        int? summaryId;
        try
        {
            summaryId = await ExecuteWithDbRetryAsync<int?>(async () =>
            {
                currentStep = "db_open_connection";
                await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
                currentStep = "db_begin_transaction";
                await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

                currentStep = "db_find_summary";
                var localSummaryId = await FindSummaryIdByEmployeeDateAsync(conn, tx, companyId, employee.Id, recordDate);
                logger.LogInformation("DETAIL find_summary_ok traceId={TraceId} summaryId={SummaryId}", traceId, localSummaryId);

                currentStep = "db_upsert_summary_detail";
                await UpsertSummaryDetailAsync(
                    conn, tx, localSummaryId, companyId, employee.DepartmentId, employee.Id, employee.ShiftId, device.Id,
                    requestKey, recordDate, deviceTime, request.MacAddress, request.AppVersion,
                    ComputeSha256($"{request.EmployeeId}|{request.DeviceTime}|{payload.Activity}|{payload.Sleep}|{payload.Stress}|{payload.Spo2}|{payload.HeartRateMax}|{payload.HeartRateResting}|{payload.HeartRateManual}"),
                    "detail",
                    payload.Activity, payload.Sleep, payload.Stress, payload.RespiratoryRate, payload.Pai, payload.Spo2,
                    payload.Temperature, payload.Cycling, payload.Weight, payload.HeartRateMax, payload.HeartRateResting,
                    payload.HeartRateManual, payload.HrvSummary, payload.HrvValue, payload.BodyEnergy
                );
                logger.LogInformation("DETAIL upsert_detail_ok traceId={TraceId} uploadKey={UploadKey}", traceId, requestKey);

                currentStep = "db_commit";
                await tx.CommitAsync(context.RequestAborted);
                logger.LogInformation("DETAIL commit_ok traceId={TraceId} summaryId={SummaryId}", traceId, localSummaryId);
                return localSummaryId;
            }, context.RequestAborted);
        }
        catch (Exception ex)
        {
            watch.Stop();
            logger.LogError(ex, "DETAIL failed traceId={TraceId} step={Step} requestKey={RequestKey} companyId={CompanyId} employeeId={EmployeeId} deviceId={DeviceId}",
                traceId, currentStep, requestKey, companyId, employee.Id, device.Id);
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
        currentStep = "sideeffect_enqueue";
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
        currentStep = "sideeffect_network_probe";
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
        currentStep = "sideeffect_google_activity";
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
        logger.LogInformation("DETAIL success traceId={TraceId} summaryId={SummaryId} requestKey={RequestKey} warnings={WarningCount} durationMs={DurationMs}",
            traceId, summaryId, requestKey, sideEffectWarnings.Count, watch.ElapsedMilliseconds);

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
