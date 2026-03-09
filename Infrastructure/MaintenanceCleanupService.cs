using Dapper;
using Microsoft.Extensions.Options;
using Npgsql;

namespace SaveraApi.Infrastructure;

public sealed class MaintenanceCleanupService : BackgroundService
{
    private readonly AppOptions _options;
    private readonly ILogger<MaintenanceCleanupService> _logger;
    private readonly NpgsqlDataSource _db;

    public MaintenanceCleanupService(
        IOptions<AppOptions> options,
        ILogger<MaintenanceCleanupService> logger,
        NpgsqlDataSource db)
    {
        _options = options.Value;
        _logger = logger;
        _db = db;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        if (!_options.MaintenanceCleanupEnabled)
        {
            _logger.LogInformation("MAINT cleanup_disabled");
            return;
        }

        var interval = TimeSpan.FromMinutes(Math.Max(5, _options.MaintenanceIntervalMinutes));
        _logger.LogInformation(
            "MAINT cleanup_started retentionDays={RetentionDays} intervalMinutes={IntervalMinutes} uploadRoot={UploadRoot}",
            _options.MaintenanceRetentionDays,
            (int)interval.TotalMinutes,
            _options.UploadRoot);

        using var timer = new PeriodicTimer(interval);
        await RunCleanupOnceAsync(stoppingToken);

        while (!stoppingToken.IsCancellationRequested && await timer.WaitForNextTickAsync(stoppingToken))
        {
            await RunCleanupOnceAsync(stoppingToken);
        }
    }

    private async Task RunCleanupOnceAsync(CancellationToken cancellationToken)
    {
        var retentionDays = Math.Max(1, _options.MaintenanceRetentionDays);
        var cutoff = DateTime.UtcNow.AddDays(-retentionDays);

        try
        {
            _logger.LogInformation("MAINT cleanup_run_start cutoffUtc={CutoffUtc}", cutoff);

            var deletedUploadLog = await _db.ExecuteAsync(@"
DELETE FROM public.tbl_t_upload_log
WHERE created_at < @Cutoff", new { Cutoff = cutoff });

            var deletedQueue = await _db.ExecuteAsync(@"
DELETE FROM public.tbl_t_upload_file_queue
WHERE updated_at < @Cutoff
  AND status IN ('done', 'failed')", new { Cutoff = cutoff });

            var deletedNetworkProbe = await _db.ExecuteAsync(@"
DELETE FROM public.tbl_t_network_probe
WHERE created_at < @Cutoff", new { Cutoff = cutoff });

            var deletedGoogleActivity = await _db.ExecuteAsync(@"
DELETE FROM public.tbl_t_google_activity
WHERE created_at < @Cutoff", new { Cutoff = cutoff });

            var deletedExpiredTokens = await _db.ExecuteAsync(@"
DELETE FROM public.tbl_t_api_token
WHERE expires_at < @Cutoff", new { Cutoff = cutoff });

            var deletedFallbackFiles = DeleteOldFallbackFiles(_options.UploadRoot, cutoff);

            _logger.LogInformation(
                "MAINT cleanup_run_done deletedUploadLog={DeletedUploadLog} deletedQueue={DeletedQueue} deletedNetworkProbe={DeletedNetworkProbe} deletedGoogleActivity={DeletedGoogleActivity} deletedExpiredTokens={DeletedExpiredTokens} deletedFallbackFiles={DeletedFallbackFiles}",
                deletedUploadLog,
                deletedQueue,
                deletedNetworkProbe,
                deletedGoogleActivity,
                deletedExpiredTokens,
                deletedFallbackFiles);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "MAINT cleanup_run_failed");
        }
    }

    private int DeleteOldFallbackFiles(string uploadRoot, DateTime cutoffUtc)
    {
        var failedRoot = Path.Combine(uploadRoot, "failed_uploads");
        if (!Directory.Exists(failedRoot))
        {
            return 0;
        }

        var deleted = 0;
        foreach (var file in Directory.EnumerateFiles(failedRoot, "*.json", SearchOption.AllDirectories))
        {
            try
            {
                var info = new FileInfo(file);
                if (info.LastWriteTimeUtc < cutoffUtc)
                {
                    info.Delete();
                    deleted++;
                }
            }
            catch (Exception ex)
            {
                _logger.LogWarning(ex, "MAINT fallback_delete_failed path={Path}", file);
            }
        }

        return deleted;
    }
}
