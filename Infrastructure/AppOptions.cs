namespace SaveraApi.Infrastructure;

public sealed class AppOptions
{
    public string UploadRoot { get; set; } = @"D:\4. PROJECT\6. Android\API\file savera\storage\app";
    public string AdminImageBaseUrl { get; set; } = "https://adminsavera.indexim.id/image/";
    public int TokenLifetimeHours { get; set; } = 168;
    public long MaxRequestBodyBytes { get; set; } = 5 * 1024 * 1024;
    public int RateLimitTokenPerSecond { get; set; } = 300;
    public int RateLimitBurst { get; set; } = 1200;
    public int RateLimitQueueLimit { get; set; } = 5000;
    public int FileQueuePollMs { get; set; } = 200;
    public int FileQueueMaxAttempts { get; set; } = 5;
    public int FileQueueBaseRetrySeconds { get; set; } = 2;
    public int FileQueueWorkerConcurrency { get; set; } = 4;
    public string? PasswordDecryptKey { get; set; }
}
