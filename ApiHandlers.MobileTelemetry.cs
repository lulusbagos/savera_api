using System.Globalization;
using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> NetworkProbeAsync(HttpContext context, NetworkProbeRequest request, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        int? employeeId = request.EmployeeId;
        int? deviceId = request.DeviceId;

        if (!employeeId.HasValue || employeeId.Value <= 0)
        {
            employeeId = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT e.id
FROM public.tbl_r_employee e
WHERE e.deleted_at IS NULL
  AND e.company_id=@CompanyId
  AND e.user_id=@UserId
LIMIT 1", new
            {
                CompanyId = company.Id,
                UserId = auth.UserId
            });
        }

        if (employeeId.HasValue && employeeId.Value > 0 && (!deviceId.HasValue || deviceId.Value <= 0))
        {
            deviceId = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT device_id
FROM public.tbl_r_employee
WHERE id=@EmployeeId
  AND deleted_at IS NULL
LIMIT 1", new { EmployeeId = employeeId.Value });
        }

        var idempotencyKey = ResolveIdempotencyKey(context);
        var traceId = !string.IsNullOrWhiteSpace(request.TraceId)
            ? request.TraceId!.Trim()
            : (!string.IsNullOrWhiteSpace(idempotencyKey) ? idempotencyKey! : EnsureTraceId(context));

        if (!string.IsNullOrWhiteSpace(traceId))
        {
            var alreadyInserted = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT 1
FROM public.tbl_t_network_probe
WHERE company_id=@CompanyId
  AND trace_id=@TraceId
LIMIT 1", new
            {
                CompanyId = company.Id,
                TraceId = traceId
            });

            if (alreadyInserted.HasValue)
            {
                return Results.Ok(new
                {
                    message = "Already processed",
                    duplicate = true,
                    data = new
                    {
                        company_id = company.Id,
                        employee_id = employeeId,
                        device_id = deviceId
                    }
                });
            }
        }

        await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_network_probe
(measured_at, company_id, employee_id, device_id, mac_address, app_version,
 network_transport, is_network_available, is_api_reachable, is_api_slow,
 latency_ms, api_base, api_endpoint, trace_id, note, created_at)
VALUES
(@MeasuredAt, @CompanyId, @EmployeeId, @DeviceId, @MacAddress, @AppVersion,
 @NetworkTransport, @IsNetworkAvailable, @IsApiReachable, @IsApiSlow,
 @LatencyMs, @ApiBase, @ApiEndpoint, @TraceId, @Note, now())", new
        {
            MeasuredAt = ExtractDateTime(request.MeasuredAt) ?? DateTime.Now,
            CompanyId = company.Id,
            EmployeeId = employeeId,
            DeviceId = deviceId,
            MacAddress = request.MacAddress,
            AppVersion = request.AppVersion,
            NetworkTransport = request.NetworkTransport,
            IsNetworkAvailable = request.IsNetworkAvailable,
            IsApiReachable = request.IsApiReachable,
            IsApiSlow = request.IsApiSlow,
            LatencyMs = request.LatencyMs,
            ApiBase = request.ApiBase,
            ApiEndpoint = request.ApiEndpoint,
            TraceId = traceId,
            Note = request.Note
        });

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                company_id = company.Id,
                employee_id = employeeId,
                device_id = deviceId
            }
        });
    }

    public static async Task<IResult> GoogleActivityAsync(HttpContext context, GoogleActivityRequest request, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        if (request.Activities is null || request.Activities.Count == 0)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "activities is required");
        }

        int? employeeId = request.EmployeeId;
        int? deviceId = request.DeviceId;

        if (!employeeId.HasValue || employeeId.Value <= 0)
        {
            employeeId = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT e.id
FROM public.tbl_r_employee e
WHERE e.deleted_at IS NULL
  AND e.company_id=@CompanyId
  AND e.user_id=@UserId
LIMIT 1", new
            {
                CompanyId = company.Id,
                UserId = auth.UserId
            });
        }

        if (employeeId.HasValue && employeeId.Value > 0 && (!deviceId.HasValue || deviceId.Value <= 0))
        {
            deviceId = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT device_id
FROM public.tbl_r_employee
WHERE id=@EmployeeId
  AND deleted_at IS NULL
LIMIT 1", new { EmployeeId = employeeId.Value });
        }

        await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
        await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

        foreach (var item in request.Activities)
        {
            await conn.ExecuteAsync(@"
INSERT INTO public.tbl_t_google_activity
(activity_time, company_id, employee_id, device_id, activity_type, confidence, source, raw_payload, created_at)
VALUES
(@ActivityTime, @CompanyId, @EmployeeId, @DeviceId, @ActivityType, @Confidence, @Source, @RawPayload::jsonb, now())", new
            {
                ActivityTime = ExtractDateTime(item.ActivityTime) ?? DateTime.Now,
                CompanyId = company.Id,
                EmployeeId = employeeId,
                DeviceId = deviceId,
                ActivityType = string.IsNullOrWhiteSpace(item.ActivityType) ? "unknown" : item.ActivityType.Trim(),
                Confidence = item.Confidence,
                Source = string.IsNullOrWhiteSpace(request.Source) ? "api" : request.Source.Trim(),
                RawPayload = item.RawPayload?.GetRawText() ?? "{}"
            }, tx);
        }

        await tx.CommitAsync(context.RequestAborted);

        return Results.Ok(new
        {
            message = "Successfully created",
            inserted = request.Activities.Count,
            company_id = company.Id,
            employee_id = employeeId
        });
    }
}
