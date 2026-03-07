using System.Text.Json;
using System.Text.RegularExpressions;
using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> UpsertP5MCheckpointAsync(HttpContext context, P5MCheckpointRequest request, NpgsqlDataSource db)
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
        if (string.IsNullOrWhiteSpace(auth.Username))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "username is missing in token context");
        }

        var fallbackEmployee = await ResolveEmployeeByUserAsync(db, company.Id, auth.UserId);
        var employeeId = request.EmployeeId.GetValueOrDefault() > 0
            ? request.EmployeeId!.Value
            : fallbackEmployee?.Id;
        var nikOrUsername = string.IsNullOrWhiteSpace(request.NikOrUsername) ? auth.Username : request.NikOrUsername!.Trim();
        var recordDate = ExtractDateOnly(request.RecordDate) ?? DateOnly.FromDateTime(DateTime.Today);
        var source = string.IsNullOrWhiteSpace(request.Source) ? "mobile_quiz" : request.Source!.Trim();
        var payloadRaw = request.Payload.HasValue ? request.Payload.Value.GetRawText() : null;

        await UpsertP5MCheckpointRecordAsync(
            db,
            company.Id,
            employeeId,
            auth.Username,
            nikOrUsername,
            recordDate,
            request.Score,
            request.MaxScore,
            request.Percentage,
            source,
            payloadRaw
        );

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                company_id = company.Id,
                employee_id = employeeId,
                username = auth.Username,
                record_date = recordDate.ToString("yyyy-MM-dd"),
                status = 1
            }
        });
    }

    public static async Task<IResult> GetTodayP5MCheckpointAsync(HttpContext context, NpgsqlDataSource db)
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

        var employee = await ResolveEmployeeByUserAsync(db, company.Id, auth.UserId);
        var row = await db.QuerySingleOrDefaultAsync<P5MCheckpointRow>(@"
SELECT id, company_id, employee_id, username, nik, record_date, score, max_score, percentage, source, submitted_at
FROM public.tbl_t_p5m_checkpoint
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND record_date=CURRENT_DATE
  AND (
        lower(username)=lower(@Username)
        OR (@EmployeeId > 0 AND employee_id=@EmployeeId)
      )
ORDER BY submitted_at DESC, id DESC
LIMIT 1", new
        {
            CompanyId = company.Id,
            Username = auth.Username,
            EmployeeId = employee?.Id ?? 0
        });

        return Results.Ok(new
        {
            message = "ok",
            status = row is null ? 0 : 1,
            data = row
        });
    }

    public static async Task<IResult> GetP5MQuestionsAsync(HttpContext context, NpgsqlDataSource db)
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

        var rows = (await db.QueryAsync<dynamic>(@"
SELECT id, company_id, judul, konten, status, created_at, updated_at
FROM public.tbl_m_p5m
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
ORDER BY id ASC", new { CompanyId = company.Id })).ToList();

        var items = new List<object>();
        DateTime? latestUpdate = null;

        foreach (var row in rows)
        {
            if (row is not IDictionary<string, object> map)
            {
                continue;
            }

            var statusRaw = map.TryGetValue("status", out var st) ? st : null;
            if (!IsStatusActive(statusRaw))
            {
                continue;
            }

            var id = ToInt(map.TryGetValue("id", out var idObj) ? idObj : null);
            var questionText = (map.TryGetValue("judul", out var questionObj) ? questionObj?.ToString() : string.Empty)?.Trim() ?? string.Empty;
            var content = (map.TryGetValue("konten", out var contentObj) ? contentObj?.ToString() : string.Empty)?.Trim() ?? string.Empty;
            if (string.IsNullOrWhiteSpace(questionText))
            {
                continue;
            }

            var meta = ParseP5MQuestionMeta(content, questionText, items.Count + 1);
            items.Add(new
            {
                id,
                question = questionText,
                question_type = meta.QuestionType,
                options = meta.Options,
                weight = meta.Weight,
                is_required = meta.IsRequired,
                option_scores = meta.OptionScores,
                content
            });

            var updatedAt = ToDateTime(map.TryGetValue("updated_at", out var updatedObj) ? updatedObj : null)
                ?? ToDateTime(map.TryGetValue("created_at", out var createdObj) ? createdObj : null);
            if (updatedAt.HasValue && (!latestUpdate.HasValue || updatedAt.Value > latestUpdate.Value))
            {
                latestUpdate = updatedAt;
            }
        }

        return Results.Ok(new
        {
            message = "ok",
            total = items.Count,
            data = new
            {
                items,
                updated_at = latestUpdate
            }
        });
    }

    public static async Task<IResult> GetFtwManualEligibilityAsync(HttpContext context, NpgsqlDataSource db)
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

        var employee = await ResolveEmployeeByUserAsync(db, company.Id, auth.UserId);
        var employeeProfile = employee is null ? null : await ResolveEmployeeByIdAsync(db, company.Id, employee.Id);
        var check = await CheckFtwManualEligibilityAsync(db, company.Id, auth, employeeProfile, auth.Username);

        return Results.Ok(new
        {
            message = "ok",
            data = new
            {
                allowed = check.Allowed,
                require_p5m = check.RequireP5m,
                p5m_today = check.P5mToday,
                employee_id = employeeProfile?.Id,
                username = auth.Username,
                info = check.Message
            }
        });
    }

    public static async Task<IResult> GetMyNotificationsAsync(HttpContext context, NpgsqlDataSource db)
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

        var months = ParseRangeInt(context.Request.Query["months"].FirstOrDefault(), 2, 1, 12);
        var limit = ParseRangeInt(context.Request.Query["limit"].FirstOrDefault(), 2, 1, 200);

        var rows = (await db.QueryAsync<dynamic>(@"
SELECT id,
       username,
       title,
       message,
       kind,
       status,
       payload,
       created_at,
       read_at,
       COALESCE(
           payload->>'message_html',
           payload->>'html',
           payload->>'content_html',
           payload->>'body_html',
           payload->>'description_html'
       ) AS message_html,
       COALESCE(
           payload->>'message_plain',
           payload->>'message_text',
           payload->>'content_text',
           payload->>'description',
           NULLIF(message, '')
       ) AS message_plain
FROM public.tbl_t_user_notification
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
  AND created_at >= now() - make_interval(months => @Months)
ORDER BY created_at DESC, id DESC
LIMIT @Limit", new
        {
            CompanyId = company.Id,
            Username = auth.Username,
            Months = months,
            Limit = limit
        })).ToList();

        var unreadCount = await db.QuerySingleOrDefaultAsync<int>(@"
SELECT COUNT(*)::int
FROM public.tbl_t_user_notification
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
  AND status=0", new
        {
            CompanyId = company.Id,
            Username = auth.Username
        });

        return Results.Ok(new
        {
            message = "ok",
            months,
            limit,
            unread_count = unreadCount,
            total = rows.Count,
            data = rows
        });
    }

    public static async Task<IResult> MarkMyNotificationReadAsync(HttpContext context, long id, NpgsqlDataSource db)
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

        var affected = await db.ExecuteAsync(@"
UPDATE public.tbl_t_user_notification
SET status=1,
    read_at=COALESCE(read_at, now()),
    updated_at=now()
WHERE id=@Id
  AND deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)", new
        {
            Id = id,
            CompanyId = company.Id,
            Username = auth.Username
        });

        if (affected <= 0)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Notification not found.");
        }

        return Results.Ok(new
        {
            message = "Notification marked as read",
            data = new
            {
                id,
                status = 1
            }
        });
    }

    public static async Task<IResult> AdminCreateNotificationAsync(HttpContext context, NotificationCreateRequest request, NpgsqlDataSource db)
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

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var title = (request.Title ?? string.Empty).Trim();
        var payloadTitle = TryGetNotificationPayloadString(request.Payload, "title", "subject", "heading");
        if (string.IsNullOrWhiteSpace(title))
        {
            title = payloadTitle ?? string.Empty;
        }

        var message = ResolveNotificationMessage(request.Message, request.Payload);
        if (string.IsNullOrWhiteSpace(title) || string.IsNullOrWhiteSpace(message))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "title and message/payload content are required");
        }

        var targets = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        if (!string.IsNullOrWhiteSpace(request.Username))
        {
            targets.Add(request.Username.Trim());
        }
        if (request.Usernames is not null)
        {
            foreach (var item in request.Usernames)
            {
                if (!string.IsNullOrWhiteSpace(item))
                {
                    targets.Add(item.Trim());
                }
            }
        }
        if (targets.Count == 0)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "username/usernames is required");
        }

        var kind = string.IsNullOrWhiteSpace(request.Kind) ? "info" : request.Kind!.Trim().ToLowerInvariant();
        var payloadRaw = request.Payload.HasValue ? request.Payload.Value.GetRawText() : null;
        var inserted = 0;
        var rejected = new List<string>();

        foreach (var username in targets)
        {
            var userExists = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT id
FROM public.tbl_m_user
WHERE deleted_at IS NULL
  AND is_active=true
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
LIMIT 1", new
            {
                CompanyId = company.Id,
                Username = username
            });

            if (!userExists.HasValue)
            {
                rejected.Add(username);
                continue;
            }

            await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_user_notification
(company_id, username, title, message, kind, status, payload, created_by, created_at, updated_at)
VALUES
(@CompanyId, @Username, @Title, @Message, @Kind, 0, @Payload::jsonb, @CreatedBy, now(), now())", new
            {
                CompanyId = company.Id,
                Username = username,
                Title = title,
                Message = message,
                Kind = kind,
                Payload = payloadRaw,
                CreatedBy = auth.UserId
            });
            inserted++;
        }

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                inserted_count = inserted,
                rejected_usernames = rejected
            }
        });
    }

    public static async Task<IResult> AdminListNotificationsAsync(HttpContext context, NpgsqlDataSource db)
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

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var username = context.Request.Query["username"].FirstOrDefault();
        var months = ParseRangeInt(context.Request.Query["months"].FirstOrDefault(), 2, 1, 12);
        var limit = ParseRangeInt(context.Request.Query["limit"].FirstOrDefault(), 50, 1, 500);

        var rows = (await db.QueryAsync<dynamic>(@"
SELECT id,
       username,
       title,
       message,
       kind,
       status,
       payload,
       created_at,
       read_at,
       COALESCE(
           payload->>'message_html',
           payload->>'html',
           payload->>'content_html',
           payload->>'body_html',
           payload->>'description_html'
       ) AS message_html,
       COALESCE(
           payload->>'message_plain',
           payload->>'message_text',
           payload->>'content_text',
           payload->>'description',
           NULLIF(message, '')
       ) AS message_plain
FROM public.tbl_t_user_notification
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND created_at >= now() - make_interval(months => @Months)
  AND (@Username IS NULL OR @Username='' OR lower(username)=lower(@Username))
ORDER BY created_at DESC, id DESC
LIMIT @Limit", new
        {
            CompanyId = company.Id,
            Username = username,
            Months = months,
            Limit = limit
        })).ToList();

        return Results.Ok(new
        {
            message = "ok",
            total = rows.Count,
            data = rows
        });
    }

    private static string ResolveNotificationMessage(string? message, JsonElement? payload)
    {
        var plainMessage = (message ?? string.Empty).Trim();
        if (!string.IsNullOrWhiteSpace(plainMessage))
        {
            return plainMessage;
        }

        var fromPayload = TryGetNotificationPayloadString(
            payload,
            "message_plain",
            "message_text",
            "content_text",
            "description",
            "message",
            "message_html",
            "html",
            "content_html",
            "body_html",
            "description_html"
        );

        if (string.IsNullOrWhiteSpace(fromPayload))
        {
            return string.Empty;
        }

        return StripHtml(fromPayload);
    }

    private static string? TryGetNotificationPayloadString(JsonElement? payload, params string[] keys)
    {
        if (!payload.HasValue || payload.Value.ValueKind != JsonValueKind.Object || keys is null)
        {
            return null;
        }

        foreach (var key in keys)
        {
            if (!payload.Value.TryGetProperty(key, out var value))
            {
                continue;
            }

            var raw = value.ValueKind == JsonValueKind.String ? value.GetString() : value.ToString();
            if (!string.IsNullOrWhiteSpace(raw))
            {
                return raw.Trim();
            }
        }

        return null;
    }

    private static string StripHtml(string value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return string.Empty;
        }

        var noTags = Regex.Replace(value, "<[^>]+>", " ");
        var decoded = System.Net.WebUtility.HtmlDecode(noTags);
        return Regex.Replace(decoded, "\\s+", " ").Trim();
    }

    public static async Task<IResult> AdminUpsertFtwManualAccessAsync(HttpContext context, FtwManualAccessUpsertRequest request, NpgsqlDataSource db)
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

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var username = (request.Username ?? string.Empty).Trim();
        if (string.IsNullOrWhiteSpace(username))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "username is required");
        }

        var userExists = await db.QuerySingleOrDefaultAsync<int?>(@"
SELECT id
FROM public.tbl_m_user
WHERE deleted_at IS NULL
  AND is_active=true
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
LIMIT 1", new
        {
            CompanyId = company.Id,
            Username = username
        });

        if (!userExists.HasValue)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Username not found in company.");
        }

        var existing = await db.QuerySingleOrDefaultAsync<FtwManualAccessRow>(@"
SELECT id, company_id, username, employee_id, nik, require_p5m, is_active, note
FROM public.tbl_m_ftw_manual_access
WHERE company_id=@CompanyId
  AND deleted_at IS NULL
  AND lower(username)=lower(@Username)
LIMIT 1", new
        {
            CompanyId = company.Id,
            Username = username
        });

        if (existing is null)
        {
            await db.ExecuteAsync(@"
INSERT INTO public.tbl_m_ftw_manual_access
(company_id, username, employee_id, nik, require_p5m, is_active, note, created_by, updated_by, created_at, updated_at)
VALUES
(@CompanyId, @Username, @EmployeeId, @Nik, @RequireP5m, @IsActive, @Note, @UserId, @UserId, now(), now())", new
            {
                CompanyId = company.Id,
                Username = username,
                EmployeeId = request.EmployeeId,
                Nik = string.IsNullOrWhiteSpace(request.Nik) ? null : request.Nik!.Trim(),
                RequireP5m = request.RequireP5m ?? true,
                IsActive = request.IsActive ?? true,
                Note = string.IsNullOrWhiteSpace(request.Note) ? null : request.Note!.Trim(),
                UserId = auth.UserId
            });
        }
        else
        {
            await db.ExecuteAsync(@"
UPDATE public.tbl_m_ftw_manual_access
SET employee_id=@EmployeeId,
    nik=@Nik,
    require_p5m=@RequireP5m,
    is_active=@IsActive,
    note=@Note,
    updated_by=@UserId,
    updated_at=now()
WHERE id=@Id", new
            {
                Id = existing.Id,
                EmployeeId = request.EmployeeId ?? existing.EmployeeId,
                Nik = request.Nik is null ? existing.Nik : request.Nik.Trim(),
                RequireP5m = request.RequireP5m ?? existing.RequireP5m,
                IsActive = request.IsActive ?? existing.IsActive,
                Note = request.Note is null ? existing.Note : request.Note.Trim(),
                UserId = auth.UserId
            });
        }

        var updated = await db.QuerySingleOrDefaultAsync<FtwManualAccessRow>(@"
SELECT id, company_id, username, employee_id, nik, require_p5m, is_active, note
FROM public.tbl_m_ftw_manual_access
WHERE company_id=@CompanyId
  AND deleted_at IS NULL
  AND lower(username)=lower(@Username)
LIMIT 1", new
        {
            CompanyId = company.Id,
            Username = username
        });

        return Results.Ok(new
        {
            message = "Successfully saved",
            data = updated
        });
    }

    public static async Task<IResult> AdminListFtwManualAccessAsync(HttpContext context, NpgsqlDataSource db)
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

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var keyword = context.Request.Query["keyword"].FirstOrDefault();
        var limit = ParseRangeInt(context.Request.Query["limit"].FirstOrDefault(), 100, 1, 500);
        var rows = (await db.QueryAsync<dynamic>(@"
SELECT id, company_id, username, employee_id, nik, require_p5m, is_active, note, created_at, updated_at
FROM public.tbl_m_ftw_manual_access
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND (
    @Keyword IS NULL OR @Keyword='' OR
    lower(username) LIKE lower(@KeywordLike) OR
    lower(COALESCE(nik, '')) LIKE lower(@KeywordLike)
  )
ORDER BY username ASC
LIMIT @Limit", new
        {
            CompanyId = company.Id,
            Keyword = keyword,
            KeywordLike = $"%{(keyword ?? string.Empty).Trim()}%",
            Limit = limit
        })).ToList();

        return Results.Ok(new
        {
            message = "ok",
            total = rows.Count,
            data = rows
        });
    }

    private static async Task<EmployeeSlimRow?> ResolveEmployeeByUserAsync(NpgsqlDataSource db, int companyId, int userId)
    {
        return await db.QuerySingleOrDefaultAsync<EmployeeSlimRow>(@"
SELECT id, company_id
FROM public.tbl_r_employee
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND user_id=@UserId
LIMIT 1", new
        {
            CompanyId = companyId,
            UserId = userId
        });
    }

    private static async Task<FtwManualEligibilityResult> CheckFtwManualEligibilityAsync(
        NpgsqlDataSource db,
        int companyId,
        AuthContext auth,
        EmployeeProfileRow? employee,
        string? nikOrUsername)
    {
        var username = string.IsNullOrWhiteSpace(auth.Username)
            ? (nikOrUsername ?? string.Empty).Trim()
            : auth.Username.Trim();
        var nik = !string.IsNullOrWhiteSpace(employee?.Nik)
            ? employee!.Nik!.Trim()
            : (nikOrUsername ?? string.Empty).Trim();
        var employeeId = employee?.Id ?? 0;

        var access = await FindActiveFtwManualAccessAsync(db, companyId, username, employeeId, nik);

        if (access is null)
        {
            return new FtwManualEligibilityResult(false, false, false, "Anda tidak masuk NIK FTW Manual.");
        }

        if (!access.RequireP5m)
        {
            return new FtwManualEligibilityResult(true, false, false, "Akses FTW Manual aktif.");
        }

        var checkpoint = await db.QuerySingleOrDefaultAsync<P5MCheckpointRow>(@"
SELECT id, company_id, employee_id, username, nik, record_date, score, max_score, percentage, source, submitted_at
FROM public.tbl_t_p5m_checkpoint
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND record_date=CURRENT_DATE
  AND (
    lower(username)=lower(@Username)
    OR (@EmployeeId > 0 AND employee_id=@EmployeeId)
  )
ORDER BY submitted_at DESC, id DESC
LIMIT 1", new
        {
            CompanyId = companyId,
            Username = username,
            EmployeeId = employeeId
        });

        if (checkpoint is null)
        {
            return new FtwManualEligibilityResult(false, true, false, "Anda harus isi P5M dulu sebelum mengisi FTW Manual.");
        }

        return new FtwManualEligibilityResult(true, true, true, "Akses FTW Manual aktif.");
    }

    private static async Task<FtwManualAccessRow?> FindActiveFtwManualAccessAsync(
        NpgsqlDataSource db,
        int companyId,
        string username,
        int employeeId,
        string nik)
    {
        if (!string.IsNullOrWhiteSpace(username))
        {
            var byUsername = await db.QuerySingleOrDefaultAsync<FtwManualAccessRow>(@"
SELECT id, company_id, username, employee_id, nik, require_p5m, is_active, note
FROM public.tbl_m_ftw_manual_access
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND is_active=true
  AND lower(username)=lower(@Username)
LIMIT 1", new
            {
                CompanyId = companyId,
                Username = username
            });

            if (byUsername is not null)
            {
                return byUsername;
            }
        }

        if (employeeId > 0)
        {
            var byEmployee = await db.QuerySingleOrDefaultAsync<FtwManualAccessRow>(@"
SELECT id, company_id, username, employee_id, nik, require_p5m, is_active, note
FROM public.tbl_m_ftw_manual_access
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND is_active=true
  AND employee_id=@EmployeeId
LIMIT 1", new
            {
                CompanyId = companyId,
                EmployeeId = employeeId
            });

            if (byEmployee is not null)
            {
                return byEmployee;
            }
        }

        if (!string.IsNullOrWhiteSpace(nik))
        {
            return await db.QuerySingleOrDefaultAsync<FtwManualAccessRow>(@"
SELECT id, company_id, username, employee_id, nik, require_p5m, is_active, note
FROM public.tbl_m_ftw_manual_access
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND is_active=true
  AND lower(nik)=lower(@Nik)
LIMIT 1", new
            {
                CompanyId = companyId,
                Nik = nik
            });
        }

        return null;
    }

    private static async Task UpsertP5MCheckpointRecordAsync(
        NpgsqlDataSource db,
        int companyId,
        int? employeeId,
        string username,
        string? nik,
        DateOnly recordDate,
        int? score,
        int? maxScore,
        int? percentage,
        string source,
        string? payload)
    {
        var normalizedUsername = (username ?? string.Empty).Trim();
        if (string.IsNullOrWhiteSpace(normalizedUsername))
        {
            return;
        }

        var updated = await db.ExecuteAsync(@"
UPDATE public.tbl_t_p5m_checkpoint
SET employee_id=@EmployeeId,
    nik=@Nik,
    score=@Score,
    max_score=@MaxScore,
    percentage=@Percentage,
    source=@Source,
    payload=@Payload::jsonb,
    submitted_at=now(),
    updated_at=now()
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
  AND record_date=@RecordDate", new
        {
            CompanyId = companyId,
            EmployeeId = employeeId,
            Username = normalizedUsername,
            Nik = string.IsNullOrWhiteSpace(nik) ? null : nik.Trim(),
            RecordDate = recordDate.ToDateTime(TimeOnly.MinValue).Date,
            Score = score,
            MaxScore = maxScore,
            Percentage = percentage,
            Source = source,
            Payload = payload
        });

        if (updated > 0)
        {
            return;
        }

        try
        {
            await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_p5m_checkpoint
(company_id, employee_id, username, nik, record_date, score, max_score, percentage, source, payload, submitted_at, created_at, updated_at)
VALUES
(@CompanyId, @EmployeeId, @Username, @Nik, @RecordDate, @Score, @MaxScore, @Percentage, @Source, @Payload::jsonb, now(), now(), now())", new
            {
                CompanyId = companyId,
                EmployeeId = employeeId,
                Username = normalizedUsername,
                Nik = string.IsNullOrWhiteSpace(nik) ? null : nik.Trim(),
                RecordDate = recordDate.ToDateTime(TimeOnly.MinValue).Date,
                Score = score,
                MaxScore = maxScore,
                Percentage = percentage,
                Source = source,
                Payload = payload
            });
        }
        catch (PostgresException ex) when (ex.SqlState == "23505")
        {
            await db.ExecuteAsync(@"
UPDATE public.tbl_t_p5m_checkpoint
SET employee_id=@EmployeeId,
    nik=@Nik,
    score=@Score,
    max_score=@MaxScore,
    percentage=@Percentage,
    source=@Source,
    payload=@Payload::jsonb,
    submitted_at=now(),
    updated_at=now()
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
  AND record_date=@RecordDate", new
            {
                CompanyId = companyId,
                EmployeeId = employeeId,
                Username = normalizedUsername,
                Nik = string.IsNullOrWhiteSpace(nik) ? null : nik.Trim(),
                RecordDate = recordDate.ToDateTime(TimeOnly.MinValue).Date,
                Score = score,
                MaxScore = maxScore,
                Percentage = percentage,
                Source = source,
                Payload = payload
            });
        }
    }

    private static bool IsStatusActive(object? status)
    {
        if (status is null || status == DBNull.Value)
        {
            return true;
        }
        if (status is bool b)
        {
            return b;
        }

        var text = status.ToString()?.Trim().ToLowerInvariant() ?? string.Empty;
        if (string.IsNullOrWhiteSpace(text))
        {
            return true;
        }
        if (text is "0" or "false" or "inactive" or "nonaktif" or "disabled")
        {
            return false;
        }
        return true;
    }

    private static int ToInt(object? value)
    {
        if (value is null || value == DBNull.Value)
        {
            return 0;
        }
        if (value is int i)
        {
            return i;
        }
        return int.TryParse(value.ToString(), out var parsed) ? parsed : 0;
    }

    private static DateTime? ToDateTime(object? value)
    {
        if (value is null || value == DBNull.Value)
        {
            return null;
        }
        if (value is DateTime dt)
        {
            return dt;
        }
        return DateTime.TryParse(value.ToString(), out var parsed) ? parsed : null;
    }

    private static P5MQuestionMeta ParseP5MQuestionMeta(string content, string questionText, int order)
    {
        var fallback = BuildFallbackMeta(questionText, order);
        if (string.IsNullOrWhiteSpace(content))
        {
            return fallback;
        }

        var raw = content.Trim();
        if (raw.StartsWith("{") && raw.EndsWith("}"))
        {
            try
            {
                using var doc = JsonDocument.Parse(raw);
                var root = doc.RootElement;

                var qType = root.TryGetProperty("question_type", out var qt) ? (qt.GetString() ?? fallback.QuestionType) : fallback.QuestionType;
                var weight = root.TryGetProperty("weight", out var w) && w.TryGetInt32(out var wi) ? wi : fallback.Weight;
                var isRequired = root.TryGetProperty("is_required", out var req) && req.ValueKind == JsonValueKind.True
                    ? true
                    : (root.TryGetProperty("is_required", out req) && req.ValueKind == JsonValueKind.False ? false : fallback.IsRequired);

                var options = fallback.Options;
                if (root.TryGetProperty("options", out var optionsEl) && optionsEl.ValueKind == JsonValueKind.Array)
                {
                    options = optionsEl.EnumerateArray()
                        .Select(x => x.GetString() ?? string.Empty)
                        .Where(x => !string.IsNullOrWhiteSpace(x))
                        .ToArray();
                }

                var scoreMap = fallback.OptionScores;
                if (root.TryGetProperty("option_scores", out var scoreEl) && scoreEl.ValueKind == JsonValueKind.Object)
                {
                    scoreMap = new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase);
                    foreach (var p in scoreEl.EnumerateObject())
                    {
                        if (p.Value.TryGetInt32(out var sv))
                        {
                            scoreMap[p.Name] = sv;
                        }
                    }
                }

                return new P5MQuestionMeta(qType, options, weight, isRequired, scoreMap);
            }
            catch
            {
                return fallback;
            }
        }

        var typeMatch = Regex.Match(raw, @"Tipe:\s*([^;]+)", RegexOptions.IgnoreCase);
        var weightMatch = Regex.Match(raw, @"Bobot:\s*(\d+)", RegexOptions.IgnoreCase);
        var optionsMatch = Regex.Match(raw, @"Opsi:\s*(\[[^\]]*\])", RegexOptions.IgnoreCase);
        var reqMatch = Regex.Match(raw, @"Wajib:\s*([^;]+)", RegexOptions.IgnoreCase);

        var parsedType = typeMatch.Success ? typeMatch.Groups[1].Value.Trim().ToLowerInvariant() : fallback.QuestionType;
        var parsedWeight = weightMatch.Success && int.TryParse(weightMatch.Groups[1].Value.Trim(), out var bw) ? bw : fallback.Weight;
        var parsedRequired = reqMatch.Success
            ? reqMatch.Groups[1].Value.Trim().Equals("ya", StringComparison.OrdinalIgnoreCase) || reqMatch.Groups[1].Value.Trim().Equals("true", StringComparison.OrdinalIgnoreCase)
            : fallback.IsRequired;
        var parsedOptions = fallback.Options;
        if (optionsMatch.Success)
        {
            try
            {
                var arr = JsonSerializer.Deserialize<string[]>(optionsMatch.Groups[1].Value.Trim());
                if (arr is { Length: > 0 })
                {
                    parsedOptions = arr;
                }
            }
            catch
            {
                // Keep fallback options when legacy metadata is malformed.
            }
        }

        return new P5MQuestionMeta(parsedType, parsedOptions, parsedWeight, parsedRequired, fallback.OptionScores);
    }

    private static P5MQuestionMeta BuildFallbackMeta(string questionText, int order)
    {
        var lower = (questionText ?? string.Empty).ToLowerInvariant();
        if (lower.Contains("catatan p5m") || lower.Contains("sebutkan"))
        {
            return new P5MQuestionMeta(
                "text",
                Array.Empty<string>(),
                10,
                true,
                new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase)
            );
        }

        if (lower.Contains("apd wajib"))
        {
            var options = new[] { "Lengkap", "Kurang 1 item", "Tidak lengkap" };
            return new P5MQuestionMeta(
                "radio",
                options,
                15,
                true,
                new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase)
                {
                    [options[0]] = 15,
                    [options[1]] = 5,
                    [options[2]] = 0
                }
            );
        }

        string[] defaultOptions;
        if (lower.Contains("pre-start"))
        {
            defaultOptions = new[] { "Sudah lengkap", "Sebagian", "Belum" };
        }
        else if (lower.Contains("hazard"))
        {
            defaultOptions = new[] { "Aman", "Ada potensi hazard", "Tidak aman" };
        }
        else
        {
            defaultOptions = new[] { "Ya, fit", "Ragu-ragu", "Tidak fit" };
        }

        var weight = order <= 3 ? 25 : 25;
        return new P5MQuestionMeta(
            "radio",
            defaultOptions,
            weight,
            true,
            new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase)
            {
                [defaultOptions[0]] = 25,
                [defaultOptions[1]] = 10,
                [defaultOptions[2]] = 0
            }
        );
    }

    private static int ParseRangeInt(string? raw, int defaultValue, int min, int max)
    {
        if (!int.TryParse(raw, out var value))
        {
            return defaultValue;
        }
        if (value < min)
        {
            return min;
        }
        if (value > max)
        {
            return max;
        }
        return value;
    }

    private sealed record FtwManualEligibilityResult(bool Allowed, bool RequireP5m, bool P5mToday, string Message);

    private sealed class FtwManualAccessRow
    {
        public long Id { get; set; }
        public int CompanyId { get; set; }
        public string Username { get; set; } = string.Empty;
        public int? EmployeeId { get; set; }
        public string? Nik { get; set; }
        public bool RequireP5m { get; set; }
        public bool IsActive { get; set; }
        public string? Note { get; set; }
    }

    private sealed class P5MCheckpointRow
    {
        public long Id { get; set; }
        public int CompanyId { get; set; }
        public int? EmployeeId { get; set; }
        public string? Username { get; set; }
        public string? Nik { get; set; }
        public DateOnly RecordDate { get; set; }
        public int? Score { get; set; }
        public int? MaxScore { get; set; }
        public int? Percentage { get; set; }
        public string? Source { get; set; }
        public DateTime SubmittedAt { get; set; }
    }

    private sealed record P5MQuestionMeta(
        string QuestionType,
        string[] Options,
        int Weight,
        bool IsRequired,
        Dictionary<string, int> OptionScores
    );
}
