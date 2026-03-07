using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static Task<IResult> UploadStatusAsync(HttpContext context, NpgsqlDataSource db)
    {
        var uploadKey = context.Request.Query["upload_key"].FirstOrDefault() ?? string.Empty;
        return UploadStatusByKeyAsync(context, uploadKey, db);
    }

    public static async Task<IResult> UploadStatusByKeyAsync(HttpContext context, string uploadKey, NpgsqlDataSource db)
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

        var key = (uploadKey ?? string.Empty).Trim();
        if (string.IsNullOrWhiteSpace(key))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "upload_key is required");
        }

        var summary = await db.QuerySingleOrDefaultAsync<UploadStatusSummaryRow>(@"
SELECT id, upload_key, send_date, upload_status, last_error_message, route_base, retry_count, updated_at
FROM public.tbl_t_summary
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND upload_key=@UploadKey
ORDER BY id DESC
LIMIT 1", new
        {
            CompanyId = company.Id,
            UploadKey = key
        });

        var detail = await db.QuerySingleOrDefaultAsync<UploadStatusDetailRow>(@"
SELECT id, summary_id, upload_key, record_date, source, updated_at
FROM public.tbl_t_summary_detail
WHERE company_id=@CompanyId
  AND upload_key=@UploadKey
ORDER BY id DESC
LIMIT 1", new
        {
            CompanyId = company.Id,
            UploadKey = key
        });

        var lastAttempt = await db.QuerySingleOrDefaultAsync<UploadStatusLastAttemptRow>(@"
SELECT request_type, status_code, error_type, error_message, note, route_base, route_url, created_at
FROM public.tbl_t_upload_log
WHERE company_id=@CompanyId
  AND request_key=@UploadKey
ORDER BY id DESC
LIMIT 1", new
        {
            CompanyId = company.Id,
            UploadKey = key
        });

        var accepted = summary is not null || detail is not null;
        var status = accepted
            ? "accepted"
            : (lastAttempt is not null && lastAttempt.StatusCode >= 400 ? "failed" : "pending");

        return Results.Ok(new
        {
            message = "ok",
            upload_key = key,
            status,
            accepted,
            summary = summary,
            detail = detail,
            last_attempt = lastAttempt
        });
    }

    public static async Task<IResult> AdminMonitoringAsync(HttpContext context, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }
        if (!IsAdmin(auth.Role))
        {
            return ErrorMessage(StatusCodes.Status403Forbidden, "Admin access required.");
        }

        var limit = ParseLimit(context, "limit", 20, 5, 200);
        try
        {
            var successRate5m = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_upload_success_rate_5m
ORDER BY request_type")).ToList();

            var uploadStatus5m = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_upload_status_5m
ORDER BY request_type, status_group")).ToList();

            var topErrors1h = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_top_errors_1h
ORDER BY total_count DESC
LIMIT @Limit", new { Limit = limit })).ToList();

            var sideEffectErrors1h = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_sideeffect_errors_1h
ORDER BY total_count DESC")).ToList();

            var queueBacklog = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_queue_backlog")).ToList();

            var queuePendingOldest = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_queue_pending_oldest
ORDER BY next_retry_at NULLS FIRST, id
LIMIT @Limit", new { Limit = limit })).ToList();

            var networkQuality1h = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_network_quality_1h")).ToList();

            var summaryDetailGapToday = (await db.QueryAsync<dynamic>(@"
SELECT *
FROM monitoring.v_summary_detail_gap_today")).ToList();

            return Results.Ok(new
            {
                message = "ok",
                generated_at = DateTimeOffset.Now,
                data = new
                {
                    success_rate_5m = successRate5m,
                    upload_status_5m = uploadStatus5m,
                    top_errors_1h = topErrors1h,
                    sideeffect_errors_1h = sideEffectErrors1h,
                    queue_backlog = queueBacklog,
                    queue_pending_oldest = queuePendingOldest,
                    network_quality_1h = networkQuality1h,
                    summary_detail_gap_today = summaryDetailGapToday
                }
            });
        }
        catch (PostgresException ex) when (ex.SqlState == "42P01" || ex.SqlState == "3F000")
        {
            return Results.Json(new
            {
                message = "Monitoring views unavailable. Run sql/20260303_monitoring_dashboard_views.sql first.",
                sql_state = ex.SqlState
            }, statusCode: StatusCodes.Status503ServiceUnavailable);
        }
    }

    public static async Task<IResult> AdminUploadFailuresAsync(HttpContext context, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }
        if (!IsAdmin(auth.Role))
        {
            return ErrorMessage(StatusCodes.Status403Forbidden, "Admin access required.");
        }

        var limit = ParseLimit(context, "limit", 50, 10, 500);

        var recentFailures = (await db.QueryAsync<dynamic>(@"
SELECT id, trace_id, request_type, endpoint, request_key, status_code,
       error_type, error_message, note, route_base, route_url, company_id,
       department_id, employee_id, device_id, mac_address, created_at
FROM public.tbl_t_upload_log
WHERE status_code >= 400
ORDER BY id DESC
LIMIT @Limit", new { Limit = limit })).ToList();

        var queueFailed = (await db.QueryAsync<dynamic>(@"
SELECT id, request_type, request_key, employee_id, record_date,
       relative_path, status, attempts, max_attempts, next_retry_at, last_error, updated_at
FROM public.tbl_t_upload_file_queue
WHERE status = 'failed'
ORDER BY updated_at DESC, id DESC
LIMIT @Limit", new { Limit = limit })).ToList();

        return Results.Ok(new
        {
            message = "ok",
            generated_at = DateTimeOffset.Now,
            data = new
            {
                upload_log_failures = recentFailures,
                file_queue_failed = queueFailed
            }
        });
    }

    private static int ParseLimit(HttpContext context, string key, int defaultValue, int min, int max)
    {
        var raw = context.Request.Query[key].FirstOrDefault();
        if (!int.TryParse(raw, out var parsed))
        {
            return defaultValue;
        }

        if (parsed < min)
        {
            return min;
        }
        if (parsed > max)
        {
            return max;
        }
        return parsed;
    }
}

public sealed class UploadStatusSummaryRow
{
    public int Id { get; set; }
    public string? UploadKey { get; set; }
    public DateTime SendDate { get; set; }
    public string? UploadStatus { get; set; }
    public string? LastErrorMessage { get; set; }
    public string? RouteBase { get; set; }
    public int RetryCount { get; set; }
    public DateTime UpdatedAt { get; set; }
}

public sealed class UploadStatusDetailRow
{
    public long Id { get; set; }
    public int? SummaryId { get; set; }
    public string? UploadKey { get; set; }
    public DateTime RecordDate { get; set; }
    public string? Source { get; set; }
    public DateTime UpdatedAt { get; set; }
}

public sealed class UploadStatusLastAttemptRow
{
    public string? RequestType { get; set; }
    public int StatusCode { get; set; }
    public string? ErrorType { get; set; }
    public string? ErrorMessage { get; set; }
    public string? Note { get; set; }
    public string? RouteBase { get; set; }
    public string? RouteUrl { get; set; }
    public DateTime CreatedAt { get; set; }
}
