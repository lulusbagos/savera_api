using System.Threading.RateLimiting;
using System.IO.Compression;
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
