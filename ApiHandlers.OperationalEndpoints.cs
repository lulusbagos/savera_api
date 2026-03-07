using System.Globalization;
using Dapper;
using Npgsql;
using SaveraApi.Infrastructure;

namespace SaveraApi;

public static partial class ApiHandlers
{
    public static async Task<IResult> LeaveAsync(HttpContext context, LeaveRequest request, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        if (request.EmployeeId <= 0 ||
            string.IsNullOrWhiteSpace(request.Type) ||
            string.IsNullOrWhiteSpace(request.Phone) ||
            string.IsNullOrWhiteSpace(request.Note))
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "employee_id, type, phone, note are required");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var employee = await ResolveEmployeeByIdAsync(db, company.Id, request.EmployeeId);
        if (employee is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");
        }

        await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_leave
(date, shift, code, fullname, job, type, phone, note, employee_id, company_id, department_id, created_at, updated_at)
VALUES
(CURRENT_DATE, '-', @Code, @Fullname, @Job, @Type, @Phone, @Note, @EmployeeId, @CompanyId, @DepartmentId, now(), now())", new
        {
            Code = employee.Code,
            Fullname = employee.Fullname,
            Job = employee.Job,
            Type = request.Type.Trim(),
            Phone = request.Phone.Trim(),
            Note = request.Note.Trim(),
            EmployeeId = employee.Id,
            CompanyId = company.Id,
            DepartmentId = employee.DepartmentId
        });

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                date = DateOnly.FromDateTime(DateTime.Today).ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
                shift = "-",
                code = employee.Code,
                fullname = employee.Fullname,
                job = employee.Job,
                type = request.Type.Trim(),
                phone = request.Phone.Trim(),
                note = request.Note.Trim(),
                employee_id = employee.Id,
                company_id = company.Id,
                department_id = employee.DepartmentId
            }
        });
    }

    public static async Task<IResult> TicketAsync(HttpContext context, int id, NpgsqlDataSource db)
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

        var employee = await ResolveEmployeeByIdAsync(db, company.Id, id);
        if (employee is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");
        }

        var summary = await db.QuerySingleOrDefaultAsync<SummaryTicketRow>(@"
SELECT id, send_date, send_time, sleep_text, is_fit1, is_fit2, is_fit3
FROM public.tbl_t_summary
WHERE deleted_at IS NULL
  AND employee_id=@EmployeeId
  AND send_date=CURRENT_DATE
ORDER BY id DESC
LIMIT 1", new { EmployeeId = employee.Id });

        if (summary is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Summary not found.");
        }

        const string placeholder = "-";

        await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_ticket
(summary_id, date, shift, code, fullname, job, sector, area, type, unit, model, fleet, transport, day,
 employee_id, company_id, department_id, created_at, updated_at)
VALUES
(@SummaryId, @Date, @Shift, @Code, @Fullname, @Job, @Sector, @Area, @Type, @Unit, @Model, @Fleet, @Transport, @Day,
 @EmployeeId, @CompanyId, @DepartmentId, now(), now())", new
        {
            SummaryId = summary.Id,
            Date = summary.SendDate.ToDateTime(TimeOnly.MinValue).Date,
            Shift = placeholder,
            Code = employee.Code,
            Fullname = employee.Fullname,
            Job = employee.Job,
            Sector = placeholder,
            Area = placeholder,
            Type = placeholder,
            Unit = placeholder,
            Model = placeholder,
            Fleet = placeholder,
            Transport = placeholder,
            Day = placeholder,
            EmployeeId = employee.Id,
            CompanyId = company.Id,
            DepartmentId = employee.DepartmentId
        });

        return Results.Ok(new
        {
            id = summary.Id,
            send_date = summary.SendDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
            send_time = summary.SendTime?.ToString("HH:mm:ss", CultureInfo.InvariantCulture),
            shift = placeholder,
            hauler = placeholder,
            loader = placeholder,
            transport = placeholder,
            date = summary.SendDate.ToString("dd MMMM yyyy", new CultureInfo("id-ID")),
            time = summary.SendTime?.ToString("HH:mm:ss", CultureInfo.InvariantCulture),
            sleep_text = string.IsNullOrWhiteSpace(summary.SleepText) ? "-" : summary.SleepText,
            message = $"Minum Obat: {ToYesNo(summary.IsFit1)}\nAda Masalah Konsentrasi: {ToYesNo(summary.IsFit2)}\nSiap Bekerja: {ToYesNo(summary.IsFit3)}\n"
        });
    }

    public static async Task<IResult> RankingAsync(HttpContext context, int id, NpgsqlDataSource db)
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

        var rows = (await db.QueryAsync<RankingRow>(@"
SELECT s.employee_id,
       e.code,
       e.full_name AS fullname,
       EXTRACT(YEAR FROM s.send_date)::int AS year,
       EXTRACT(MONTH FROM s.send_date)::int AS month,
       COALESCE(SUM(s.sleep), 0) AS total_sleep,
       COALESCE(AVG(s.sleep), 0) AS average_sleep,
       COUNT(*)::int AS count_data
FROM public.tbl_t_summary s
JOIN public.tbl_r_employee e ON e.id = s.employee_id
WHERE s.deleted_at IS NULL
  AND e.deleted_at IS NULL
  AND s.company_id=@CompanyId
  AND EXTRACT(YEAR FROM s.send_date)=EXTRACT(YEAR FROM CURRENT_DATE)
  AND EXTRACT(MONTH FROM s.send_date)=EXTRACT(MONTH FROM CURRENT_DATE)
GROUP BY s.employee_id, e.code, e.full_name,
         EXTRACT(YEAR FROM s.send_date), EXTRACT(MONTH FROM s.send_date)
ORDER BY COALESCE(AVG(s.sleep),0) DESC, s.employee_id ASC", new { CompanyId = company.Id })).ToList();

        if (rows.Count == 0)
        {
            return Results.Ok(new
            {
                message = "ok",
                total = 0,
                rank = 0,
                average = "00:00",
                date = DateTime.Now.ToString("dd MMM yyyy", CultureInfo.InvariantCulture),
                data = Array.Empty<object>()
            });
        }

        var totalAverageSleep = rows.Sum(x => x.AverageSleep);
        var rank = 0;
        for (var i = 0; i < rows.Count; i++)
        {
            if (rows[i].EmployeeId == id)
            {
                rank = i + 1;
                break;
            }
        }

        var averageTotal = totalAverageSleep / rows.Count;
        var top = rows.Take(10).Select(x => new
        {
            employee_id = x.EmployeeId,
            code = x.Code,
            fullname = x.Fullname,
            year = x.Year,
            month = x.Month,
            total_sleep = x.TotalSleep,
            average_sleep = x.AverageSleep,
            count_data = x.CountData,
            average_sleep_hour = ToHourMinute(x.AverageSleep)
        });

        return Results.Ok(new
        {
            message = "ok",
            total = rows.Count,
            rank,
            average = ToHourMinute(averageTotal),
            date = DateTime.Now.ToString("dd MMM yyyy", CultureInfo.InvariantCulture),
            data = top
        });
    }

    public static async Task<IResult> FtwManualAsync(HttpContext context, FtwManualRequest request, NpgsqlDataSource db)
    {
        var auth = await AuthenticateAsync(context, db);
        if (auth is null)
        {
            return ErrorMessage(StatusCodes.Status401Unauthorized, "Unauthenticated.");
        }

        if (request.EmployeeId <= 0)
        {
            return ErrorMessage(StatusCodes.Status422UnprocessableEntity, "employee_id is required");
        }

        var company = await ResolveCompanyForAuthAsync(context, db, auth.CompanyId);
        if (company is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Company not found.");
        }

        var employee = await ResolveEmployeeByIdAsync(db, company.Id, request.EmployeeId);
        if (employee is null)
        {
            return ErrorMessage(StatusCodes.Status404NotFound, "Employee not found.");
        }

        var eligibility = await CheckFtwManualEligibilityAsync(db, company.Id, auth, employee, request.NikOrUsername);
        if (!eligibility.Allowed)
        {
            return Results.Json(new
            {
                message = eligibility.Message,
                data = new
                {
                    allowed = eligibility.Allowed,
                    require_p5m = eligibility.RequireP5m,
                    p5m_today = eligibility.P5mToday
                }
            }, statusCode: StatusCodes.Status403Forbidden);
        }

        var idempotencyKey = ResolveIdempotencyKey(context);
        var localId = !string.IsNullOrWhiteSpace(request.LocalId)
            ? request.LocalId.Trim()
            : (!string.IsNullOrWhiteSpace(idempotencyKey) ? idempotencyKey : Guid.NewGuid().ToString("N"));
        var recordDate = ExtractDateOnly(request.RecordDate) ??
            ExtractDateOnly(request.DeviceTime) ??
            DateOnly.FromDateTime(DateTime.Today);
        var deviceTime = ExtractDateTime(request.DeviceTime) ?? DateTime.Now;
        var source = string.IsNullOrWhiteSpace(request.Source) ? "manual" : request.Source.Trim();
        var sleepMinutes = request.SleepMinutes ?? (request.SleepHours.HasValue ? (int)Math.Round(request.SleepHours.Value * 60m) : (int?)null);

        await db.ExecuteAsync(@"
INSERT INTO public.tbl_t_ftw_manual
(local_id, source, record_date, device_time, sleep_hours, sleep_minutes,
 nik_or_username, employee_code, employee_name, employee_id, company_id,
 jabatan_pekerjaan, mess, hari_kerja, fit_status,
 q1_keluhan_kesehatan, q2_obat_mengantuk, q3_masalah_konsentrasi, q4_siap_bekerja_aman, q5_berani_speakup_fatigue,
 q1_alasan, q2_alasan, q3_alasan, q4_alasan, agreement_checked, app_version,
 sync_status, synced_at, created_at, updated_at)
VALUES
(@LocalId, @Source, @RecordDate, @DeviceTime, @SleepHours, @SleepMinutes,
 @NikOrUsername, @EmployeeCode, @EmployeeName, @EmployeeId, @CompanyId,
 @JabatanPekerjaan, @Mess, @HariKerja, @FitStatus,
 @Q1KeluhanKesehatan, @Q2ObatMengantuk, @Q3MasalahKonsentrasi, @Q4SiapBekerjaAman, @Q5BeraniSpeakupFatigue,
 @Q1Alasan, @Q2Alasan, @Q3Alasan, @Q4Alasan, @AgreementChecked, @AppVersion,
 'synced', now(), now(), now())
ON CONFLICT (local_id)
DO UPDATE SET
    source=EXCLUDED.source,
    record_date=EXCLUDED.record_date,
    device_time=EXCLUDED.device_time,
    sleep_hours=EXCLUDED.sleep_hours,
    sleep_minutes=EXCLUDED.sleep_minutes,
    nik_or_username=EXCLUDED.nik_or_username,
    employee_code=EXCLUDED.employee_code,
    employee_name=EXCLUDED.employee_name,
    employee_id=EXCLUDED.employee_id,
    company_id=EXCLUDED.company_id,
    jabatan_pekerjaan=EXCLUDED.jabatan_pekerjaan,
    mess=EXCLUDED.mess,
    hari_kerja=EXCLUDED.hari_kerja,
    fit_status=EXCLUDED.fit_status,
    q1_keluhan_kesehatan=EXCLUDED.q1_keluhan_kesehatan,
    q2_obat_mengantuk=EXCLUDED.q2_obat_mengantuk,
    q3_masalah_konsentrasi=EXCLUDED.q3_masalah_konsentrasi,
    q4_siap_bekerja_aman=EXCLUDED.q4_siap_bekerja_aman,
    q5_berani_speakup_fatigue=EXCLUDED.q5_berani_speakup_fatigue,
    q1_alasan=EXCLUDED.q1_alasan,
    q2_alasan=EXCLUDED.q2_alasan,
    q3_alasan=EXCLUDED.q3_alasan,
    q4_alasan=EXCLUDED.q4_alasan,
    agreement_checked=EXCLUDED.agreement_checked,
    app_version=EXCLUDED.app_version,
    sync_status='synced',
    synced_at=now(),
    updated_at=now()", new
        {
            LocalId = localId,
            Source = source,
            RecordDate = recordDate.ToDateTime(TimeOnly.MinValue).Date,
            DeviceTime = deviceTime,
            SleepHours = request.SleepHours,
            SleepMinutes = sleepMinutes,
            NikOrUsername = request.NikOrUsername,
            EmployeeCode = string.IsNullOrWhiteSpace(request.EmployeeCode) ? employee.Code : request.EmployeeCode,
            EmployeeName = string.IsNullOrWhiteSpace(request.EmployeeName) ? employee.Fullname : request.EmployeeName,
            EmployeeId = employee.Id,
            CompanyId = company.Id,
            JabatanPekerjaan = request.JabatanPekerjaan,
            Mess = request.Mess,
            HariKerja = request.HariKerja,
            FitStatus = request.FitStatus,
            Q1KeluhanKesehatan = request.Q1KeluhanKesehatan,
            Q2ObatMengantuk = request.Q2ObatMengantuk,
            Q3MasalahKonsentrasi = request.Q3MasalahKonsentrasi,
            Q4SiapBekerjaAman = request.Q4SiapBekerjaAman,
            Q5BeraniSpeakupFatigue = request.Q5BeraniSpeakupFatigue,
            Q1Alasan = request.Q1Alasan,
            Q2Alasan = request.Q2Alasan,
            Q3Alasan = request.Q3Alasan,
            Q4Alasan = request.Q4Alasan,
            AgreementChecked = request.AgreementChecked,
            AppVersion = request.AppVersion
        });

        return Results.Ok(new
        {
            message = "Successfully created",
            data = new
            {
                local_id = localId,
                record_date = recordDate.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture),
                employee_id = employee.Id,
                company_id = company.Id
            }
        });
    }
}
