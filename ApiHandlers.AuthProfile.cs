using System.Globalization;
using Dapper;
using Microsoft.Extensions.Options;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> LoginAsync(
        HttpContext context,
        LoginRequest request,
        NpgsqlDataSource db,
        IOptions<AppOptions> options)
    {
        if (string.IsNullOrWhiteSpace(request.Email) || string.IsNullOrWhiteSpace(request.Password))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "Email and password are required");
        }

        var companyFromHeader = await ResolveCompanyFromHeaderAsync(context, db);
        if (context.Request.Headers.ContainsKey("company") && companyFromHeader is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var candidates = BuildLoginCandidates(request.Email, companyFromHeader?.Code);
        if (candidates.Length == 0)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "Invalid login identity");
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
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Invalid credentials.");
        }

        if (companyFromHeader is not null && user.CompanyId != companyFromHeader.Id)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Invalid credentials.");
        }

        if (!VerifyPassword(request.Password, user.Password, decryptKey: options.Value.PasswordDecryptKey))
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Invalid credentials.");
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
