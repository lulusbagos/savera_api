using System.Threading.RateLimiting;
using System.IO.Compression;
using System.Text;
using Dapper;
using Microsoft.AspNetCore.Http.Features;
using Microsoft.Extensions.Options;
using Npgsql;
using SaveraApi;
using SaveraApi.Infrastructure;

DefaultTypeMap.MatchNamesWithUnderscores = true;

var builder = WebApplication.CreateBuilder(args);
var appConfig = builder.Configuration.GetSection("App").Get<AppOptions>() ?? new AppOptions();

builder.Services.Configure<AppOptions>(builder.Configuration.GetSection("App"));
builder.Services.AddEndpointsApiExplorer();
builder.Services.AddSwaggerGen();
builder.Services.AddRateLimiter(options =>
{
    options.GlobalLimiter = PartitionedRateLimiter.Create<HttpContext, string>(context =>
    {
        var remoteIp = context.Connection.RemoteIpAddress?.ToString() ?? "unknown";
        return RateLimitPartition.GetTokenBucketLimiter(remoteIp, _ => new TokenBucketRateLimiterOptions
        {
            TokenLimit = Math.Max(1, appConfig.RateLimitBurst),
            QueueProcessingOrder = QueueProcessingOrder.OldestFirst,
            QueueLimit = Math.Max(0, appConfig.RateLimitQueueLimit),
            ReplenishmentPeriod = TimeSpan.FromSeconds(1),
            TokensPerPeriod = Math.Max(1, appConfig.RateLimitTokenPerSecond),
            AutoReplenishment = true
        });
    });

    options.RejectionStatusCode = StatusCodes.Status429TooManyRequests;
    options.OnRejected = async (context, token) =>
    {
        context.HttpContext.Response.ContentType = "application/json";
        await context.HttpContext.Response.WriteAsJsonAsync(new
        {
            message = "Too many requests",
            trace_id = ApiHandlers.EnsureTraceId(context.HttpContext)
        }, cancellationToken: token);
    };
});

builder.Services.AddSingleton(sp =>
{
    var connectionString = builder.Configuration.GetConnectionString("Postgres")
        ?? throw new InvalidOperationException("ConnectionStrings:Postgres is required");
    return NpgsqlDataSource.Create(connectionString);
});

builder.Services.AddSingleton<FileWriterQueue>();
builder.Services.AddHostedService(sp => sp.GetRequiredService<FileWriterQueue>());

builder.Services.ConfigureHttpJsonOptions(options =>
{
    options.SerializerOptions.PropertyNamingPolicy = System.Text.Json.JsonNamingPolicy.SnakeCaseLower;
    options.SerializerOptions.DictionaryKeyPolicy = System.Text.Json.JsonNamingPolicy.SnakeCaseLower;
    options.SerializerOptions.DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull;
    options.SerializerOptions.NumberHandling = System.Text.Json.Serialization.JsonNumberHandling.AllowReadingFromString;
    options.SerializerOptions.Converters.Add(new FlexibleIntConverter());
    options.SerializerOptions.Converters.Add(new FlexibleNullableIntConverter());
    options.SerializerOptions.Converters.Add(new FlexibleDecimalConverter());
    options.SerializerOptions.Converters.Add(new FlexibleNullableDecimalConverter());
    options.SerializerOptions.Converters.Add(new FlexibleBoolConverter());
    options.SerializerOptions.Converters.Add(new FlexibleNullableBoolConverter());
});

var app = builder.Build();

app.UseSwagger();
app.UseSwaggerUI();

app.UseExceptionHandler(exceptionApp =>
{
    exceptionApp.Run(ApiHandlers.HandleUnhandledExceptionAsync);
});
app.UseRateLimiter();

app.Use(async (context, next) =>
{
    var traceId = ApiHandlers.EnsureTraceId(context);
    context.Response.Headers["X-Request-Id"] = traceId;
    await next();
});

app.Use(async (context, next) =>
{
    if (HttpMethods.IsPost(context.Request.Method) ||
        HttpMethods.IsPut(context.Request.Method) ||
        HttpMethods.IsPatch(context.Request.Method))
    {
        var appOptions = context.RequestServices.GetRequiredService<IOptions<AppOptions>>().Value;
        var maxBodyBytes = Math.Max(1024, appOptions.MaxRequestBodyBytes);

        if (context.Request.ContentLength is long length && length > maxBodyBytes)
        {
            context.Response.StatusCode = StatusCodes.Status413PayloadTooLarge;
            await context.Response.WriteAsJsonAsync(new
            {
                message = "Payload too large",
                max_request_body_bytes = maxBodyBytes,
                trace_id = ApiHandlers.EnsureTraceId(context)
            });
            return;
        }

        var bodyFeature = context.Features.Get<IHttpMaxRequestBodySizeFeature>();
        if (bodyFeature is not null && !bodyFeature.IsReadOnly)
        {
            bodyFeature.MaxRequestBodySize = maxBodyBytes;
        }
    }

    await next();
});

app.Use(async (context, next) =>
{
    var contentEncoding = context.Request.Headers.ContentEncoding.ToString();
    if (!string.IsNullOrWhiteSpace(contentEncoding)
        && contentEncoding.Contains("gzip", StringComparison.OrdinalIgnoreCase))
    {
        var originalBody = context.Request.Body;
        await using var gzipBody = new GZipStream(originalBody, CompressionMode.Decompress, leaveOpen: true);
        context.Request.Body = gzipBody;
        context.Request.ContentLength = null;
        context.Request.Headers.Remove("Content-Encoding");

        try
        {
            await next();
        }
        catch (InvalidDataException)
        {
            context.Response.StatusCode = StatusCodes.Status400BadRequest;
            await context.Response.WriteAsJsonAsync(new
            {
                message = "Invalid gzip request body",
                trace_id = ApiHandlers.EnsureTraceId(context)
            });
        }
        finally
        {
            context.Request.Body = originalBody;
        }

        return;
    }

    await next();
});

app.Use(async (context, next) =>
{
    if (!ApiHandlers.IsUploadEndpoint(context.Request.Path))
    {
        await next();
        return;
    }

    var traceId = ApiHandlers.EnsureTraceId(context);
    var logger = context.RequestServices.GetRequiredService<ILoggerFactory>().CreateLogger("UploadBadRequest");
    var bodySnippet = await ApiHandlers.ReadRequestBodySnippetAsync(context);
    var originalBody = context.Response.Body;
    await using var captureBody = new MemoryStream();
    context.Response.Body = captureBody;

    try
    {
        await next();
    }
    catch (BadHttpRequestException ex)
    {
        logger.LogWarning(ex,
            "Upload request rejected before handler. traceId={TraceId} path={Path} uploadKey={UploadKey} employeeId={EmployeeId} macAddress={MacAddress}",
            traceId,
            context.Request.Path.Value,
            ApiHandlers.ExtractJsonStringField(bodySnippet, "upload_key"),
            ApiHandlers.ExtractJsonStringField(bodySnippet, "employee_id"),
            ApiHandlers.ExtractJsonStringField(bodySnippet, "mac_address"));

        context.Response.Body = originalBody;
        context.Response.Clear();
        context.Response.StatusCode = StatusCodes.Status400BadRequest;
        context.Response.ContentType = "application/json";
        await context.Response.WriteAsJsonAsync(new
        {
            message = "Bad request before upload handler",
            detail = ex.Message,
            hint = ApiHandlers.BuildUploadBadRequestHint(bodySnippet),
            endpoint = context.Request.Path.Value,
            upload_key = ApiHandlers.ExtractJsonStringField(bodySnippet, "upload_key"),
            employee_id = ApiHandlers.ExtractJsonStringField(bodySnippet, "employee_id"),
            mac_address = ApiHandlers.ExtractJsonStringField(bodySnippet, "mac_address"),
            trace_id = traceId,
            request_body_snippet = bodySnippet
        });
        return;
    }
    catch (System.Text.Json.JsonException ex)
    {
        logger.LogWarning(ex,
            "Upload JSON rejected before handler. traceId={TraceId} path={Path} uploadKey={UploadKey} employeeId={EmployeeId} macAddress={MacAddress}",
            traceId,
            context.Request.Path.Value,
            ApiHandlers.ExtractJsonStringField(bodySnippet, "upload_key"),
            ApiHandlers.ExtractJsonStringField(bodySnippet, "employee_id"),
            ApiHandlers.ExtractJsonStringField(bodySnippet, "mac_address"));

        context.Response.Body = originalBody;
        context.Response.Clear();
        context.Response.StatusCode = StatusCodes.Status400BadRequest;
        context.Response.ContentType = "application/json";
        await context.Response.WriteAsJsonAsync(new
        {
            message = "Invalid JSON body for upload request",
            detail = ex.Message,
            hint = ApiHandlers.BuildUploadBadRequestHint(bodySnippet),
            endpoint = context.Request.Path.Value,
            upload_key = ApiHandlers.ExtractJsonStringField(bodySnippet, "upload_key"),
            employee_id = ApiHandlers.ExtractJsonStringField(bodySnippet, "employee_id"),
            mac_address = ApiHandlers.ExtractJsonStringField(bodySnippet, "mac_address"),
            trace_id = traceId,
            request_body_snippet = bodySnippet
        });
        return;
    }

    if (context.Response.StatusCode == StatusCodes.Status400BadRequest)
    {
        captureBody.Position = 0;
        var upstreamBody = await new StreamReader(captureBody, Encoding.UTF8, detectEncodingFromByteOrderMarks: false, leaveOpen: true).ReadToEndAsync();
        logger.LogWarning(
            "Upload request returned 400 before completion. traceId={TraceId} path={Path} uploadKey={UploadKey} employeeId={EmployeeId} macAddress={MacAddress} upstreamBody={UpstreamBody} bodySnippet={BodySnippet}",
            traceId,
            context.Request.Path.Value,
            ApiHandlers.ExtractJsonStringField(bodySnippet, "upload_key"),
            ApiHandlers.ExtractJsonStringField(bodySnippet, "employee_id"),
            ApiHandlers.ExtractJsonStringField(bodySnippet, "mac_address"),
            upstreamBody,
            bodySnippet);

        context.Response.Body = originalBody;
        context.Response.Clear();
        context.Response.StatusCode = StatusCodes.Status400BadRequest;
        context.Response.ContentType = "application/json";
        await context.Response.WriteAsJsonAsync(new
        {
            message = "Upload request rejected before handler",
            detail = string.IsNullOrWhiteSpace(upstreamBody) ? null : upstreamBody,
            hint = ApiHandlers.BuildUploadBadRequestHint(bodySnippet),
            endpoint = context.Request.Path.Value,
            upload_key = ApiHandlers.ExtractJsonStringField(bodySnippet, "upload_key"),
            employee_id = ApiHandlers.ExtractJsonStringField(bodySnippet, "employee_id"),
            mac_address = ApiHandlers.ExtractJsonStringField(bodySnippet, "mac_address"),
            trace_id = traceId,
            request_body_snippet = bodySnippet
        });
        return;
    }

    captureBody.Position = 0;
    context.Response.Body = originalBody;
    await captureBody.CopyToAsync(originalBody);
});

app.MapGet("/", () => Results.Ok(new { message = "Savera ASP.NET API Ready" }));

var api = app.MapGroup("/api");
api.MapGet("/", () => Results.Ok(new { message = "API Server Ready" }));
api.MapGet("/health", ApiHandlers.HealthAsync);
api.MapGet("/health/detail", ApiHandlers.HealthDetailAsync);

api.MapPost("/login", ApiHandlers.LoginAsync);
api.MapPost("/login/diagnostics", ApiHandlers.LoginDiagnosticsAsync);
api.MapPost("/logout", ApiHandlers.LogoutAsync);
api.MapGet("/profile", ApiHandlers.ProfileAsync);
api.MapPost("/profile/password", ApiHandlers.ChangePasswordAsync);

api.MapMethods("/avatar", new[] { "GET" }, ApiHandlers.GetAvatarAsync);
api.MapPost("/avatar", ApiHandlers.UploadAvatarAsync);

api.MapGet("/device/{mac}", ApiHandlers.GetDeviceAsync);
api.MapGet("/device/{mac}/auth-key", ApiHandlers.GetDeviceAuthKeyAsync);
api.MapPost("/device/{mac}/auth-key", ApiHandlers.UpdateDeviceAuthKeyAsync);
api.MapGet("/route-config", ApiHandlers.GetRouteConfigEndpointAsync);
api.MapPost("/route-config", ApiHandlers.UpdateRouteConfigEndpointAsync);

api.MapPost("/summary", ApiHandlers.SummaryAsync);
api.MapPost("/detail", ApiHandlers.DetailAsync);
api.MapGet("/upload-status", ApiHandlers.UploadStatusAsync);
api.MapGet("/upload-status/{uploadKey}", ApiHandlers.UploadStatusByKeyAsync);

api.MapGet("/ticket/{id:int}", ApiHandlers.TicketAsync);
api.MapGet("/ranking/{id:int}", ApiHandlers.RankingAsync);
api.MapPost("/leave", ApiHandlers.LeaveAsync);
api.MapGet("/banner", ApiHandlers.BannerAsync);

api.MapPost("/fit-to-work/manual", ApiHandlers.FtwManualAsync);
api.MapPost("/fit-to-work", ApiHandlers.FtwManualAsync);
api.MapPost("/fatigue/manual", ApiHandlers.FtwManualAsync);
api.MapPost("/fatigue", ApiHandlers.FtwManualAsync);
api.MapPost("/p5m/manual", ApiHandlers.FtwManualAsync);
api.MapGet("/p5m/questions", ApiHandlers.GetP5MQuestionsAsync);
api.MapGet("/ftw-manual/eligibility", ApiHandlers.GetFtwManualEligibilityAsync);
api.MapPost("/p5m/checkpoint", ApiHandlers.UpsertP5MCheckpointAsync);
api.MapGet("/p5m/checkpoint/today", ApiHandlers.GetTodayP5MCheckpointAsync);
api.MapGet("/zona-pintar/articles", ApiHandlers.GetZonaPintarArticlesAsync);
api.MapGet("/notifications", ApiHandlers.GetMyNotificationsAsync);
api.MapPost("/notifications/{id:long}/read", ApiHandlers.MarkMyNotificationReadAsync);
api.MapPost("/admin/zona-pintar/articles", ApiHandlers.AdminUpsertZonaPintarArticleAsync);
api.MapGet("/admin/zona-pintar/articles", ApiHandlers.AdminListZonaPintarArticlesAsync);
api.MapPost("/admin/notifications", ApiHandlers.AdminCreateNotificationAsync);
api.MapGet("/admin/notifications", ApiHandlers.AdminListNotificationsAsync);
api.MapPost("/admin/ftw-manual-access", ApiHandlers.AdminUpsertFtwManualAccessAsync);
api.MapGet("/admin/ftw-manual-access", ApiHandlers.AdminListFtwManualAccessAsync);
api.MapPost("/network-probe", ApiHandlers.NetworkProbeAsync);
api.MapPost("/google-activity", ApiHandlers.GoogleActivityAsync);
api.MapGet("/admin/monitoring", ApiHandlers.AdminMonitoringAsync);
api.MapGet("/admin/upload-failures", ApiHandlers.AdminUploadFailuresAsync);

app.MapGet("/image/{**path}", ApiHandlers.GetImageAsync);

app.Run();
