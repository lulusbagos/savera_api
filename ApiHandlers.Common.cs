using System.Globalization;
using System.IO;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;
using Dapper;
using Microsoft.AspNetCore.Diagnostics;
using Microsoft.Extensions.Options;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    private static readonly DateTimeOffset ProcessStartedAt = DateTimeOffset.Now;

    public static Task<IResult> HealthAsync()
    {
        return Task.FromResult<IResult>(Results.Ok(new
        {
            status = "ok",
            timestamp = DateTimeOffset.Now
        }));
    }

    public static async Task<IResult> HealthDetailAsync(
        NpgsqlDataSource db,
        FileWriterQueue queue,
        IOptions<AppOptions> options,
        IHostEnvironment environment)
    {
        var dbStatus = "ok";
        string? dbError = null;

        try
        {
            _ = await db.ExecuteScalarAsync<int>("SELECT 1");
        }
        catch (Exception ex)
        {
            dbStatus = "error";
            dbError = ex.Message;
        }

        var uptime = DateTimeOffset.Now - ProcessStartedAt;
        var overallStatus = dbStatus == "ok" ? "ok" : "degraded";
        var queueSnapshot = await queue.SnapshotAsync();

        return Results.Ok(new
        {
            status = overallStatus,
            timestamp = DateTimeOffset.Now,
            env = environment.EnvironmentName,
            uptime_seconds = (long)uptime.TotalSeconds,
            process_started_at = ProcessStartedAt,
            checks = new
            {
                db = new
                {
                    status = dbStatus,
                    error = dbError
                },
                upload_queue = queueSnapshot
            },
            limits = new
            {
                max_request_body_bytes = options.Value.MaxRequestBodyBytes,
                rate_limit_token_per_second = options.Value.RateLimitTokenPerSecond,
                rate_limit_burst = options.Value.RateLimitBurst,
                rate_limit_queue_limit = options.Value.RateLimitQueueLimit
            }
        });
    }

    public static async Task HandleUnhandledExceptionAsync(HttpContext context)
    {
        var traceId = EnsureTraceId(context);
        var logger = context.RequestServices.GetRequiredService<ILoggerFactory>().CreateLogger("GlobalException");
        var exception = context.Features.Get<IExceptionHandlerPathFeature>()?.Error;

        logger.LogError(exception, "Unhandled error. traceId={TraceId}", traceId);

        context.Response.StatusCode = StatusCodes.Status500InternalServerError;
        context.Response.ContentType = "application/json";
        await context.Response.WriteAsJsonAsync(new
        {
            message = "Internal server error",
            trace_id = traceId
        });
    }

    public static string EnsureTraceId(HttpContext context)
    {
        if (context.Items.TryGetValue("trace_id", out var existing) && existing is string s && !string.IsNullOrWhiteSpace(s))
        {
            return s;
        }

        var fromHeader = context.Request.Headers["X-Request-Id"].FirstOrDefault();
        var traceId = string.IsNullOrWhiteSpace(fromHeader) ? Guid.NewGuid().ToString("N") : fromHeader.Trim();
        context.Items["trace_id"] = traceId;
        return traceId;
    }

    public static string? ResolveIdempotencyKey(HttpContext context)
    {
        var key = context.Request.Headers["Idempotency-Key"].FirstOrDefault();
        if (string.IsNullOrWhiteSpace(key))
        {
            key = context.Request.Headers["X-Idempotency-Key"].FirstOrDefault();
        }
        if (string.IsNullOrWhiteSpace(key))
        {
            return null;
        }

        var trimmed = key.Trim();
        if (trimmed.Length > 120)
        {
            trimmed = trimmed[..120];
        }

        // Keep keys stable and log-safe.
        if (!Regex.IsMatch(trimmed, "^[a-zA-Z0-9._:-]+$"))
        {
            return null;
        }

        return trimmed;
    }

    public static IResult ErrorMessage(int statusCode, string message)
        => Results.Json(new { message }, statusCode: statusCode);

    public static bool IsUploadEndpoint(PathString path)
        => path.Equals("/api/summary", StringComparison.OrdinalIgnoreCase)
            || path.Equals("/api/detail", StringComparison.OrdinalIgnoreCase);

    public static async Task<string?> ReadRequestBodySnippetAsync(HttpContext context, int maxChars = 2048)
    {
        if (!context.Request.Body.CanSeek)
        {
            context.Request.EnableBuffering();
        }

        context.Request.Body.Position = 0;
        using var reader = new StreamReader(context.Request.Body, Encoding.UTF8, detectEncodingFromByteOrderMarks: false, leaveOpen: true);
        var buffer = new char[Math.Max(128, maxChars)];
        var read = await reader.ReadBlockAsync(buffer, 0, buffer.Length);
        context.Request.Body.Position = 0;

        if (read <= 0)
        {
            return null;
        }

        var snippet = new string(buffer, 0, read).Trim();
        return string.IsNullOrWhiteSpace(snippet) ? null : snippet;
    }

    public static string? ExtractJsonStringField(string? json, string fieldName)
    {
        if (string.IsNullOrWhiteSpace(json) || string.IsNullOrWhiteSpace(fieldName))
        {
            return null;
        }

        try
        {
            using var doc = JsonDocument.Parse(json);
            if (!doc.RootElement.TryGetProperty(fieldName, out var value))
            {
                return null;
            }

            return value.ValueKind switch
            {
                JsonValueKind.String => value.GetString(),
                JsonValueKind.Number => value.GetRawText(),
                JsonValueKind.True => "true",
                JsonValueKind.False => "false",
                _ => value.GetRawText()
            };
        }
        catch
        {
            return null;
        }
    }

    public static string? BuildUploadBadRequestHint(string? bodySnippet)
    {
        if (string.IsNullOrWhiteSpace(bodySnippet))
        {
            return "Request body empty or unreadable before model binding.";
        }

        var employeeId = ExtractJsonStringField(bodySnippet, "employee_id");
        var macAddress = ExtractJsonStringField(bodySnippet, "mac_address");
        var uploadKey = ExtractJsonStringField(bodySnippet, "upload_key");
        var parts = new List<string>();

        if (string.IsNullOrWhiteSpace(uploadKey))
        {
            parts.Add("upload_key missing");
        }
        if (string.IsNullOrWhiteSpace(employeeId))
        {
            parts.Add("employee_id missing");
        }
        if (string.IsNullOrWhiteSpace(macAddress))
        {
            parts.Add("mac_address missing");
        }

        if (parts.Count == 0)
        {
            return "Check JSON types or malformed body; required upload fields were present in body snippet.";
        }

        return string.Join(", ", parts);
    }

    public static async Task<AuthContext?> AuthenticateAsync(HttpContext context, NpgsqlDataSource db)
    {
        var authHeader = context.Request.Headers.Authorization.ToString();
        if (!authHeader.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase))
        {
            return null;
        }

        var rawToken = authHeader[7..].Trim();
        if (string.IsNullOrWhiteSpace(rawToken))
        {
            return null;
        }

        var tokenHash = ComputeSha256(rawToken);

        const string sql = @"
SELECT t.user_id, t.company_id, u.username, u.email, u.role, t.last_used_at
FROM public.tbl_t_api_token t
JOIN public.tbl_m_user u ON u.id = t.user_id
WHERE t.token_hash=@TokenHash
  AND t.revoked_at IS NULL
  AND t.expires_at > now()
  AND u.deleted_at IS NULL
  AND u.is_active = true
LIMIT 1";

        var row = await db.QuerySingleOrDefaultAsync<AuthTokenRow>(sql, new { TokenHash = tokenHash });
        if (row is null)
        {
            return null;
        }

        if (!row.LastUsedAt.HasValue || row.LastUsedAt.Value < DateTime.Now.AddMinutes(-5))
        {
            await db.ExecuteAsync(
                "UPDATE public.tbl_t_api_token SET last_used_at=now() WHERE token_hash=@TokenHash",
                new { TokenHash = tokenHash }
            );
        }

        return new AuthContext
        {
            UserId = row.UserId,
            CompanyId = row.CompanyId,
            Username = row.Username,
            Email = row.Email,
            Role = row.Role,
            TokenHash = tokenHash
        };
    }

    public static async Task<CompanyRow?> ResolveCompanyFromHeaderAsync(HttpContext context, NpgsqlDataSource db)
    {
        var code = context.Request.Headers["company"].FirstOrDefault()?.Trim();
        if (string.IsNullOrWhiteSpace(code))
        {
            return null;
        }

        return await db.QuerySingleOrDefaultAsync<CompanyRow>(
            @"SELECT id, code, name
              FROM public.tbl_m_company
              WHERE deleted_at IS NULL
                AND lower(trim(code)) = lower(trim(@Code))
              LIMIT 1",
            new { Code = code }
        );
    }

    public static async Task<CompanyRow?> ResolveCompanyForAuthAsync(HttpContext context, NpgsqlDataSource db, int fallbackCompanyId)
    {
        var fromHeader = await ResolveCompanyFromHeaderAsync(context, db);
        if (fromHeader is not null)
        {
            return fromHeader;
        }

        return await db.QuerySingleOrDefaultAsync<CompanyRow>(
            "SELECT id, code, name FROM public.tbl_m_company WHERE deleted_at IS NULL AND id=@Id LIMIT 1",
            new { Id = fallbackCompanyId }
        );
    }

    public static async Task<ApiRouteConfigRow?> GetRouteConfigAsync(NpgsqlDataSource db, int companyId)
    {
        const string sql = @"
SELECT company_id,
       primary_base_url,
       secondary_base_url,
       local_base_url,
       local_ip,
       local_port,
       sleep_rest_bonus_enabled
FROM public.tbl_m_api_route
WHERE company_id=@CompanyId
  AND is_active=true
  AND deleted_at IS NULL
LIMIT 1";

        try
        {
            return await db.QuerySingleOrDefaultAsync<ApiRouteConfigRow>(sql, new { CompanyId = companyId });
        }
        catch (PostgresException ex) when (ex.SqlState == "42703")
        {
            const string fallbackSql = @"
SELECT company_id,
       primary_base_url,
       secondary_base_url,
       local_base_url,
       local_ip,
       local_port,
       true AS sleep_rest_bonus_enabled
FROM public.tbl_m_api_route
WHERE company_id=@CompanyId
  AND is_active=true
  AND deleted_at IS NULL
LIMIT 1";
            return await db.QuerySingleOrDefaultAsync<ApiRouteConfigRow>(fallbackSql, new { CompanyId = companyId });
        }
    }

    public static async Task InsertUploadLogAsync(NpgsqlDataSource db, UploadLogInput input)
    {
        const string sql = @"
INSERT INTO public.tbl_t_upload_log
(trace_id, request_type, endpoint, http_method, route_base, route_url, request_key, status_code,
 duration_ms, attempts, error_type, error_message, note,
 company_id, department_id, employee_id, device_id,
 mac_address, app_version, network_transport, network_quality,
 is_api_reachable, is_api_slow, payload_size, response_size, created_at)
VALUES
(@TraceId, @RequestType, @Endpoint, 'POST', @RouteBase, @RouteUrl, @RequestKey, @StatusCode,
 @DurationMs, @Attempts, @ErrorType, @ErrorMessage, @Note,
 @CompanyId, @DepartmentId, @EmployeeId, @DeviceId,
 @MacAddress, @AppVersion, @NetworkTransport, @NetworkQuality,
 @IsApiReachable, @IsApiSlow, @PayloadSize, @ResponseSize, now())";

        await db.ExecuteAsync(sql, input);
    }

    public static string CreateRawToken()
    {
        var bytes = RandomNumberGenerator.GetBytes(32);
        return Convert.ToHexString(bytes).ToLowerInvariant();
    }

    public static string ComputeSha256(string input)
    {
        var bytes = SHA256.HashData(Encoding.UTF8.GetBytes(input));
        return Convert.ToHexString(bytes).ToLowerInvariant();
    }

    public static string[] BuildLoginCandidates(string identity, string? companyCode)
    {
        var values = new List<string>();
        var baseValue = (identity ?? string.Empty).Trim().ToLowerInvariant();
        if (string.IsNullOrWhiteSpace(baseValue))
        {
            return Array.Empty<string>();
        }

        values.Add(baseValue);

        if (!string.IsNullOrWhiteSpace(companyCode))
        {
            var companyLower = companyCode.Trim().ToLowerInvariant();
            if (baseValue.StartsWith(companyLower, StringComparison.Ordinal) && baseValue.Length > companyLower.Length)
            {
                values.Add(baseValue[companyLower.Length..]);
            }
        }

        var atIndex = baseValue.IndexOf('@');
        if (atIndex > 0)
        {
            values.Add(baseValue[..atIndex]);
        }

        return values.Distinct(StringComparer.Ordinal).ToArray();
    }

    public static string DescribeStoredPasswordMode(string? storedHash)
    {
        var value = (storedHash ?? string.Empty).Trim();
        if (string.IsNullOrWhiteSpace(value))
        {
            return "empty";
        }

        if (LooksLikeEncryptedPassword(value))
        {
            return "encrypted";
        }

        if (value.StartsWith("$2", StringComparison.Ordinal))
        {
            return "bcrypt";
        }

        if (Regex.IsMatch(value, "^[A-Fa-f0-9]{64}$"))
        {
            return "sha256";
        }

        return "plain";
    }

    public static bool VerifyPassword(
        string plainPassword,
        string storedHash,
        string? dcrip = null,
        string? decryptKey = null)
    {
        if (string.IsNullOrWhiteSpace(plainPassword) || string.IsNullOrWhiteSpace(storedHash))
        {
            return false;
        }

        _ = dcrip;

        if (LooksLikeEncryptedPassword(storedHash))
        {
            var decrypted = TryDecryptAes(storedHash, decryptKey);
            if (!string.IsNullOrWhiteSpace(decrypted))
            {
                return string.Equals(plainPassword, decrypted, StringComparison.Ordinal);
            }
        }

        if (TryVerifyBcrypt(plainPassword, storedHash))
        {
            return true;
        }

        if (Regex.IsMatch(storedHash, "^[A-Fa-f0-9]{64}$"))
        {
            var plainSha = ComputeSha256(plainPassword);
            return string.Equals(plainSha, storedHash, StringComparison.OrdinalIgnoreCase);
        }

        return string.Equals(plainPassword, storedHash, StringComparison.Ordinal);
    }

    private static bool LooksLikeEncryptedPassword(string storedHash)
    {
        var payload = storedHash.Trim();
        if (payload.StartsWith("enc:v1:", StringComparison.OrdinalIgnoreCase))
        {
            return true;
        }

        var parts = payload.Split(':', StringSplitOptions.TrimEntries);
        if (parts.Length != 2)
        {
            return false;
        }

        try
        {
            var ivBytes = Convert.FromBase64String(parts[0]);
            var cipherBytes = Convert.FromBase64String(parts[1]);
            return ivBytes.Length == 16 && cipherBytes.Length > 0;
        }
        catch
        {
            return false;
        }
    }

    private static bool TryVerifyBcrypt(string plainPassword, string storedHash)
    {
        if (!storedHash.StartsWith("$2", StringComparison.Ordinal))
        {
            return false;
        }

        try
        {
            return BCrypt.Net.BCrypt.Verify(plainPassword, storedHash);
        }
        catch
        {
            return false;
        }
    }

    private static string? TryDecryptAes(string encryptedValue, string? decryptKey)
    {
        if (string.IsNullOrWhiteSpace(encryptedValue) || string.IsNullOrWhiteSpace(decryptKey))
        {
            return null;
        }

        var payload = encryptedValue.Trim();
        string ivBase64;
        string cipherBase64;

        if (payload.StartsWith("enc:v1:", StringComparison.OrdinalIgnoreCase))
        {
            var parts = payload.Split(':', StringSplitOptions.TrimEntries);
            if (parts.Length != 4)
            {
                return null;
            }

            ivBase64 = parts[2];
            cipherBase64 = parts[3];
        }
        else
        {
            var parts = payload.Split(':', StringSplitOptions.TrimEntries);
            if (parts.Length != 2)
            {
                return null;
            }

            ivBase64 = parts[0];
            cipherBase64 = parts[1];
        }

        try
        {
            var ivBytes = Convert.FromBase64String(ivBase64);
            var cipherBytes = Convert.FromBase64String(cipherBase64);
            if (ivBytes.Length != 16 || cipherBytes.Length == 0)
            {
                return null;
            }

            using var aes = Aes.Create();
            aes.Mode = CipherMode.CBC;
            aes.Padding = PaddingMode.PKCS7;
            aes.Key = SHA256.HashData(Encoding.UTF8.GetBytes(decryptKey.Trim()));
            aes.IV = ivBytes;

            using var decryptor = aes.CreateDecryptor();
            var plainBytes = decryptor.TransformFinalBlock(cipherBytes, 0, cipherBytes.Length);
            return Encoding.UTF8.GetString(plainBytes);
        }
        catch
        {
            return null;
        }
    }

    public static string NormalizeMac(string? value) => (value ?? string.Empty).Trim();

    public static bool IsAdmin(string role)
    {
        if (string.IsNullOrWhiteSpace(role))
        {
            return false;
        }

        var normalized = role.Trim().ToLowerInvariant();
        return normalized is "admin" or "superadmin" or "super_admin";
    }

    public static bool? ToBool(int? value)
    {
        if (!value.HasValue)
        {
            return null;
        }

        return value.Value > 0;
    }

    public static string ToYesNo(bool? value)
    {
        if (!value.HasValue)
        {
            return "-";
        }

        return value.Value ? "ya" : "tidak";
    }

    public static string ToHourMinute(decimal valueMinutes)
    {
        var hours = (int)Math.Floor(valueMinutes / 60m);
        var minutes = (int)(valueMinutes % 60m);
        return $"{hours:00}:{minutes:00}";
    }
}
