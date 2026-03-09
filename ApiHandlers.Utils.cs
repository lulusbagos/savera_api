using System.Globalization;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static DateOnly? ExtractDateOnly(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        if (DateOnly.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.None, out var dateOnly))
        {
            return dateOnly;
        }

        if (DateTime.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.AssumeLocal, out var dateTime))
        {
            return DateOnly.FromDateTime(dateTime);
        }

        if (DateTime.TryParse(value, out dateTime))
        {
            return DateOnly.FromDateTime(dateTime);
        }

        return null;
    }

    public static DateTime? ExtractDateTime(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        if (DateTime.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.AssumeLocal, out var dateTime))
        {
            return dateTime;
        }

        if (DateTime.TryParse(value, out dateTime))
        {
            return dateTime;
        }

        return null;
    }

    public static string NormalizeJsonPayload(string? json)
    {
        if (string.IsNullOrWhiteSpace(json))
        {
            return "[]";
        }

        var trimmed = json.Trim();
        try
        {
            using var doc = JsonDocument.Parse(trimmed);
            return doc.RootElement.GetRawText();
        }
        catch
        {
            return "[]";
        }
    }

    public static string BuildMetricRelativePath(string metric, DateOnly date, int employeeId, string source)
    {
        var safeMetric = SanitizePathPart(metric);
        var paddedEmployee = employeeId <= 0 ? "00000000" : employeeId.ToString("D8", CultureInfo.InvariantCulture);
        var safeSource = string.IsNullOrWhiteSpace(source) ? "data" : SanitizePathPart(source).ToLowerInvariant();

        return Path.Combine(
            safeMetric,
            date.Year.ToString("D4", CultureInfo.InvariantCulture),
            date.Month.ToString("D2", CultureInfo.InvariantCulture),
            date.Day.ToString("D2", CultureInfo.InvariantCulture),
            $"{paddedEmployee}_{safeSource}.json"
        );
    }

    public static string SanitizePathPart(string value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return "none";
        }

        var replaced = Regex.Replace(value, "[^a-zA-Z0-9_\\-]+", "_");
        return replaced.Length > 80 ? replaced[..80] : replaced;
    }

    public static int JsonSizeBytes(string? value)
    {
        if (string.IsNullOrEmpty(value))
        {
            return 0;
        }

        return Encoding.UTF8.GetByteCount(value);
    }

    public static TimeOnly? ExtractTimeOnly(string? value)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            return null;
        }

        if (TimeOnly.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.None, out var timeOnly))
        {
            return timeOnly;
        }

        if (DateTime.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.AssumeLocal, out var dateTime))
        {
            return TimeOnly.FromDateTime(dateTime);
        }

        if (DateTime.TryParse(value, out dateTime))
        {
            return TimeOnly.FromDateTime(dateTime);
        }

        return null;
    }

    public static string? GetExtraString(Dictionary<string, JsonElement>? extra, string key)
    {
        if (extra is null || !extra.TryGetValue(key, out var value))
        {
            return null;
        }

        if (value.ValueKind == JsonValueKind.String)
        {
            return value.GetString();
        }

        return value.ToString();
    }

    public static bool? GetExtraBool(Dictionary<string, JsonElement>? extra, string key)
    {
        if (extra is null || !extra.TryGetValue(key, out var value))
        {
            return null;
        }

        if (value.ValueKind is JsonValueKind.True or JsonValueKind.False)
        {
            return value.GetBoolean();
        }

        if (value.ValueKind == JsonValueKind.Number && value.TryGetInt32(out var asInt))
        {
            return asInt > 0;
        }

        if (value.ValueKind == JsonValueKind.String)
        {
            var raw = value.GetString();
            if (bool.TryParse(raw, out var b))
            {
                return b;
            }

            if (int.TryParse(raw, NumberStyles.Integer, CultureInfo.InvariantCulture, out asInt))
            {
                return asInt > 0;
            }
        }

        return null;
    }

    public static int? GetExtraInt(Dictionary<string, JsonElement>? extra, string key)
    {
        if (extra is null || !extra.TryGetValue(key, out var value))
        {
            return null;
        }

        if (value.ValueKind == JsonValueKind.Number && value.TryGetInt32(out var asInt))
        {
            return asInt;
        }

        if (value.ValueKind == JsonValueKind.String &&
            int.TryParse(value.GetString(), NumberStyles.Integer, CultureInfo.InvariantCulture, out asInt))
        {
            return asInt;
        }

        return null;
    }

    public static string ResolveRouteBase(HttpContext context, string? bodyRouteBase)
    {
        if (!string.IsNullOrWhiteSpace(bodyRouteBase))
        {
            return bodyRouteBase.Trim();
        }

        var fromHeader = context.Request.Headers["X-Route-Base"].FirstOrDefault();
        if (!string.IsNullOrWhiteSpace(fromHeader))
        {
            return fromHeader.Trim();
        }

        return string.Empty;
    }

    public static string? ValidateSummaryRequest(SummaryRequest request)
    {
        if (request.Active is null) return "active is required";
        if (string.IsNullOrWhiteSpace(request.ActiveText)) return "active_text is required";
        if (request.Steps is null) return "steps is required";
        if (string.IsNullOrWhiteSpace(request.StepsText)) return "steps_text is required";
        if (request.HeartRate is null) return "heart_rate is required";
        if (string.IsNullOrWhiteSpace(request.HeartRateText)) return "heart_rate_text is required";
        if (request.Distance is null) return "distance is required";
        if (string.IsNullOrWhiteSpace(request.DistanceText)) return "distance_text is required";
        if (request.Calories is null) return "calories is required";
        if (string.IsNullOrWhiteSpace(request.CaloriesText)) return "calories_text is required";
        if (request.Spo2 is null) return "spo2 is required";
        if (string.IsNullOrWhiteSpace(request.Spo2Text)) return "spo2_text is required";
        if (request.Stress is null) return "stress is required";
        if (string.IsNullOrWhiteSpace(request.StressText)) return "stress_text is required";
        if (request.Sleep is null) return "sleep is required";
        if (string.IsNullOrWhiteSpace(request.SleepText)) return "sleep_text is required";
        if (string.IsNullOrWhiteSpace(request.DeviceTime)) return "device_time is required";
        if (string.IsNullOrWhiteSpace(request.MacAddress)) return "mac_address is required";
        if (request.EmployeeId <= 0) return "employee_id is required";
        if (request.DepartmentId <= 0) return "department_id is required";
        return null;
    }
}
