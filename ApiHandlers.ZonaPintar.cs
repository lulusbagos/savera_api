using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> GetZonaPintarArticlesAsync(HttpContext context, NpgsqlDataSource db)
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

        var limit = ParseRangeIntZona(context.Request.Query["limit"].FirstOrDefault(), 20, 1, 200);

        try
        {
            var rows = (await db.QueryAsync<ZonaPintarArticleRow>(@"
SELECT id, company_id, title, description, content, category, image_url, article_link, sort_order, is_active, published_at, created_at, updated_at
FROM public.tbl_t_zona_pintar_article
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND is_active=true
ORDER BY sort_order ASC, COALESCE(published_at, created_at) DESC, id DESC
LIMIT @Limit", new
            {
                CompanyId = company.Id,
                Limit = limit
            })).ToList();

            DateTime? updatedAt = null;
            foreach (var row in rows)
            {
                var candidate = row.UpdatedAt ?? row.CreatedAt ?? row.PublishedAt;
                if (!candidate.HasValue)
                {
                    continue;
                }

                if (!updatedAt.HasValue || candidate.Value > updatedAt.Value)
                {
                    updatedAt = candidate.Value;
                }
            }

            var data = rows.Select(x => new
            {
                id = x.Id,
                title = x.Title ?? string.Empty,
                description = x.Description ?? string.Empty,
                content = x.Content ?? string.Empty,
                category = x.Category ?? "Umum",
                image_url = x.ImageUrl ?? string.Empty,
                article_link = x.ArticleLink ?? string.Empty,
                sort_order = x.SortOrder ?? 100,
                is_active = x.IsActive,
                published_at = x.PublishedAt,
                created_at = x.CreatedAt,
                updated_at = x.UpdatedAt
            }).ToList();

            return Results.Ok(new
            {
                message = "ok",
                source = "postgres",
                total = data.Count,
                updated_at = updatedAt,
                data
            });
        }
        catch (PostgresException ex) when (ex.SqlState == "42P01")
        {
            return Results.Ok(new
            {
                message = "Zona Pintar table not ready. Apply SQL migration first.",
                source = "fallback_empty",
                total = 0,
                data = Array.Empty<object>()
            });
        }
    }

    public static async Task<IResult> AdminUpsertZonaPintarArticleAsync(
        HttpContext context,
        ZonaPintarArticleUpsertRequest request,
        NpgsqlDataSource db)
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
        var content = (request.Content ?? string.Empty).Trim();
        if (string.IsNullOrWhiteSpace(title) || string.IsNullOrWhiteSpace(content))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "title and content are required");
        }

        var description = string.IsNullOrWhiteSpace(request.Description) ? null : request.Description!.Trim();
        var category = string.IsNullOrWhiteSpace(request.Category) ? "Umum" : request.Category!.Trim();
        var imageUrl = string.IsNullOrWhiteSpace(request.ImageUrl) ? null : request.ImageUrl!.Trim();
        var articleLink = string.IsNullOrWhiteSpace(request.ArticleLink) ? null : request.ArticleLink!.Trim();
        var sortOrder = request.SortOrder.GetValueOrDefault(100);
        if (sortOrder < 0)
        {
            sortOrder = 0;
        }
        if (sortOrder > 1_000_000)
        {
            sortOrder = 1_000_000;
        }
        var isActive = request.IsActive ?? true;
        var publishedAt = ExtractDateTime(request.PublishedAt) ?? DateTime.Now;

        if (request.Id.GetValueOrDefault() > 0)
        {
            var affected = await db.ExecuteAsync(@"
UPDATE public.tbl_t_zona_pintar_article
SET title=@Title,
    description=@Description,
    content=@Content,
    category=@Category,
    image_url=@ImageUrl,
    article_link=@ArticleLink,
    sort_order=@SortOrder,
    is_active=@IsActive,
    published_at=@PublishedAt,
    updated_by=@UpdatedBy,
    updated_at=now()
WHERE id=@Id
  AND company_id=@CompanyId
  AND deleted_at IS NULL", new
            {
                Id = request.Id!.Value,
                CompanyId = company.Id,
                Title = title,
                Description = description,
                Content = content,
                Category = category,
                ImageUrl = imageUrl,
                ArticleLink = articleLink,
                SortOrder = sortOrder,
                IsActive = isActive,
                PublishedAt = publishedAt,
                UpdatedBy = auth.UserId
            });

            if (affected <= 0)
            {
                return ErrorMessage(StatusCodes.Status404NotFound, "Article not found.");
            }

            var updated = await db.QuerySingleOrDefaultAsync<ZonaPintarArticleRow>(@"
SELECT id, company_id, title, description, content, category, image_url, article_link, sort_order, is_active, published_at, created_at, updated_at
FROM public.tbl_t_zona_pintar_article
WHERE id=@Id
  AND company_id=@CompanyId
  AND deleted_at IS NULL", new
            {
                Id = request.Id.Value,
                CompanyId = company.Id
            });

            return Results.Ok(new
            {
                message = "Successfully updated",
                data = updated
            });
        }

        var insertedId = await db.QuerySingleAsync<long>(@"
INSERT INTO public.tbl_t_zona_pintar_article
(company_id, title, description, content, category, image_url, article_link, sort_order, is_active, published_at, created_by, updated_by, created_at, updated_at)
VALUES
(@CompanyId, @Title, @Description, @Content, @Category, @ImageUrl, @ArticleLink, @SortOrder, @IsActive, @PublishedAt, @CreatedBy, @UpdatedBy, now(), now())
RETURNING id", new
        {
            CompanyId = company.Id,
            Title = title,
            Description = description,
            Content = content,
            Category = category,
            ImageUrl = imageUrl,
            ArticleLink = articleLink,
            SortOrder = sortOrder,
            IsActive = isActive,
            PublishedAt = publishedAt,
            CreatedBy = auth.UserId,
            UpdatedBy = auth.UserId
        });

        var inserted = await db.QuerySingleOrDefaultAsync<ZonaPintarArticleRow>(@"
SELECT id, company_id, title, description, content, category, image_url, article_link, sort_order, is_active, published_at, created_at, updated_at
FROM public.tbl_t_zona_pintar_article
WHERE id=@Id
  AND company_id=@CompanyId
  AND deleted_at IS NULL", new
        {
            Id = insertedId,
            CompanyId = company.Id
        });

        return Results.Ok(new
        {
            message = "Successfully created",
            data = inserted
        });
    }

    public static async Task<IResult> AdminListZonaPintarArticlesAsync(HttpContext context, NpgsqlDataSource db)
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

        var keyword = (context.Request.Query["keyword"].FirstOrDefault() ?? string.Empty).Trim();
        var activeFilter = ParseNullableBoolZona(context.Request.Query["active"].FirstOrDefault());
        var limit = ParseRangeIntZona(context.Request.Query["limit"].FirstOrDefault(), 100, 1, 500);

        var rows = (await db.QueryAsync<ZonaPintarArticleRow>(@"
SELECT id, company_id, title, description, content, category, image_url, article_link, sort_order, is_active, published_at, created_at, updated_at
FROM public.tbl_t_zona_pintar_article
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND (@ActiveFilter IS NULL OR is_active=@ActiveFilter)
  AND (
      @Keyword = '' OR
      lower(title) LIKE lower(@KeywordLike) OR
      lower(COALESCE(description, '')) LIKE lower(@KeywordLike) OR
      lower(COALESCE(category, '')) LIKE lower(@KeywordLike)
  )
ORDER BY sort_order ASC, COALESCE(published_at, created_at) DESC, id DESC
LIMIT @Limit", new
        {
            CompanyId = company.Id,
            ActiveFilter = activeFilter,
            Keyword = keyword,
            KeywordLike = $"%{keyword}%",
            Limit = limit
        })).ToList();

        return Results.Ok(new
        {
            message = "ok",
            total = rows.Count,
            data = rows
        });
    }

    private static int ParseRangeIntZona(string? raw, int defaultValue, int min, int max)
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

    private static bool? ParseNullableBoolZona(string? raw)
    {
        if (string.IsNullOrWhiteSpace(raw))
        {
            return null;
        }

        var normalized = raw.Trim().ToLowerInvariant();
        if (normalized is "1" or "true" or "yes" or "on")
        {
            return true;
        }
        if (normalized is "0" or "false" or "no" or "off")
        {
            return false;
        }
        return null;
    }

    private sealed class ZonaPintarArticleRow
    {
        public long Id { get; set; }
        public int CompanyId { get; set; }
        public string? Title { get; set; }
        public string? Description { get; set; }
        public string? Content { get; set; }
        public string? Category { get; set; }
        public string? ImageUrl { get; set; }
        public string? ArticleLink { get; set; }
        public int? SortOrder { get; set; }
        public bool IsActive { get; set; }
        public DateTime? PublishedAt { get; set; }
        public DateTime? CreatedAt { get; set; }
        public DateTime? UpdatedAt { get; set; }
    }
}
