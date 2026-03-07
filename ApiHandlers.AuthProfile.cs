using System.Globalization;
using Dapper;
using Microsoft.Extensions.Options;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    private static IResult LoginError(HttpContext context, int statusCode, string message, object? diagnostics = null)
    {
        return Results.Json(new
        {
            message,
            trace_id = EnsureTraceId(context),
            diagnostics
        }, statusCode: statusCode);
    }

    public static async Task<IResult> LoginAsync(
        HttpContext context,
        LoginRequest request,
        NpgsqlDataSource db,
        IOptions<AppOptions> options)
    {
        if (string.IsNullOrWhiteSpace(request.Email) || string.IsNullOrWhiteSpace(request.Password))
        {
            return LoginError(context, StatusCodes.Status422UnprocessableEntity, "Email and password are required");
        }

        var companyFromHeader = await ResolveCompanyFromHeaderAsync(context, db);
        if (context.Request.Headers.ContainsKey("company") && companyFromHeader is null)
        {
            return LoginError(context, StatusCodes.Status404NotFound, "Company not found.", new
            {
                company_header = context.Request.Headers["company"].FirstOrDefault()?.Trim(),
                email = request.Email.Trim(),
                suggestion = "Call POST /api/login/diagnostics with the same payload and company header to inspect the login flow."
            });
        }

        var candidates = BuildLoginCandidates(request.Email, companyFromHeader?.Code);
        if (candidates.Length == 0)
        {
            return LoginError(context, StatusCodes.Status422UnprocessableEntity, "Invalid login identity");
        }

        const string userSql = @"
SELECT id, company_id, username, email, password, role
FROM public.tbl_m_user
WHERE deleted_at IS NULL
  AND is_active = true
  AND (lower(username) = ANY(@Candidates) OR lower(email) = ANY(@Candidates))
ORDER BY id
LIMIT 1";

        var user = await db.QuerySingleOrDefaultAsync<UserRow>(userSql, new { Candidates = candidates });
        if (user is null)
        {
            return LoginError(context, StatusCodes.Status401Unauthorized, "Invalid credentials.");
        }

        if (companyFromHeader is not null && user.CompanyId != companyFromHeader.Id)
        {
            return LoginError(context, StatusCodes.Status401Unauthorized, "Invalid credentials.");
        }

        if (!VerifyPassword(request.Password, user.Password, decryptKey: options.Value.PasswordDecryptKey))
        {
            return LoginError(context, StatusCodes.Status401Unauthorized, "Invalid credentials.");
        }

        var company = companyFromHeader;
        if (company is null)
        {
            company = await db.QuerySingleOrDefaultAsync<CompanyRow>(
                "SELECT id, code, name FROM public.tbl_m_company WHERE id=@Id AND deleted_at IS NULL",
                new { Id = user.CompanyId }
            );
        }

        var rawToken = CreateRawToken();
        var tokenHash = ComputeSha256(rawToken);
        var now = DateTime.Now;
        var expiresAt = now.AddHours(Math.Max(1, options.Value.TokenLifetimeHours));
        var appVersion = string.IsNullOrWhiteSpace(request.AppVersion)
            ? context.Request.Headers["X-App-Version"].FirstOrDefault()?.Trim()
            : request.AppVersion.Trim();

        const string insertTokenSql = @"
INSERT INTO public.tbl_t_api_token
(token_hash, raw_hint, user_id, company_id, device_info, ip_address, expires_at, created_at)
VALUES
(@TokenHash, @RawHint, @UserId, @CompanyId, @DeviceInfo, @IpAddress, @ExpiresAt, @Now)";

        await db.ExecuteAsync(insertTokenSql, new
        {
            TokenHash = tokenHash,
            RawHint = rawToken[..8],
            UserId = user.Id,
            CompanyId = company?.Id,
            DeviceInfo = context.Request.Headers.UserAgent.ToString(),
            IpAddress = context.Connection.RemoteIpAddress?.ToString(),
            ExpiresAt = expiresAt,
            Now = now
        });

        try
        {
            await db.ExecuteAsync(
                "UPDATE public.tbl_m_user SET last_login_at=@Now, app_version=COALESCE(@AppVersion, app_version) WHERE id=@UserId",
                new
                {
                    UserId = user.Id,
                    Now = now,
                    AppVersion = appVersion
                }
            );
        }
        catch (PostgresException ex) when (ex.SqlState == "42703")
        {
            try
            {
                await db.ExecuteAsync(
                    "UPDATE public.tbl_m_user SET last_login=@Now, app_version=COALESCE(@AppVersion, app_version) WHERE id=@UserId",
                    new
                    {
                        UserId = user.Id,
                        Now = now,
                        AppVersion = appVersion
                    }
                );
            }
            catch (PostgresException ex2) when (ex2.SqlState == "42703")
            {
                // Backward compatibility: ignore when audit columns do not exist yet.
                try
                {
                    await db.ExecuteAsync(
                        "UPDATE public.tbl_m_user SET last_login=@Now WHERE id=@UserId",
                        new
                        {
                            UserId = user.Id,
                            Now = now
                        }
                    );
                }
                catch (PostgresException ex3) when (ex3.SqlState == "42703")
                {
                    // Ignore when both last_login_at and app_version columns are missing.
                }
            }
        }

        ApiRouteConfigRow? routeConfig = null;
        if (company is not null)
        {
            routeConfig = await GetRouteConfigAsync(db, company.Id);
        }

        return Results.Ok(new
        {
            message = "Successfully login",
            token = rawToken,
            expires_at = expiresAt,
            api_local_base_url = routeConfig?.LocalBaseUrl,
            api_local_ip = routeConfig?.LocalIp,
            api_local_port = routeConfig?.LocalPort,
            route_config = routeConfig is null ? null : new
            {
                api_local_base_url = routeConfig.LocalBaseUrl,
                api_local_ip = routeConfig.LocalIp,
                api_local_port = routeConfig.LocalPort,
                primary_base_url = routeConfig.PrimaryBaseUrl,
                secondary_base_url = routeConfig.SecondaryBaseUrl,
                sleep_rest_bonus_enabled = routeConfig.SleepRestBonusEnabled ?? true
            }
        });
    }

    public static async Task<IResult> LoginDiagnosticsAsync(
        HttpContext context,
        LoginRequest request,
        NpgsqlDataSource db,
        IOptions<AppOptions> options)
    {
        var traceId = EnsureTraceId(context);
        var companyHeader = context.Request.Headers["company"].FirstOrDefault()?.Trim() ?? string.Empty;
        var identity = request.Email?.Trim() ?? string.Empty;
        var candidatesWithoutCompany = BuildLoginCandidates(identity, null);
        var candidatesWithHeader = BuildLoginCandidates(identity, companyHeader);
        var candidates = candidatesWithHeader
            .Concat(candidatesWithoutCompany)
            .Distinct(StringComparer.Ordinal)
            .ToArray();

        var companyFromHeader = await ResolveCompanyFromHeaderAsync(context, db);

        var users = (await db.QueryAsync<LoginDiagnosticUserRow>(@"
SELECT id,
       company_id,
       username,
       email,
       password,
       role,
       is_active,
       deleted_at,
       last_login_at,
       last_login,
       app_version
FROM public.tbl_m_user
WHERE lower(username) = ANY(@Candidates)
   OR lower(email) = ANY(@Candidates)
ORDER BY id", new
        {
            Candidates = candidates.Length == 0 ? new[] { string.Empty } : candidates
        })).ToList();

        var userIds = users.Select(x => x.Id).Distinct().ToArray();
        var companyIds = users.Select(x => x.CompanyId).Distinct().ToArray();

        var employees = userIds.Length == 0
            ? new List<LoginDiagnosticEmployeeRow>()
            : (await db.QueryAsync<LoginDiagnosticEmployeeRow>(@"
SELECT id, company_id, user_id, department_id, device_id,
       COALESCE(NULLIF(code, ''), nik) AS code,
       nik, full_name, email, phone
FROM public.tbl_r_employee
WHERE deleted_at IS NULL
  AND user_id = ANY(@UserIds)
ORDER BY id", new { UserIds = userIds })).ToList();

        var employeeIds = employees.Select(x => x.Id).Distinct().ToArray();

        var tokenSessions = userIds.Length == 0
            ? new List<LoginDiagnosticTokenRow>()
            : (await db.QueryAsync<LoginDiagnosticTokenRow>(@"
SELECT id, user_id, company_id, raw_hint, ip_address, device_info, created_at, expires_at, last_used_at, revoked_at
FROM public.tbl_t_api_token
WHERE user_id = ANY(@UserIds)
ORDER BY id DESC
LIMIT 20", new { UserIds = userIds })).ToList();

        var recentProcesses = employeeIds.Length == 0
            ? new List<LoginDiagnosticProcessRow>()
            : (await db.QueryAsync<LoginDiagnosticProcessRow>(@"
SELECT id, request_type, endpoint, request_key, status_code, error_type, error_message, note,
       route_base, route_url, employee_id, device_id, created_at
FROM public.tbl_t_upload_log
WHERE employee_id = ANY(@EmployeeIds)
ORDER BY id DESC
LIMIT 30", new { EmployeeIds = employeeIds })).ToList();

        var queueItems = employeeIds.Length == 0
            ? new List<LoginDiagnosticQueueRow>()
            : (await db.QueryAsync<LoginDiagnosticQueueRow>(@"
SELECT id, request_type, request_key, employee_id, status, attempts, max_attempts,
       next_retry_at, last_error, updated_at
FROM public.tbl_t_upload_file_queue
WHERE employee_id = ANY(@EmployeeIds)
ORDER BY id DESC
LIMIT 20", new { EmployeeIds = employeeIds })).ToList();

        var companyProcessSummary = companyIds.Length == 0
            ? new List<LoginDiagnosticCompanyProcessRow>()
            : (await db.QueryAsync<LoginDiagnosticCompanyProcessRow>(@"
SELECT request_type,
       CASE
           WHEN status_code BETWEEN 200 AND 299 THEN 'success'
           WHEN status_code BETWEEN 400 AND 499 THEN 'client_error'
           WHEN status_code >= 500 THEN 'server_error'
           ELSE 'unknown'
       END AS status_group,
       COUNT(*) AS total,
       MAX(created_at) AS last_seen_at
FROM public.tbl_t_upload_log
WHERE company_id = ANY(@CompanyIds)
  AND created_at >= now() - interval '24 hour'
GROUP BY request_type,
         CASE
             WHEN status_code BETWEEN 200 AND 299 THEN 'success'
             WHEN status_code BETWEEN 400 AND 499 THEN 'client_error'
             WHEN status_code >= 500 THEN 'server_error'
             ELSE 'unknown'
         END
ORDER BY MAX(created_at) DESC, request_type", new { CompanyIds = companyIds })).ToList();

        var userSnapshots = users.Select(user =>
        {
            var userEmployees = employees.Where(x => x.UserId == user.Id).ToList();
            var userEmployeeIds = userEmployees.Select(x => x.Id).ToHashSet();
            var userTokens = tokenSessions
                .Where(x => x.UserId == user.Id)
                .Select(x => new
                {
                    id = x.Id,
                    company_id = x.CompanyId,
                    raw_hint = x.RawHint,
                    ip_address = x.IpAddress,
                    device_info = x.DeviceInfo,
                    created_at = x.CreatedAt,
                    expires_at = x.ExpiresAt,
                    last_used_at = x.LastUsedAt,
                    revoked_at = x.RevokedAt,
                    status = x.RevokedAt.HasValue
                        ? "revoked"
                        : (x.ExpiresAt <= DateTime.Now ? "expired" : "active")
                })
                .ToList();

            var userProcesses = recentProcesses
                .Where(x => x.EmployeeId.HasValue && userEmployeeIds.Contains(x.EmployeeId.Value))
                .Select(x => new
                {
                    id = x.Id,
                    request_type = x.RequestType,
                    endpoint = x.Endpoint,
                    request_key = x.RequestKey,
                    status_code = x.StatusCode,
                    status = x.StatusCode switch
                    {
                        >= 200 and <= 299 => "success",
                        >= 400 and <= 499 => "client_error",
                        >= 500 => "server_error",
                        _ => "unknown"
                    },
                    error_type = x.ErrorType,
                    error_message = x.ErrorMessage,
                    note = x.Note,
                    route_base = x.RouteBase,
                    route_url = x.RouteUrl,
                    device_id = x.DeviceId,
                    created_at = x.CreatedAt
                })
                .ToList();

            var userQueue = queueItems
                .Where(x => x.EmployeeId.HasValue && userEmployeeIds.Contains(x.EmployeeId.Value))
                .ToList();

            return new
            {
                id = user.Id,
                company_id = user.CompanyId,
                username = user.Username,
                email = user.Email,
                role = user.Role,
                is_active = user.IsActive,
                deleted_at = user.DeletedAt,
                last_login_at = user.LastLoginAt ?? user.LastLogin,
                app_version = user.AppVersion,
                password_mode = DescribeStoredPasswordMode(user.Password),
                password_match = string.IsNullOrWhiteSpace(request.Password)
                    ? (bool?)null
                    : VerifyPassword(request.Password, user.Password, decryptKey: options.Value.PasswordDecryptKey),
                employees = userEmployees,
                token_sessions = userTokens,
                recent_processes = userProcesses,
                queue_items = userQueue
            };
        }).ToList();

        var steps = new List<object>
        {
            new
            {
                step = "company_lookup",
                status = string.IsNullOrWhiteSpace(companyHeader)
                    ? "skipped"
                    : (companyFromHeader is null ? "error" : "ok"),
                input = companyHeader,
                message = string.IsNullOrWhiteSpace(companyHeader)
                    ? "company header not provided"
                    : (companyFromHeader is null ? "company header did not match tbl_m_company" : "company matched"),
                company = companyFromHeader
            },
            new
            {
                step = "identity_candidates",
                status = candidates.Length == 0 ? "error" : "ok",
                input = identity,
                message = candidates.Length == 0 ? "no login candidates generated" : "login candidates generated",
                candidates
            },
            new
            {
                step = "user_lookup",
                status = users.Count == 0 ? "error" : "ok",
                input = identity,
                message = users.Count == 0 ? "no user matched the generated candidates" : "user candidates found",
                total_matches = users.Count
            }
        };

        return Results.Ok(new
        {
            message = "ok",
            trace_id = traceId,
            request = new
            {
                email = identity,
                company_header = companyHeader,
                app_version = request.AppVersion,
                user_agent = context.Request.Headers.UserAgent.ToString(),
                remote_ip = context.Connection.RemoteIpAddress?.ToString()
            },
            steps,
            company = companyFromHeader,
            users = userSnapshots,
            company_process_status_24h = companyProcessSummary
        });
    }

    public static async Task<IResult> LogoutAsync(HttpContext context, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        await db.ExecuteAsync(
            "UPDATE public.tbl_t_api_token SET revoked_at=now() WHERE token_hash=@TokenHash AND revoked_at IS NULL",
            new { auth.TokenHash }
        );

        return Results.Ok(new { message = "Successfully logout" });
    }

    public static async Task<IResult> ProfileAsync(HttpContext context, NpgsqlDataSource db)
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

        const string employeeSql = @"
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
       e.position,
       d.name AS department_name,
       m.name AS mess_name
FROM public.tbl_r_employee e
LEFT JOIN public.tbl_m_department d ON d.id = e.department_id
LEFT JOIN public.tbl_m_mess m ON m.id = e.mess_id
WHERE e.deleted_at IS NULL
  AND e.user_id = @UserId
  AND e.company_id = @CompanyId
LIMIT 1";

        var employee = await db.QuerySingleOrDefaultAsync<EmployeeProfileRow>(employeeSql, new
        {
            UserId = auth.UserId,
            CompanyId = company.Id
        });

        ShiftRow? shift = null;
        DeviceRow? device = null;

        if (employee is not null)
        {
            var shiftId = employee.ShiftId.GetValueOrDefault();
            if (shiftId > 0)
            {
                shift = await db.QuerySingleOrDefaultAsync<ShiftRow>(
                    "SELECT id, code, name, work_start, work_end FROM public.tbl_m_shift WHERE id=@Id AND deleted_at IS NULL",
                    new { Id = shiftId }
                );
            }

            if (shift is null)
            {
                shift = await db.QuerySingleOrDefaultAsync<ShiftRow>(
                    "SELECT id, code, name, work_start, work_end FROM public.tbl_m_shift WHERE company_id=@CompanyId AND deleted_at IS NULL ORDER BY id LIMIT 1",
                    new { CompanyId = company.Id }
                );
            }

            if (employee.DeviceId.GetValueOrDefault() > 0)
            {
                device = await db.QuerySingleOrDefaultAsync<DeviceRow>(
                    @"SELECT id, company_id, brand, device_name, mac_address, auth_key, app_version, updated_at
                      FROM public.tbl_m_device
                      WHERE id=@Id AND deleted_at IS NULL",
                    new { Id = employee.DeviceId }
                );
            }
        }

        var routeConfig = await GetRouteConfigAsync(db, company.Id);

        return Results.Ok(new
        {
            id = auth.UserId,
            name = auth.Username,
            email = auth.Email,
            employee = employee is null ? null : new
            {
                id = employee.Id,
                company_id = employee.CompanyId,
                department_id = employee.DepartmentId,
                mess_id = employee.MessId,
                shift_id = employee.ShiftId,
                device_id = employee.DeviceId,
                user_id = employee.UserId,
                code = employee.Code,
                nik = employee.Nik,
                fullname = employee.Fullname,
                email = employee.Email,
                phone = employee.Phone,
                photo = employee.Photo,
                job_name = employee.Job,
                position_name = employee.Position,
                department_name = employee.DepartmentName,
                mess_name = employee.MessName
            },
            shift,
            device,
            is_admin = IsAdmin(auth.Role) ? 1 : 0,
            api_local_base_url = routeConfig?.LocalBaseUrl,
            api_local_ip = routeConfig?.LocalIp,
            api_local_port = routeConfig?.LocalPort,
            route_config = routeConfig is null ? null : new
            {
                api_local_base_url = routeConfig.LocalBaseUrl,
                api_local_ip = routeConfig.LocalIp,
                api_local_port = routeConfig.LocalPort,
                primary_base_url = routeConfig.PrimaryBaseUrl,
                secondary_base_url = routeConfig.SecondaryBaseUrl,
                sleep_rest_bonus_enabled = routeConfig.SleepRestBonusEnabled ?? true
            }
        });
    }

    public static async Task<IResult> ChangePasswordAsync(
        HttpContext context,
        ChangePasswordRequest request,
        NpgsqlDataSource db,
        IOptions<AppOptions> options)
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

        if (string.IsNullOrWhiteSpace(request.OldPassword) ||
            string.IsNullOrWhiteSpace(request.NewPassword) ||
            string.IsNullOrWhiteSpace(request.ConfirmPassword))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "old_password, new_password, and confirm_password are required");
        }

        if (!string.Equals(request.NewPassword, request.ConfirmPassword, StringComparison.Ordinal))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "confirm_password does not match");
        }

        if (request.NewPassword.Length < 6)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "new_password minimum length is 6");
        }

        if (string.Equals(request.OldPassword, request.NewPassword, StringComparison.Ordinal))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "new_password must be different from old_password");
        }

        var currentUser = await db.QuerySingleOrDefaultAsync<UserRow>(
            @"SELECT id, company_id, password
              FROM public.tbl_m_user
              WHERE id=@UserId
                AND company_id=@CompanyId
                AND deleted_at IS NULL
                AND is_active = true
              LIMIT 1",
            new
            {
                UserId = auth.UserId,
                CompanyId = company.Id
            });

        if (currentUser is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "User not found.");
        }

        if (!VerifyPassword(request.OldPassword, currentUser.Password, decryptKey: options.Value.PasswordDecryptKey))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "old_password is invalid");
        }

        await db.ExecuteAsync(
            @"UPDATE public.tbl_m_user
              SET password=@PasswordPlain
              WHERE id=@UserId
                AND company_id=@CompanyId",
            new
            {
                PasswordPlain = request.NewPassword,
                UserId = auth.UserId,
                CompanyId = company.Id
            });

        await db.ExecuteAsync(
            @"UPDATE public.tbl_t_api_token
              SET revoked_at=now()
              WHERE user_id=@UserId
                AND revoked_at IS NULL
                AND token_hash <> @CurrentTokenHash",
            new
            {
                UserId = auth.UserId,
                CurrentTokenHash = auth.TokenHash
            });

        return Results.Ok(new
        {
            message = "Successfully reset password"
        });
    }

    public static async Task<IResult> GetAvatarAsync(HttpContext context, NpgsqlDataSource db)
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

        var photo = await db.QuerySingleOrDefaultAsync<string>(@"
SELECT e.photo
FROM public.tbl_r_employee e
WHERE e.deleted_at IS NULL
  AND e.user_id=@UserId
  AND e.company_id=@CompanyId
LIMIT 1", new { auth.UserId, CompanyId = company.Id });

        if (string.IsNullOrWhiteSpace(photo))
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Photo not found.");
        }

        return Results.Redirect($"/image/{photo}");
    }

    public static async Task<IResult> BannerAsync(HttpContext context, NpgsqlDataSource db, IOptions<AppOptions> options)
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

        var rows = await db.QueryAsync<BannerRow>(@"
SELECT image
FROM public.tbl_m_banner
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND is_active=true
  AND (valid_from IS NULL OR valid_from <= CURRENT_DATE)
  AND (valid_to IS NULL OR valid_to >= CURRENT_DATE)
ORDER BY seq, id", new { CompanyId = company.Id });

        var baseUrl = (options.Value.AdminImageBaseUrl ?? string.Empty).Trim();
        var normalizedBase = baseUrl.EndsWith("/") ? baseUrl : baseUrl + "/";

        var images = rows
            .Select(x => x.Image)
            .Where(x => !string.IsNullOrWhiteSpace(x))
            .Select(x => x!.StartsWith("http://", StringComparison.OrdinalIgnoreCase) || x.StartsWith("https://", StringComparison.OrdinalIgnoreCase)
                ? x
                : normalizedBase + x.TrimStart('/'))
            .ToArray();

        return Results.Ok(images);
    }
}

public sealed class LoginDiagnosticUserRow
{
    public int Id { get; set; }
    public int CompanyId { get; set; }
    public string Username { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public bool IsActive { get; set; }
    public DateTime? DeletedAt { get; set; }
    public DateTime? LastLoginAt { get; set; }
    public DateTime? LastLogin { get; set; }
    public string? AppVersion { get; set; }
}

public sealed class LoginDiagnosticEmployeeRow
{
    public int Id { get; set; }
    public int CompanyId { get; set; }
    public int? UserId { get; set; }
    public int? DepartmentId { get; set; }
    public int? DeviceId { get; set; }
    public string? Code { get; set; }
    public string? Nik { get; set; }
    public string? FullName { get; set; }
    public string? Email { get; set; }
    public string? Phone { get; set; }
}

public sealed class LoginDiagnosticTokenRow
{
    public long Id { get; set; }
    public int UserId { get; set; }
    public int? CompanyId { get; set; }
    public string? RawHint { get; set; }
    public string? IpAddress { get; set; }
    public string? DeviceInfo { get; set; }
    public DateTime CreatedAt { get; set; }
    public DateTime ExpiresAt { get; set; }
    public DateTime? LastUsedAt { get; set; }
    public DateTime? RevokedAt { get; set; }
}

public sealed class LoginDiagnosticProcessRow
{
    public long Id { get; set; }
    public string? RequestType { get; set; }
    public string? Endpoint { get; set; }
    public string? RequestKey { get; set; }
    public int StatusCode { get; set; }
    public string? ErrorType { get; set; }
    public string? ErrorMessage { get; set; }
    public string? Note { get; set; }
    public string? RouteBase { get; set; }
    public string? RouteUrl { get; set; }
    public int? EmployeeId { get; set; }
    public int? DeviceId { get; set; }
    public DateTime CreatedAt { get; set; }
}

public sealed class LoginDiagnosticQueueRow
{
    public long Id { get; set; }
    public string? RequestType { get; set; }
    public string? RequestKey { get; set; }
    public int? EmployeeId { get; set; }
    public string? Status { get; set; }
    public int Attempts { get; set; }
    public int MaxAttempts { get; set; }
    public DateTime? NextRetryAt { get; set; }
    public string? LastError { get; set; }
    public DateTime UpdatedAt { get; set; }
}

public sealed class LoginDiagnosticCompanyProcessRow
{
    public string? RequestType { get; set; }
    public string? StatusGroup { get; set; }
    public long Total { get; set; }
    public DateTime LastSeenAt { get; set; }
}
