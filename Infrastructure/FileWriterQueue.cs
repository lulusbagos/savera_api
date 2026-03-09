using System.Text;
using Dapper;
using Microsoft.Extensions.Options;
using Npgsql;

namespace SaveraApi.Infrastructure;

public sealed record UploadFileTask(
    string RelativePath,
    string Content,
    string RequestType,
    string RequestKey,
    int EmployeeId,
    DateOnly RecordDate
);

internal sealed class UploadFileQueueRow
{
    public long Id { get; set; }
    public string RelativePath { get; set; } = string.Empty;
    public string Content { get; set; } = string.Empty;
    public string RequestType { get; set; } = string.Empty;
    public string RequestKey { get; set; } = string.Empty;
    public int EmployeeId { get; set; }
    public DateOnly RecordDate { get; set; }
    public int Attempts { get; set; }
    public int MaxAttempts { get; set; }
}

public sealed class FileWriterQueue : BackgroundService
{
    private readonly ILogger<FileWriterQueue> _logger;
    private readonly AppOptions _options;
    private readonly NpgsqlDataSource _db;
    private readonly TimeSpan _pollInterval;
    private readonly int _workerConcurrency;
    private long _enqueuedCount;
    private long _successWriteCount;
    private long _failedWriteCount;
    private string? _lastErrorMessage;
    private DateTimeOffset? _lastSuccessWriteAt;
    private DateTimeOffset? _lastFailedWriteAt;

    public FileWriterQueue(
        IOptions<AppOptions> options,
        ILogger<FileWriterQueue> logger,
        NpgsqlDataSource db)
    {
        _logger = logger;
        _options = options.Value;
        _db = db;
        _pollInterval = TimeSpan.FromMilliseconds(Math.Max(50, _options.FileQueuePollMs));
        _workerConcurrency = Math.Max(1, _options.FileQueueWorkerConcurrency);
    }

    public async ValueTask EnqueueAsync(UploadFileTask task, CancellationToken cancellationToken)
    {
        const string sql = @"
INSERT INTO public.tbl_t_upload_file_queue
(request_type, request_key, employee_id, record_date, relative_path, content,
 status, attempts, max_attempts, next_retry_at, created_at, updated_at)
VALUES
(@RequestType, @RequestKey, @EmployeeId, @RecordDate, @RelativePath, @Content,
 'pending', 0, @MaxAttempts, now(), now(), now())
ON CONFLICT (request_type, request_key, relative_path)
DO UPDATE SET
    content=EXCLUDED.content,
    employee_id=EXCLUDED.employee_id,
    record_date=EXCLUDED.record_date,
    status='pending',
    next_retry_at=now(),
    last_error=NULL,
    updated_at=now()";

        await _db.ExecuteAsync(sql, new
        {
            task.RequestType,
            task.RequestKey,
            task.EmployeeId,
            RecordDate = task.RecordDate.ToDateTime(TimeOnly.MinValue).Date,
            task.RelativePath,
            task.Content,
            MaxAttempts = Math.Max(1, _options.FileQueueMaxAttempts)
        });

        Interlocked.Increment(ref _enqueuedCount);
    }

    public async Task<object> SnapshotAsync()
    {
        const string sql = @"
SELECT status, COUNT(*)::bigint AS total
FROM public.tbl_t_upload_file_queue
WHERE status IN ('pending', 'processing', 'failed')
GROUP BY status";

        var rows = await _db.QueryAsync<(string Status, long Total)>(sql);
        long pending = 0;
        long processing = 0;
        long failedQueued = 0;

        foreach (var (status, total) in rows)
        {
            if (string.Equals(status, "pending", StringComparison.OrdinalIgnoreCase)) pending = total;
            if (string.Equals(status, "processing", StringComparison.OrdinalIgnoreCase)) processing = total;
            if (string.Equals(status, "failed", StringComparison.OrdinalIgnoreCase)) failedQueued = total;
        }

        return new
        {
            pending,
            processing,
            failed_queued = failedQueued,
            enqueued_count = Interlocked.Read(ref _enqueuedCount),
            success_write_count = Interlocked.Read(ref _successWriteCount),
            failed_write_count = Interlocked.Read(ref _failedWriteCount),
            last_success_write_at = _lastSuccessWriteAt,
            last_failed_write_at = _lastFailedWriteAt,
            last_error_message = _lastErrorMessage
        };
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        Directory.CreateDirectory(_options.UploadRoot);
        EnsureBaseUploadDirectories();

        var workers = Enumerable.Range(1, _workerConcurrency)
            .Select(workerNo => RunWorkerLoopAsync(workerNo, stoppingToken))
            .ToArray();

        await Task.WhenAll(workers);
    }

    private void EnsureBaseUploadDirectories()
    {
        var baseDirs = new[]
        {
            "data_activity",
            "data_sleep",
            "data_stress",
            "data_spo2",
            "data_heart_rate_max",
            "data_heart_rate_resting",
            "data_heart_rate_manual",
            "failed_uploads"
        };

        foreach (var dir in baseDirs)
        {
            Directory.CreateDirectory(Path.Combine(_options.UploadRoot, dir));
        }

        _logger.LogInformation("Upload directories ensured at root={UploadRoot}", _options.UploadRoot);
    }

    private async Task RunWorkerLoopAsync(int workerNo, CancellationToken stoppingToken)
    {
        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                var next = await DequeuePendingAsync(stoppingToken);
                if (next is null)
                {
                    await Task.Delay(_pollInterval, stoppingToken);
                    continue;
                }

                await ProcessQueueItemAsync(next, stoppingToken);
            }
            catch (OperationCanceledException) when (stoppingToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Unexpected upload queue worker error. worker={WorkerNo}", workerNo);
                await Task.Delay(TimeSpan.FromMilliseconds(500), stoppingToken);
            }
        }
    }

    private async Task<UploadFileQueueRow?> DequeuePendingAsync(CancellationToken cancellationToken)
    {
        const string sql = @"
WITH next_item AS (
  SELECT id
  FROM public.tbl_t_upload_file_queue
  WHERE status='pending'
    AND next_retry_at <= now()
  ORDER BY id
  LIMIT 1
  FOR UPDATE SKIP LOCKED
)
UPDATE public.tbl_t_upload_file_queue q
SET status='processing',
    attempts = attempts + 1,
    updated_at=now()
FROM next_item n
WHERE q.id = n.id
RETURNING q.id,
          q.relative_path,
          q.content,
          q.request_type,
          q.request_key,
          q.employee_id,
          q.record_date,
          q.attempts,
          q.max_attempts";

        await using var conn = await _db.OpenConnectionAsync(cancellationToken);
        return await conn.QuerySingleOrDefaultAsync<UploadFileQueueRow>(sql);
    }

    private async Task ProcessQueueItemAsync(UploadFileQueueRow task, CancellationToken cancellationToken)
    {
        try
        {
            var fullPath = Path.Combine(_options.UploadRoot, task.RelativePath);
            var directory = Path.GetDirectoryName(fullPath);
            if (!string.IsNullOrWhiteSpace(directory))
            {
                Directory.CreateDirectory(directory);
            }

            await File.WriteAllTextAsync(
                fullPath,
                task.Content,
                new UTF8Encoding(false),
                cancellationToken
            );

            await MarkDoneAsync(task.Id, cancellationToken);
            Interlocked.Increment(ref _successWriteCount);
            _lastSuccessWriteAt = DateTimeOffset.Now;
        }
        catch (Exception ex)
        {
            Interlocked.Increment(ref _failedWriteCount);
            _lastFailedWriteAt = DateTimeOffset.Now;
            _lastErrorMessage = ex.Message;

            await MarkFailedAsync(task, ex.Message, cancellationToken);
            _logger.LogError(
                ex,
                "Failed writing upload file. id={Id} type={RequestType} key={RequestKey} employee={EmployeeId} date={RecordDate}",
                task.Id,
                task.RequestType,
                task.RequestKey,
                task.EmployeeId,
                task.RecordDate
            );
        }
    }

    private async Task MarkDoneAsync(long id, CancellationToken cancellationToken)
    {
        const string sql = @"
UPDATE public.tbl_t_upload_file_queue
SET status='done',
    processed_at=now(),
    last_error=NULL,
    updated_at=now()
WHERE id=@Id";

        await _db.ExecuteAsync(sql, new { Id = id });
    }

    private async Task MarkFailedAsync(UploadFileQueueRow task, string errorMessage, CancellationToken cancellationToken)
    {
        var reachedMax = task.Attempts >= task.MaxAttempts;
        var safeError = string.IsNullOrWhiteSpace(errorMessage)
            ? "Unknown queue writer error"
            : (errorMessage.Length > 3000 ? errorMessage[..3000] : errorMessage);

        if (reachedMax)
        {
            const string failSql = @"
UPDATE public.tbl_t_upload_file_queue
SET status='failed',
    last_error=@LastError,
    updated_at=now()
WHERE id=@Id";

            await _db.ExecuteAsync(failSql, new
            {
                Id = task.Id,
                LastError = safeError
            });
            return;
        }

        var baseRetry = Math.Max(1, _options.FileQueueBaseRetrySeconds);
        var retrySeconds = Math.Min(300, baseRetry * (int)Math.Pow(2, Math.Max(0, task.Attempts - 1)));

        const string retrySql = @"
UPDATE public.tbl_t_upload_file_queue
SET status='pending',
    next_retry_at = now() + make_interval(secs => @RetrySeconds),
    last_error=@LastError,
    updated_at=now()
WHERE id=@Id";

        await _db.ExecuteAsync(retrySql, new
        {
            Id = task.Id,
            RetrySeconds = retrySeconds,
            LastError = safeError
        });
    }
}
