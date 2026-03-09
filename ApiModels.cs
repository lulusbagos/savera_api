using System.Text.Json;
using System.Text.Json.Serialization;

namespace SaveraApi;

public sealed class LoginRequest
{
    public string Email { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
    public string? AppVersion { get; set; }
}

public sealed class ChangePasswordRequest
{
    public string OldPassword { get; set; } = string.Empty;
    public string NewPassword { get; set; } = string.Empty;
    public string ConfirmPassword { get; set; } = string.Empty;
}

public sealed class LeaveRequest
{
    public int EmployeeId { get; set; }
    public string Type { get; set; } = string.Empty;
    public string Phone { get; set; } = string.Empty;
    public string Note { get; set; } = string.Empty;
}

public sealed class AuthKeyUpdateRequest
{
    public string AuthKey { get; set; } = string.Empty;
    public string? Source { get; set; }
    public string? Note { get; set; }
}

public sealed class ApiRouteUpdateRequest
{
    public string? PrimaryBaseUrl { get; set; }
    public string? SecondaryBaseUrl { get; set; }
    public string? LocalBaseUrl { get; set; }
    public string? LocalIp { get; set; }
    public string? LocalPort { get; set; }
    public bool? SleepRestBonusEnabled { get; set; }
    public bool? IsActive { get; set; }
}

public sealed class SummaryRequest
{
    public decimal? Active { get; set; }
    public string? ActiveText { get; set; }
    public decimal? Steps { get; set; }
    public string? StepsText { get; set; }
    public decimal? HeartRate { get; set; }
    public string? HeartRateText { get; set; }
    public decimal? Distance { get; set; }
    public string? DistanceText { get; set; }
    public decimal? Calories { get; set; }
    public string? CaloriesText { get; set; }
    public decimal? Spo2 { get; set; }
    public string? Spo2Text { get; set; }
    public decimal? Stress { get; set; }
    public string? StressText { get; set; }
    public decimal? Sleep { get; set; }
    public string? SleepText { get; set; }
    public string? SleepStart { get; set; }
    public string? SleepEnd { get; set; }
    public string? SleepType { get; set; }
    public decimal? DeepSleep { get; set; }
    public decimal? LightSleep { get; set; }
    public decimal? RemSleep { get; set; }
    public decimal? Awake { get; set; }
    public string? Wakeup { get; set; }
    public string? Status { get; set; }
    public string? DeviceTime { get; set; }
    public string? MacAddress { get; set; }
    public string? AppVersion { get; set; }
    public int? CompanyId { get; set; }
    public int? DeviceId { get; set; }
    public int EmployeeId { get; set; }
    public int DepartmentId { get; set; }
    public int? ShiftId { get; set; }
    public int? IsFit1 { get; set; }
    public int? IsFit2 { get; set; }
    public int? IsFit3 { get; set; }
    public string? UploadKey { get; set; }
    public string? RequestId { get; set; }
    public string? UserActivity { get; set; }
    public string? UserSleep { get; set; }
    public string? UserStress { get; set; }
    public string? UserSpo2 { get; set; }
    public string? NetworkTransport { get; set; }
    public string? NetworkQuality { get; set; }
    public bool? IsNetworkAvailable { get; set; }
    public bool? IsApiReachable { get; set; }
    public bool? IsApiSlow { get; set; }
    public int? LatencyMs { get; set; }
    public string? ApiBase { get; set; }
    public string? ApiEndpoint { get; set; }
    public string? RouteBase { get; set; }
    public string? Note { get; set; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? Extra { get; set; }
}

public sealed class DetailRequest
{
    public string? DeviceTime { get; set; }
    public string? MacAddress { get; set; }
    public string? AppVersion { get; set; }
    public int EmployeeId { get; set; }
    public string? UploadKey { get; set; }
    public string? UserActivity { get; set; }
    public string? UserSleep { get; set; }
    public string? UserStress { get; set; }
    public string? UserRespiratoryRate { get; set; }
    public string? UserPai { get; set; }
    public string? UserSpo2 { get; set; }
    public string? UserTemperature { get; set; }
    public string? UserCycling { get; set; }
    public string? UserWeight { get; set; }
    public string? UserHeartRateMax { get; set; }
    public string? UserHeartRateResting { get; set; }
    public string? UserHeartRateManual { get; set; }
    public string? UserHrvSummary { get; set; }
    public string? UserHrvValue { get; set; }
    public string? UserBodyEnergy { get; set; }
    public string? NetworkTransport { get; set; }
    public string? NetworkQuality { get; set; }
    public bool? IsNetworkAvailable { get; set; }
    public bool? IsApiReachable { get; set; }
    public bool? IsApiSlow { get; set; }
    public int? LatencyMs { get; set; }
    public string? ApiBase { get; set; }
    public string? ApiEndpoint { get; set; }
    public string? RouteBase { get; set; }
    public string? Note { get; set; }

    [JsonExtensionData]
    public Dictionary<string, JsonElement>? Extra { get; set; }
}

public sealed class FtwManualRequest
{
    public string? LocalId { get; set; }
    public string? Source { get; set; }
    public string? RecordDate { get; set; }
    public string? DeviceTime { get; set; }
    public decimal? SleepHours { get; set; }
    public int? SleepMinutes { get; set; }
    public string? NikOrUsername { get; set; }
    public string? EmployeeCode { get; set; }
    public string? EmployeeName { get; set; }
    public int EmployeeId { get; set; }
    public string? JabatanPekerjaan { get; set; }
    public string? Mess { get; set; }
    public string? HariKerja { get; set; }
    public string? FitStatus { get; set; }
    public string? Q1KeluhanKesehatan { get; set; }
    public string? Q2ObatMengantuk { get; set; }
    public string? Q3MasalahKonsentrasi { get; set; }
    public string? Q4SiapBekerjaAman { get; set; }
    public string? Q5BeraniSpeakupFatigue { get; set; }
    public string? Q1Alasan { get; set; }
    public string? Q2Alasan { get; set; }
    public string? Q3Alasan { get; set; }
    public string? Q4Alasan { get; set; }
    public bool? AgreementChecked { get; set; }
    public string? AppVersion { get; set; }
}

public sealed class P5MCheckpointRequest
{
    public int? EmployeeId { get; set; }
    public string? NikOrUsername { get; set; }
    public string? RecordDate { get; set; }
    public int? Score { get; set; }
    public int? MaxScore { get; set; }
    public int? Percentage { get; set; }
    public string? Source { get; set; }
    public JsonElement? Payload { get; set; }
}

public sealed class NotificationCreateRequest
{
    public string? Username { get; set; }
    public List<string>? Usernames { get; set; }
    public string? Title { get; set; }
    public string? Message { get; set; }
    public string? Kind { get; set; }
    public JsonElement? Payload { get; set; }
}

public sealed class FtwManualAccessUpsertRequest
{
    public string? Username { get; set; }
    public int? EmployeeId { get; set; }
    public string? Nik { get; set; }
    public bool? RequireP5m { get; set; }
    public bool? IsActive { get; set; }
    public string? Note { get; set; }
}

public sealed class ZonaPintarArticleUpsertRequest
{
    public long? Id { get; set; }
    public string? Title { get; set; }
    public string? Description { get; set; }
    public string? Content { get; set; }
    public string? Category { get; set; }
    public string? ImageUrl { get; set; }
    public string? ArticleLink { get; set; }
    public int? SortOrder { get; set; }
    public bool? IsActive { get; set; }
    public string? PublishedAt { get; set; }
}

public sealed class UploadLogInput
{
    public string TraceId { get; set; } = string.Empty;
    public string RequestType { get; set; } = string.Empty;
    public string Endpoint { get; set; } = string.Empty;
    public string? RouteBase { get; set; }
    public string? RouteUrl { get; set; }
    public string? RequestKey { get; set; }
    public int StatusCode { get; set; }
    public int DurationMs { get; set; }
    public int Attempts { get; set; }
    public string? ErrorType { get; set; }
    public string? ErrorMessage { get; set; }
    public string? Note { get; set; }
    public int? CompanyId { get; set; }
    public int? DepartmentId { get; set; }
    public int? EmployeeId { get; set; }
    public int? DeviceId { get; set; }
    public string? MacAddress { get; set; }
    public string? AppVersion { get; set; }
    public string? NetworkTransport { get; set; }
    public string? NetworkQuality { get; set; }
    public bool? IsApiReachable { get; set; }
    public bool? IsApiSlow { get; set; }
    public int? PayloadSize { get; set; }
    public int? ResponseSize { get; set; }
}

public sealed class NetworkProbeRequest
{
    public string? MeasuredAt { get; set; }
    public int? EmployeeId { get; set; }
    public int? DeviceId { get; set; }
    public string? MacAddress { get; set; }
    public string? AppVersion { get; set; }
    public string? NetworkTransport { get; set; }
    public bool? IsNetworkAvailable { get; set; }
    public bool? IsApiReachable { get; set; }
    public bool? IsApiSlow { get; set; }
    public int? LatencyMs { get; set; }
    public string? ApiBase { get; set; }
    public string? ApiEndpoint { get; set; }
    public string? TraceId { get; set; }
    public string? Note { get; set; }
}

public sealed class GoogleActivityRequest
{
    public int? EmployeeId { get; set; }
    public int? DeviceId { get; set; }
    public string? Source { get; set; }
    public List<GoogleActivityItem>? Activities { get; set; }
}

public sealed class GoogleActivityItem
{
    public string? ActivityTime { get; set; }
    public string? ActivityType { get; set; }
    public short? Confidence { get; set; }
    public JsonElement? RawPayload { get; set; }
}

public sealed class CompanyRow
{
    public int Id { get; set; }
    public string Code { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
}

public sealed class UserRow
{
    public int Id { get; set; }
    public int CompanyId { get; set; }
    public string Username { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string Password { get; set; } = string.Empty;
    public string? Dcrip { get; set; }
    public string Role { get; set; } = string.Empty;
}

public sealed class AuthTokenRow
{
    public int UserId { get; set; }
    public int CompanyId { get; set; }
    public string Username { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public DateTime? LastUsedAt { get; set; }
}

public sealed class AuthContext
{
    public int UserId { get; set; }
    public int CompanyId { get; set; }
    public string Username { get; set; } = string.Empty;
    public string Email { get; set; } = string.Empty;
    public string Role { get; set; } = string.Empty;
    public string TokenHash { get; set; } = string.Empty;
}

public sealed class DeviceRow
{
    public int Id { get; set; }
    public int CompanyId { get; set; }
    public string? Brand { get; set; }
    public string? DeviceName { get; set; }
    public string? MacAddress { get; set; }
    public string? AuthKey { get; set; }
    public string? AppVersion { get; set; }
    public DateTime? UpdatedAt { get; set; }
}

public sealed class ApiRouteConfigRow
{
    public int CompanyId { get; set; }
    public string? PrimaryBaseUrl { get; set; }
    public string? SecondaryBaseUrl { get; set; }
    public string? LocalBaseUrl { get; set; }
    public string? LocalIp { get; set; }
    public string? LocalPort { get; set; }
    public bool? SleepRestBonusEnabled { get; set; }
}

public sealed class EmployeeSlimRow
{
    public int Id { get; set; }
    public int CompanyId { get; set; }
}

public sealed class EmployeeProfileRow
{
    public int Id { get; set; }
    public int CompanyId { get; set; }
    public int? DepartmentId { get; set; }
    public int? MessId { get; set; }
    public int? ShiftId { get; set; }
    public int? DeviceId { get; set; }
    public int? UserId { get; set; }
    public string? Code { get; set; }
    public string? Nik { get; set; }
    public string? Fullname { get; set; }
    public string? Email { get; set; }
    public string? Phone { get; set; }
    public string? Photo { get; set; }
    public string? Job { get; set; }
    public string? Position { get; set; }
    public string? DepartmentName { get; set; }
    public string? MessName { get; set; }
}

public sealed class ShiftRow
{
    public int Id { get; set; }
    public string? Code { get; set; }
    public string? Name { get; set; }
    public TimeOnly? WorkStart { get; set; }
    public TimeOnly? WorkEnd { get; set; }
}

public sealed class SummaryTicketRow
{
    public int Id { get; set; }
    public DateOnly SendDate { get; set; }
    public TimeOnly? SendTime { get; set; }
    public string? SleepText { get; set; }
    public bool? IsFit1 { get; set; }
    public bool? IsFit2 { get; set; }
    public bool? IsFit3 { get; set; }
}

public sealed class RankingRow
{
    public int EmployeeId { get; set; }
    public string? Code { get; set; }
    public string? Fullname { get; set; }
    public int Year { get; set; }
    public int Month { get; set; }
    public decimal TotalSleep { get; set; }
    public decimal AverageSleep { get; set; }
    public int CountData { get; set; }
}

public sealed class BannerRow
{
    public string? Image { get; set; }
}
