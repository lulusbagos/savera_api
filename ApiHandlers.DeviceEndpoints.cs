using System.Diagnostics;
using Dapper;
using Microsoft.Extensions.Options;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> GetDeviceAsync(HttpContext context, string mac, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        if (string.IsNullOrWhiteSpace(mac))
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Mac address not found.");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        const string deviceSql = @"
SELECT id, company_id, brand, device_name, mac_address, auth_key, app_version, updated_at
FROM public.tbl_m_device
WHERE company_id=@CompanyId
  AND deleted_at IS NULL
  AND lower(mac_address)=lower(@Mac)
LIMIT 1";

        var device = await db.QuerySingleOrDefaultAsync<DeviceRow>(deviceSql, new
        {
            CompanyId = company.Id,
            Mac = NormalizeMac(mac)
        });

        if (device is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");
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
LEFT JOIN public.tbl_m_department d ON d.id=e.department_id
LEFT JOIN public.tbl_m_mess m ON m.id=e.mess_id
WHERE e.deleted_at IS NULL
  AND e.company_id=@CompanyId
  AND e.device_id=@DeviceId
LIMIT 1";

        var employee = await db.QuerySingleOrDefaultAsync<EmployeeProfileRow>(employeeSql, new
        {
            CompanyId = company.Id,
            DeviceId = device.Id
        });

        if (employee is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Your device is not yet bound to an account.");
        }

        return Results.Ok(new
        {
            id = device.Id,
            company_id = device.CompanyId,
            brand = device.Brand,
            device_name = device.DeviceName,
            mac_address = device.MacAddress,
            auth_key = device.AuthKey,
            app_version = device.AppVersion,
            updated_at = device.UpdatedAt,
            employee = new
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
                job = employee.Job,
                position = employee.Position,
                department_name = employee.DepartmentName,
                mess_name = employee.MessName
            }
        });
    }

    public static async Task<IResult> GetDeviceAuthKeyAsync(HttpContext context, string mac, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        if (string.IsNullOrWhiteSpace(mac))
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Mac address not found.");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var device = await db.QuerySingleOrDefaultAsync<DeviceRow>(@"
SELECT id, company_id, brand, device_name, mac_address, auth_key, app_version, updated_at
FROM public.tbl_m_device
WHERE company_id=@CompanyId
  AND deleted_at IS NULL
  AND lower(mac_address)=lower(@Mac)
LIMIT 1", new { CompanyId = company.Id, Mac = NormalizeMac(mac) });

        if (device is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");
        }

        return Results.Ok(new
        {
            message = "ok",
            device_id = device.Id,
            mac_address = device.MacAddress,
            auth_key = device.AuthKey,
            has_auth_key = !string.IsNullOrWhiteSpace(device.AuthKey),
            updated_at = device.UpdatedAt
        });
    }

    public static async Task<IResult> UpdateDeviceAuthKeyAsync(
        HttpContext context,
        string mac,
        AuthKeyUpdateRequest request,
        NpgsqlDataSource db)
    {
        var watch = Stopwatch.StartNew();
        var traceId = EnsureTraceId(context);

        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        if (string.IsNullOrWhiteSpace(mac))
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Mac address not found.");
        }

        if (string.IsNullOrWhiteSpace(request.AuthKey))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "auth_key is required");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
        await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

        var device = await conn.QuerySingleOrDefaultAsync<DeviceRow>(@"
SELECT id, company_id, brand, device_name, mac_address, auth_key, app_version, updated_at
FROM public.tbl_m_device
WHERE company_id=@CompanyId
  AND deleted_at IS NULL
  AND lower(mac_address)=lower(@Mac)
LIMIT 1", new { CompanyId = company.Id, Mac = NormalizeMac(mac) }, tx);

        if (device is null)
        {
            await tx.RollbackAsync(context.RequestAborted);
            return ErrorMessage(StatusCodes.Status404NotFound, "Your device's MAC address is unavailable.");
        }

        await conn.ExecuteAsync(@"
UPDATE public.tbl_m_device
SET auth_key=@AuthKey,
    updated_by=@UpdatedBy,
    updated_at=now()
WHERE id=@DeviceId", new
        {
            AuthKey = request.AuthKey.Trim(),
            UpdatedBy = auth.UserId,
            DeviceId = device.Id
        }, tx);

        await conn.ExecuteAsync(@"
INSERT INTO public.tbl_t_device_authkey_log
(company_id, device_id, mac_address, old_auth_key, new_auth_key, changed_by_user_id, source, note, created_at)
VALUES
(@CompanyId, @DeviceId, @MacAddress, @OldAuthKey, @NewAuthKey, @ChangedByUserId, @Source, @Note, now())", new
        {
            CompanyId = company.Id,
            DeviceId = device.Id,
            MacAddress = device.MacAddress ?? NormalizeMac(mac),
            OldAuthKey = device.AuthKey,
            NewAuthKey = request.AuthKey.Trim(),
            ChangedByUserId = auth.UserId,
            Source = string.IsNullOrWhiteSpace(request.Source) ? "api" : request.Source.Trim(),
            Note = request.Note
        }, tx);

        await tx.CommitAsync(context.RequestAborted);

        watch.Stop();
        await InsertUploadLogAsync(db, new UploadLogInput
        {
            TraceId = traceId,
            RequestType = "device_auth_key",
            Endpoint = "/api/device/{mac}/auth-key",
            RouteUrl = context.Request.Path.Value,
            StatusCode = StatusCodes.Status200OK,
            DurationMs = (int)watch.ElapsedMilliseconds,
            Attempts = 1,
            CompanyId = company.Id,
            DeviceId = device.Id,
            MacAddress = device.MacAddress,
            Note = request.Note
        });

        return Results.Ok(new
        {
            message = "Successfully updated",
            device_id = device.Id,
            mac_address = device.MacAddress,
            auth_key = request.AuthKey.Trim()
        });
    }

    public static async Task<IResult> UploadAvatarAsync(
        HttpContext context,
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

        if (!context.Request.HasFormContentType)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "Invalid form payload.");
        }

        var form = await context.Request.ReadFormAsync(context.RequestAborted);
        var employeeRaw = form["employee_id"].FirstOrDefault();
        if (!int.TryParse(employeeRaw, out var employeeId) || employeeId <= 0)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "employee_id is required");
        }

        var photo = form.Files.GetFile("photo");
        if (photo is null || photo.Length == 0)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "photo is required");
        }

        if (photo.Length > 2 * 1024 * 1024)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "photo max size is 2MB");
        }

        var ext = Path.GetExtension(photo.FileName).ToLowerInvariant();
        var allowed = new HashSet<string>(StringComparer.OrdinalIgnoreCase) { ".jpg", ".jpeg", ".png", ".gif", ".svg" };
        if (!allowed.Contains(ext))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "photo format is not allowed");
        }

        var employee = await db.QuerySingleOrDefaultAsync<EmployeeProfileRow>(@"
SELECT e.id, e.company_id, e.user_id, e.photo
FROM public.tbl_r_employee e
WHERE e.deleted_at IS NULL
  AND e.id=@EmployeeId
  AND e.company_id=@CompanyId
LIMIT 1", new
        {
            EmployeeId = employeeId,
            CompanyId = company.Id
        });

        if (employee is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");
        }

        if (!IsAdmin(auth.Role) && employee.UserId.GetValueOrDefault() != auth.UserId)
        {
            return ErrorMessage(StatusCodes.Status403Forbidden, "Forbidden employee profile.");
        }

        var fileName = $"{DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}_{Guid.NewGuid():N}{ext}";
        var relative = Path.Combine("avatar", fileName);
        var fullPath = Path.Combine(options.Value.UploadRoot, relative);
        var folder = Path.GetDirectoryName(fullPath);
        if (!string.IsNullOrWhiteSpace(folder))
        {
            Directory.CreateDirectory(folder);
        }

        await using (var fileStream = File.Create(fullPath))
        {
            await photo.CopyToAsync(fileStream, context.RequestAborted);
        }

        await db.ExecuteAsync(@"
UPDATE public.tbl_r_employee
SET photo=@Photo,
    updated_by=@UpdatedBy,
    updated_at=now()
WHERE id=@EmployeeId", new
        {
            Photo = relative.Replace('\\', '/'),
            UpdatedBy = auth.UserId,
            EmployeeId = employeeId
        });

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                employee_id = employeeId,
                photo = relative.Replace('\\', '/')
            }
        });
    }

    public static IResult GetImageAsync(string path, IOptions<AppOptions> options)
    {
        if (string.IsNullOrWhiteSpace(path))
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Image not found.");
        }

        var normalized = path.Replace('\\', '/').TrimStart('/');
        if (normalized.Contains("..", StringComparison.Ordinal))
        {
            return ErrorMessage(StatusCodes.Status400BadRequest, "Invalid path.");
        }

        var fullPath = Path.GetFullPath(Path.Combine(options.Value.UploadRoot, normalized));
        var root = Path.GetFullPath(options.Value.UploadRoot);
        if (!fullPath.StartsWith(root, StringComparison.OrdinalIgnoreCase) || !File.Exists(fullPath))
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Image not found.");
        }

        var contentType = Path.GetExtension(fullPath).ToLowerInvariant() switch
        {
            ".jpg" or ".jpeg" => "image/jpeg",
            ".png" => "image/png",
            ".gif" => "image/gif",
            ".svg" => "image/svg+xml",
            ".webp" => "image/webp",
            ".json" => "application/json; charset=utf-8",
            _ => "application/octet-stream"
        };

        return Results.File(fullPath, contentType);
    }
}
