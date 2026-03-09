using Dapper;
using Microsoft.Extensions.Options;
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

    public static async Task<IResult> AdminUploadFailuresAsync(HttpContext context, NpgsqlDataSource db, IOptions<AppOptions> options)
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
        var uploadKey = (context.Request.Query["upload_key"].FirstOrDefault() ?? string.Empty).Trim();
        var employeeIdFilter = int.TryParse(context.Request.Query["employee_id"].FirstOrDefault(), out var employeeIdValue)
            ? employeeIdValue
            : (int?)null;

        var recentFailuresSql = @"
SELECT id, trace_id, request_type, endpoint, request_key, status_code,
       error_type, error_message, note, route_base, route_url, company_id,
       department_id, employee_id, device_id, mac_address, created_at
FROM public.tbl_t_upload_log
WHERE status_code >= 400";

        if (!string.IsNullOrWhiteSpace(uploadKey))
        {
            recentFailuresSql += " AND request_key=@UploadKey";
        }
        if (employeeIdFilter.HasValue)
        {
            recentFailuresSql += " AND employee_id=@EmployeeId";
        }
        recentFailuresSql += " ORDER BY id DESC LIMIT @Limit";

        var recentFailures = (await db.QueryAsync<dynamic>(recentFailuresSql, new
        {
            Limit = limit,
            UploadKey = uploadKey,
            EmployeeId = employeeIdFilter
        })).ToList();

        var allQueueSql = @"
SELECT id, request_type, request_key, employee_id, record_date,
       relative_path, status, attempts, max_attempts, next_retry_at, last_error, updated_at
FROM public.tbl_t_upload_file_queue
WHERE status IN ('pending', 'processing', 'failed')";

        if (!string.IsNullOrWhiteSpace(uploadKey))
        {
            allQueueSql += " AND request_key=@UploadKey";
        }
        if (employeeIdFilter.HasValue)
        {
            allQueueSql += " AND employee_id=@EmployeeId";
        }
        allQueueSql += " ORDER BY updated_at DESC NULLS LAST, id DESC LIMIT @Limit";

        var queueRows = (await db.QueryAsync<dynamic>(allQueueSql, new
        {
            Limit = limit,
            UploadKey = uploadKey,
            EmployeeId = employeeIdFilter
        })).ToList();

        var queueFailed = queueRows.Where(x => string.Equals((string?)x.status, "failed", StringComparison.OrdinalIgnoreCase)).ToList();
        var queuePending = queueRows.Where(x => string.Equals((string?)x.status, "pending", StringComparison.OrdinalIgnoreCase)).ToList();
        var queueProcessing = queueRows.Where(x => string.Equals((string?)x.status, "processing", StringComparison.OrdinalIgnoreCase)).ToList();

        var uploadRoot = string.IsNullOrWhiteSpace(options.Value.UploadRoot) ? AppContext.BaseDirectory : options.Value.UploadRoot;
        var uploadRootExists = Directory.Exists(uploadRoot);
        var folderSummary = BuildUploadFolderSummary(uploadRoot);
        var fallbackFiles = FindFallbackFiles(uploadRoot, uploadKey, employeeIdFilter, limit);

        return Results.Ok(new
        {
            message = "ok",
            generated_at = DateTimeOffset.Now,
            data = new
            {
                filters = new
                {
                    upload_key = string.IsNullOrWhiteSpace(uploadKey) ? null : uploadKey,
                    employee_id = employeeIdFilter
                },
                upload_root = new
                {
                    path = uploadRoot,
                    exists = uploadRootExists
                },
                upload_folder_summary = folderSummary,
                upload_log_failures = recentFailures,
                file_queue_failed = queueFailed,
                file_queue_pending = queuePending,
                file_queue_processing = queueProcessing,
                fallback_files = fallbackFiles
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

    private static object BuildUploadFolderSummary(string uploadRoot)
    {
        if (!Directory.Exists(uploadRoot))
        {
            return new
            {
                exists = false,
                directories = Array.Empty<object>()
            };
        }

        var names = new[]
        {
            "data_activity",
            "data_sleep",
            "data_stress",
            "data_spo2",
            "data_heart_rate_max",
            "data_heart_rate_resting",
            "data_heart_rate_manual",
            "failed_uploads"
        };

        var directories = names.Select(name =>
        {
            var fullPath = Path.Combine(uploadRoot, name);
            var exists = Directory.Exists(fullPath);
            var latest = exists
                ? Directory.EnumerateFiles(fullPath, "*", SearchOption.AllDirectories)
                    .Select(path => new FileInfo(path))
                    .OrderByDescending(file => file.LastWriteTimeUtc)
                    .FirstOrDefault()
                : null;

            return new
            {
                name,
                path = fullPath,
                exists,
                latest_file = latest?.FullName,
                latest_file_at = latest?.LastWriteTime
            };
        }).ToList();

        return new
        {
            exists = true,
            directories
        };
    }

    private static List<object> FindFallbackFiles(string uploadRoot, string uploadKey, int? employeeId, int limit)
    {
        var failedRoot = Path.Combine(uploadRoot, "failed_uploads");
        if (!Directory.Exists(failedRoot))
        {
            return new List<object>();
        }

        IEnumerable<string> files = Directory.EnumerateFiles(failedRoot, "*.json", SearchOption.AllDirectories);

        if (employeeId.HasValue)
        {
            var employeeToken = $"employee_{employeeId.Value}";
            files = files.Where(path => path.Contains(employeeToken, StringComparison.OrdinalIgnoreCase));
        }

        if (!string.IsNullOrWhiteSpace(uploadKey))
        {
            var safeKey = uploadKey
                .Replace('\\', '_')
                .Replace('/', '_')
                .Replace(':', '_')
                .Replace('|', '_');
            files = files.Where(path => Path.GetFileName(path).Contains(safeKey, StringComparison.OrdinalIgnoreCase));
        }

        return files
            .Select(path => new FileInfo(path))
            .OrderByDescending(file => file.LastWriteTimeUtc)
            .Take(limit)
            .Select(file => (object)new
            {
                path = file.FullName,
                name = file.Name,
                size = file.Length,
                updated_at = file.LastWriteTime
            })
            .ToList();
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
