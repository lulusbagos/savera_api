using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> GetRouteConfigEndpointAsync(HttpContext context, NpgsqlDataSource db)
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

        var routeConfig = await GetRouteConfigAsync(db, company.Id);
        if (routeConfig is null)
        {
            return Results.Ok(new
            {
                company_id = company.Id,
                primary_base_url = (string?)null,
                secondary_base_url = (string?)null,
                local_base_url = (string?)null,
                local_ip = (string?)null,
                local_port = (string?)null,
                sleep_rest_bonus_enabled = true
            });
        }

        return Results.Ok(new
        {
            company_id = company.Id,
            primary_base_url = routeConfig.PrimaryBaseUrl,
            secondary_base_url = routeConfig.SecondaryBaseUrl,
            local_base_url = routeConfig.LocalBaseUrl,
            local_ip = routeConfig.LocalIp,
            local_port = routeConfig.LocalPort,
            sleep_rest_bonus_enabled = routeConfig.SleepRestBonusEnabled ?? true
        });
    }

    public static async Task<IResult> UpdateRouteConfigEndpointAsync(
        HttpContext context,
        ApiRouteUpdateRequest request,
        NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        if (!IsAdmin(auth.Role))
        {
            return ErrorMessage(StatusCodes.Status403Forbidden, "Only admin can update route config.");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var primaryBaseUrl = NormalizeRouteValue(request.PrimaryBaseUrl);
        var secondaryBaseUrl = NormalizeRouteValue(request.SecondaryBaseUrl);
        var localBaseUrl = NormalizeRouteValue(request.LocalBaseUrl);
        var localIp = NormalizeRouteValue(request.LocalIp);
        var localPort = NormalizeRouteValue(request.LocalPort);
        var sleepRestBonusEnabled = request.SleepRestBonusEnabled ?? true;
        var isActive = request.IsActive ?? true;

        if (isActive && !HasAnyRouteValue(primaryBaseUrl, secondaryBaseUrl, localBaseUrl, localIp))
        {
            return ErrorMessage(
                StatusCodes.Status422UnprocessableEntity,
                "At least one route must be filled when route config is active.");
        }

        await using var conn = await db.OpenConnectionAsync(context.RequestAborted);
        await using var tx = await conn.BeginTransactionAsync(context.RequestAborted);

        var existingId = await conn.QuerySingleOrDefaultAsync<long?>(@"
SELECT id
FROM public.tbl_m_api_route
WHERE company_id=@CompanyId
  AND deleted_at IS NULL
LIMIT 1", new { CompanyId = company.Id }, tx);

        if (existingId.HasValue)
        {
            await conn.ExecuteAsync(@"
UPDATE public.tbl_m_api_route
SET primary_base_url=@PrimaryBaseUrl,
    secondary_base_url=@SecondaryBaseUrl,
    local_base_url=@LocalBaseUrl,
    local_ip=@LocalIp,
    local_port=@LocalPort,
    sleep_rest_bonus_enabled=@SleepRestBonusEnabled,
    is_active=@IsActive,
    updated_by=@UpdatedBy,
    updated_at=now()
WHERE id=@Id", new
            {
                Id = existingId.Value,
                PrimaryBaseUrl = primaryBaseUrl,
                SecondaryBaseUrl = secondaryBaseUrl,
                LocalBaseUrl = localBaseUrl,
                LocalIp = localIp,
                LocalPort = localPort,
                SleepRestBonusEnabled = sleepRestBonusEnabled,
                IsActive = isActive,
                UpdatedBy = auth.UserId
            }, tx);
        }
        else
        {
            await conn.ExecuteAsync(@"
INSERT INTO public.tbl_m_api_route
(company_id, primary_base_url, secondary_base_url, local_base_url, local_ip, local_port, sleep_rest_bonus_enabled, is_active, created_by, updated_by, created_at, updated_at)
VALUES
(@CompanyId, @PrimaryBaseUrl, @SecondaryBaseUrl, @LocalBaseUrl, @LocalIp, @LocalPort, @SleepRestBonusEnabled, @IsActive, @CreatedBy, @UpdatedBy, now(), now())", new
            {
                CompanyId = company.Id,
                PrimaryBaseUrl = primaryBaseUrl,
                SecondaryBaseUrl = secondaryBaseUrl,
                LocalBaseUrl = localBaseUrl,
                LocalIp = localIp,
                LocalPort = localPort,
                SleepRestBonusEnabled = sleepRestBonusEnabled,
                IsActive = isActive,
                CreatedBy = auth.UserId,
                UpdatedBy = auth.UserId
            }, tx);
        }

        await tx.CommitAsync(context.RequestAborted);

        var routeConfig = await GetRouteConfigAsync(db, company.Id);
        return Results.Ok(new
        {
            message = "Successfully updated",
            data = new
            {
                company_id = company.Id,
                primary_base_url = routeConfig?.PrimaryBaseUrl,
                secondary_base_url = routeConfig?.SecondaryBaseUrl,
                local_base_url = routeConfig?.LocalBaseUrl,
                local_ip = routeConfig?.LocalIp,
                local_port = routeConfig?.LocalPort,
                sleep_rest_bonus_enabled = routeConfig?.SleepRestBonusEnabled ?? true
            }
        });
    }

    private static string? NormalizeRouteValue(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }
        return value.Trim();
    }

    private static bool HasAnyRouteValue(
        string? primaryBaseUrl,
        string? secondaryBaseUrl,
        string? localBaseUrl,
        string? localIp)
    {
        return !string.IsNullOrWhiteSpace(primaryBaseUrl)
               || !string.IsNullOrWhiteSpace(secondaryBaseUrl)
               || !string.IsNullOrWhiteSpace(localBaseUrl)
               || !string.IsNullOrWhiteSpace(localIp);
    }
}
