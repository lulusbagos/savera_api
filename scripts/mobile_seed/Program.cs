using System.Text.Json;
using BCrypt.Net;
using Npgsql;

internal sealed class ArticleSeed
{
    public required string Title { get; init; }
    public required string Description { get; init; }
    public required string Content { get; init; }
    public required string Category { get; init; }
    public required int SortOrder { get; init; }
}

internal sealed class P5MSeed
{
    public required string QuestionText { get; init; }
    public required string QuestionType { get; init; }
    public required string OptionsJson { get; init; }
    public required int Weight { get; init; }
    public bool IsRequired { get; init; } = true;
}

internal static class Program
{
    private static readonly ArticleSeed[] ZonaPintarSeeds =
    [
        new()
        {
            Title = "5 Menit Sebelum Operasi Unit: Safety Checklist Operator",
            Description = "Checklist ringkas sebelum unit bergerak: APD, pre-start, blind spot, komunikasi radio, dan kondisi jalur.",
            Content = "Pastikan APD lengkap. Lakukan pre-start check unit sesuai SOP. Verifikasi area blind spot bebas personel. " +
                      "Uji komunikasi radio dengan dispatcher/pengawas. Konfirmasi jalur aman dari genangan, longsoran, atau rintangan.",
            Category = "Safety Briefing",
            SortOrder = 10
        },
        new()
        {
            Title = "Fatigue Management untuk Operator Tambang",
            Description = "Kenali tanda mengantuk, mikrosleep, dan tindakan mitigasi sebelum masuk area operasi.",
            Content = "Jika merasa mengantuk, pusing, atau fokus menurun, hentikan operasi dengan aman dan lapor atasan. " +
                      "Lakukan istirahat terkontrol, hidrasi, dan evaluasi FTW sebelum kembali bekerja.",
            Category = "Fatigue Management",
            SortOrder = 20
        },
        new()
        {
            Title = "Hazard Paling Sering di Area Tambang Terbuka",
            Description = "Blind spot alat berat, jalan licin, over-speed, dan crossing point berisiko tinggi.",
            Content = "Jaga jarak aman antar unit, patuhi batas kecepatan, hindari manuver tanpa spotter di area terbatas, " +
                      "dan selalu verifikasi crossing point sebelum melintas.",
            Category = "Hazard Awareness",
            SortOrder = 30
        },
        new()
        {
            Title = "Komunikasi Radio Efektif Saat Perpindahan Unit",
            Description = "Gunakan format komunikasi standar: identitas, lokasi, tujuan, dan konfirmasi ulang.",
            Content = "Sebelum pindah area, sampaikan identitas unit, posisi saat ini, tujuan, dan minta acknowledgement dari penerima. " +
                      "Hindari komunikasi ambigu yang bisa memicu near miss.",
            Category = "Operational Safety",
            SortOrder = 40
        },
        new()
        {
            Title = "Stop Work Authority: Kapan Operator Wajib Stop",
            Description = "Operator berhak dan wajib menghentikan pekerjaan saat kondisi tidak aman.",
            Content = "Hentikan pekerjaan bila ada kondisi tidak aman: visibilitas buruk, rem tidak normal, komunikasi putus, " +
                      "atau ada personel di zona bahaya. Lanjutkan hanya setelah risiko dikendalikan.",
            Category = "Safety Culture",
            SortOrder = 50
        }
    ];

    private static readonly P5MSeed[] P5MSeeds =
    [
        new()
        {
            QuestionText = "Apakah Anda fit to work (tidur cukup, tidak mengantuk, dan siap operasi unit)?",
            QuestionType = "radio",
            OptionsJson = "[\"Ya, fit\",\"Ragu-ragu\",\"Tidak fit\"]",
            Weight = 25
        },
        new()
        {
            QuestionText = "Apakah Anda sudah melakukan pre-start check unit sesuai SOP sebelum kerja?",
            QuestionType = "radio",
            OptionsJson = "[\"Sudah lengkap\",\"Sebagian\",\"Belum\"]",
            Weight = 25
        },
        new()
        {
            QuestionText = "Apakah area kerja aman dari hazard kritis (blind spot, jalan licin, longsoran, atau alat berat lain)?",
            QuestionType = "radio",
            OptionsJson = "[\"Aman\",\"Ada potensi hazard\",\"Tidak aman\"]",
            Weight = 25
        },
        new()
        {
            QuestionText = "Apakah APD wajib (helm, sepatu safety, rompi, kacamata, sarung tangan) digunakan lengkap?",
            QuestionType = "radio",
            OptionsJson = "[\"Lengkap\",\"Kurang 1 item\",\"Tidak lengkap\"]",
            Weight = 15
        },
        new()
        {
            QuestionText = "Catatan P5M: sebutkan bahaya utama shift ini dan tindakan pengendaliannya.",
            QuestionType = "text",
            OptionsJson = "[]",
            Weight = 10
        }
    ];

    private static async Task<int> Main()
    {
        try
        {
            var args = Environment.GetCommandLineArgs();
            var appSettingsPath = FindParentFile(AppContext.BaseDirectory, "appsettings.json");
            if (string.IsNullOrWhiteSpace(appSettingsPath))
            {
                Console.Error.WriteLine("appsettings.json not found in parent directories.");
                return 1;
            }

            var apiRoot = Path.GetDirectoryName(appSettingsPath)!;
            var migrationPaths = new[]
            {
                Path.Combine(apiRoot, "sql", "20260303_ftw_notifications_p5m.sql"),
                Path.Combine(apiRoot, "sql", "20260304_zona_pintar_articles.sql")
            };

            var connectionString = ReadConnectionString(appSettingsPath);
            if (string.IsNullOrWhiteSpace(connectionString))
            {
                Console.Error.WriteLine("ConnectionStrings:Postgres not found in appsettings.json");
                return 1;
            }

            var companyCode = Environment.GetEnvironmentVariable("MOBILE_COMPANY_CODE");
            if (string.IsNullOrWhiteSpace(companyCode))
            {
                companyCode = "SAVERA";
            }
            var companyCodeLower = companyCode.Trim().ToLowerInvariant();
            var mobileUsername = Environment.GetEnvironmentVariable("MOBILE_LOGIN_USERNAME");
            if (string.IsNullOrWhiteSpace(mobileUsername))
            {
                mobileUsername = $"{companyCode}mobile_debug";
            }
            var mobileEmail = Environment.GetEnvironmentVariable("MOBILE_LOGIN_EMAIL");
            if (string.IsNullOrWhiteSpace(mobileEmail))
            {
                mobileEmail = $"mobile.operator+{companyCodeLower}@savera.local";
            }
            var mobilePassword = Environment.GetEnvironmentVariable("MOBILE_LOGIN_PASSWORD");
            if (string.IsNullOrWhiteSpace(mobilePassword))
            {
                mobilePassword = "Mobile#2026!";
            }

            await using var conn = new NpgsqlConnection(connectionString);
            await conn.OpenAsync();

            foreach (var migrationPath in migrationPaths)
            {
                if (!File.Exists(migrationPath))
                {
                    continue;
                }

                var migrationSql = await File.ReadAllTextAsync(migrationPath);
                migrationSql = StripOuterTransactionStatements(migrationSql);
                await using var migrationCmd = new NpgsqlCommand(migrationSql, conn);
                await migrationCmd.ExecuteNonQueryAsync();
            }

            await using (var dropUnused = new NpgsqlCommand("DROP TABLE IF EXISTS public.tbl_m_p5m_question", conn))
            {
                await dropUnused.ExecuteNonQueryAsync();
            }

            await using var tx = await conn.BeginTransactionAsync();

            if (args.Any(x => string.Equals(x, "--user-columns", StringComparison.OrdinalIgnoreCase)))
            {
                var schemaRows = await ListColumnSchemaAsync(conn, tx, "tbl_m_user");
                Console.WriteLine("tbl_m_user columns:");
                foreach (var row in schemaRows)
                {
                    Console.WriteLine(row);
                }
                await tx.RollbackAsync();
                return 0;
            }
            if (args.Any(x => string.Equals(x, "--employee-columns", StringComparison.OrdinalIgnoreCase)))
            {
                var schemaRows = await ListColumnSchemaAsync(conn, tx, "tbl_r_employee");
                Console.WriteLine("tbl_r_employee columns:");
                foreach (var row in schemaRows)
                {
                    Console.WriteLine(row);
                }
                await tx.RollbackAsync();
                return 0;
            }

            var companyId = await ResolveCompanyIdAsync(conn, tx, companyCode);
            if (companyId <= 0)
            {
                var availableCompanies = await ListCompaniesAsync(conn, tx);
                Console.Error.WriteLine($"Company code '{companyCode}' not found.");
                Console.Error.WriteLine("Available company codes:");
                foreach (var row in availableCompanies)
                {
                    Console.Error.WriteLine($"- {row}");
                }
                return 1;
            }

            var userColumns = await GetColumnsAsync(conn, tx, "tbl_m_user");
            var userId = await UpsertMobileUserAsync(conn, tx, userColumns, companyId, mobileUsername, mobileEmail, mobilePassword);
            var linkedEmployee = await BindEmployeeIfAvailableAsync(conn, tx, userId, companyId);

            await UpsertLegacyP5MTableAsync(conn, tx, companyId, userId);
            await UpsertZonaPintarArticlesAsync(conn, tx, companyId, userId);
            await SeedUserNotificationsAsync(conn, tx, companyId, mobileUsername, userId);

            await tx.CommitAsync();

            var articleCount = await CountRowsAsync(conn, "public.tbl_t_zona_pintar_article", companyId);
            var legacyP5mCount = await CountRowsAsync(conn, "public.tbl_m_p5m", companyId);
            var notifCount = await CountUserNotificationsAsync(conn, companyId, mobileUsername);

            Console.WriteLine("Seed completed successfully.");
            Console.WriteLine($"CompanyCode  : {companyCode}");
            Console.WriteLine($"CompanyId    : {companyId}");
            Console.WriteLine($"Username     : {mobileUsername}");
            Console.WriteLine($"Email        : {mobileEmail}");
            Console.WriteLine($"Password     : {mobilePassword}");
            Console.WriteLine($"UserId       : {userId}");
            Console.WriteLine($"ArtikelCount : {articleCount}");
            Console.WriteLine($"P5MLegacyCount : {legacyP5mCount}");
            Console.WriteLine($"NotifCount   : {notifCount}");
            if (!string.IsNullOrWhiteSpace(linkedEmployee))
            {
                Console.WriteLine($"EmployeeLink : {linkedEmployee}");
            }
            else
            {
                Console.WriteLine("EmployeeLink : No available employee with empty user_id.");
            }

            return 0;
        }
        catch (Exception ex)
        {
            Console.Error.WriteLine($"ERROR: {ex.Message}");
            return 1;
        }
    }

    private static string ReadConnectionString(string appSettingsPath)
    {
        using var doc = JsonDocument.Parse(File.ReadAllText(appSettingsPath));
        if (!doc.RootElement.TryGetProperty("ConnectionStrings", out var connSection))
        {
            return string.Empty;
        }
        if (!connSection.TryGetProperty("Postgres", out var pg))
        {
            return string.Empty;
        }
        return pg.GetString() ?? string.Empty;
    }

    private static string StripOuterTransactionStatements(string sql)
    {
        if (string.IsNullOrWhiteSpace(sql))
        {
            return string.Empty;
        }

        var lines = sql.Split(['\r', '\n'], StringSplitOptions.None);
        var filtered = new List<string>(lines.Length);
        foreach (var line in lines)
        {
            var trimmed = line.Trim();
            if (string.Equals(trimmed, "BEGIN;", StringComparison.OrdinalIgnoreCase) ||
                string.Equals(trimmed, "COMMIT;", StringComparison.OrdinalIgnoreCase))
            {
                continue;
            }
            filtered.Add(line);
        }
        return string.Join(Environment.NewLine, filtered);
    }

    private static string FindParentFile(string startPath, string fileName)
    {
        var dir = new DirectoryInfo(startPath);
        while (dir is not null)
        {
            var candidate = Path.Combine(dir.FullName, fileName);
            if (File.Exists(candidate))
            {
                return candidate;
            }
            dir = dir.Parent;
        }
        return string.Empty;
    }

    private static async Task<int> ResolveCompanyIdAsync(NpgsqlConnection conn, NpgsqlTransaction tx, string companyCode)
    {
        const string sql = @"
SELECT id
FROM public.tbl_m_company
WHERE deleted_at IS NULL
  AND lower(code)=lower(@Code)
LIMIT 1";
        await using var cmd = new NpgsqlCommand(sql, conn, tx);
        cmd.Parameters.AddWithValue("Code", companyCode);
        var value = await cmd.ExecuteScalarAsync();
        return value is int id ? id : Convert.ToInt32(value ?? 0);
    }

    private static async Task<List<string>> ListCompaniesAsync(NpgsqlConnection conn, NpgsqlTransaction tx)
    {
        const string sql = @"
SELECT id, code, name
FROM public.tbl_m_company
WHERE deleted_at IS NULL
ORDER BY id
LIMIT 50";
        var result = new List<string>();
        await using var cmd = new NpgsqlCommand(sql, conn, tx);
        await using var reader = await cmd.ExecuteReaderAsync();
        while (await reader.ReadAsync())
        {
            var id = reader.GetInt32(0);
            var code = reader.IsDBNull(1) ? "-" : reader.GetString(1);
            var name = reader.IsDBNull(2) ? "-" : reader.GetString(2);
            result.Add($"{id} | {code} | {name}");
        }
        return result;
    }

    private static async Task<HashSet<string>> GetColumnsAsync(NpgsqlConnection conn, NpgsqlTransaction? tx, string tableName)
    {
        const string sql = @"
SELECT column_name
FROM information_schema.columns
WHERE table_schema='public'
  AND table_name=@TableName";
        var result = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        await using var cmd = new NpgsqlCommand(sql, conn, tx);
        cmd.Parameters.AddWithValue("TableName", tableName);
        await using var reader = await cmd.ExecuteReaderAsync();
        while (await reader.ReadAsync())
        {
            result.Add(reader.GetString(0));
        }
        return result;
    }

    private static async Task<List<string>> ListColumnSchemaAsync(NpgsqlConnection conn, NpgsqlTransaction tx, string tableName)
    {
        const string sql = @"
SELECT column_name, is_nullable, data_type, COALESCE(column_default, '')
FROM information_schema.columns
WHERE table_schema='public'
  AND table_name=@TableName
ORDER BY ordinal_position";
        var result = new List<string>();
        await using var cmd = new NpgsqlCommand(sql, conn, tx);
        cmd.Parameters.AddWithValue("TableName", tableName);
        await using var reader = await cmd.ExecuteReaderAsync();
        while (await reader.ReadAsync())
        {
            var name = reader.GetString(0);
            var nullable = reader.GetString(1);
            var dataType = reader.GetString(2);
            var defaultValue = reader.IsDBNull(3) ? string.Empty : reader.GetString(3);
            result.Add($"{name} | nullable={nullable} | type={dataType} | default={defaultValue}");
        }
        return result;
    }

    private static async Task<int> UpsertMobileUserAsync(
        NpgsqlConnection conn,
        NpgsqlTransaction tx,
        HashSet<string> columns,
        int companyId,
        string username,
        string email,
        string plainPassword)
    {
        var passwordHash = BCrypt.Net.BCrypt.HashPassword(plainPassword);
        var fullName = "Mobile Debug Operator";

        const string findSql = @"
SELECT id
FROM public.tbl_m_user
WHERE deleted_at IS NULL
  AND (lower(username)=lower(@Username) OR lower(email)=lower(@Email))
ORDER BY id
LIMIT 1";
        await using var findCmd = new NpgsqlCommand(findSql, conn, tx);
        findCmd.Parameters.AddWithValue("Username", username);
        findCmd.Parameters.AddWithValue("Email", email);
        var existing = await findCmd.ExecuteScalarAsync();
        if (existing is not null && existing != DBNull.Value)
        {
            var userId = Convert.ToInt32(existing);
            var setParts = new List<string>
            {
                "company_id=@CompanyId",
                "username=@Username",
                "email=@Email",
                "password=@Password",
                "full_name=@FullName",
                "role=@Role"
            };

            if (columns.Contains("dcrip"))
            {
                setParts.Add("dcrip=@Dcrip");
            }
            if (columns.Contains("is_active"))
            {
                setParts.Add("is_active=true");
            }
            if (columns.Contains("deleted_at"))
            {
                setParts.Add("deleted_at=NULL");
            }
            if (columns.Contains("updated_at"))
            {
                setParts.Add("updated_at=now()");
            }

            var updateSql = $"UPDATE public.tbl_m_user SET {string.Join(", ", setParts)} WHERE id=@Id";
            await using var updateCmd = new NpgsqlCommand(updateSql, conn, tx);
            updateCmd.Parameters.AddWithValue("Id", userId);
            updateCmd.Parameters.AddWithValue("CompanyId", companyId);
            updateCmd.Parameters.AddWithValue("Username", username);
            updateCmd.Parameters.AddWithValue("Email", email);
            updateCmd.Parameters.AddWithValue("Password", passwordHash);
            updateCmd.Parameters.AddWithValue("FullName", fullName);
            updateCmd.Parameters.AddWithValue("Role", "operator");
            if (columns.Contains("dcrip"))
            {
                updateCmd.Parameters.AddWithValue("Dcrip", "bcrypt");
            }
            await updateCmd.ExecuteNonQueryAsync();
            return userId;
        }

        var insertColumns = new List<string> { "company_id", "username", "email", "password", "full_name", "role" };
        var insertValues = new List<string> { "@CompanyId", "@Username", "@Email", "@Password", "@FullName", "@Role" };
        if (columns.Contains("dcrip"))
        {
            insertColumns.Add("dcrip");
            insertValues.Add("@Dcrip");
        }
        if (columns.Contains("is_active"))
        {
            insertColumns.Add("is_active");
            insertValues.Add("true");
        }
        if (columns.Contains("created_at"))
        {
            insertColumns.Add("created_at");
            insertValues.Add("now()");
        }
        if (columns.Contains("updated_at"))
        {
            insertColumns.Add("updated_at");
            insertValues.Add("now()");
        }

        var insertSql = $@"
INSERT INTO public.tbl_m_user ({string.Join(", ", insertColumns)})
VALUES ({string.Join(", ", insertValues)})
RETURNING id";
        await using var insertCmd = new NpgsqlCommand(insertSql, conn, tx);
        insertCmd.Parameters.AddWithValue("CompanyId", companyId);
        insertCmd.Parameters.AddWithValue("Username", username);
        insertCmd.Parameters.AddWithValue("Email", email);
        insertCmd.Parameters.AddWithValue("Password", passwordHash);
        insertCmd.Parameters.AddWithValue("FullName", fullName);
        insertCmd.Parameters.AddWithValue("Role", "operator");
        if (columns.Contains("dcrip"))
        {
            insertCmd.Parameters.AddWithValue("Dcrip", "bcrypt");
        }

        var inserted = await insertCmd.ExecuteScalarAsync();
        return inserted is int id ? id : Convert.ToInt32(inserted ?? 0);
    }

    private static async Task<string> BindEmployeeIfAvailableAsync(NpgsqlConnection conn, NpgsqlTransaction tx, int userId, int companyId)
    {
        const string findEmployeeSql = @"
SELECT id, code, full_name, user_id
FROM public.tbl_r_employee
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND (user_id IS NULL OR user_id=@UserId)
ORDER BY CASE WHEN user_id=@UserId THEN 0 ELSE 1 END, id
LIMIT 1";
        await using var findCmd = new NpgsqlCommand(findEmployeeSql, conn, tx);
        findCmd.Parameters.AddWithValue("CompanyId", companyId);
        findCmd.Parameters.AddWithValue("UserId", userId);
        await using var reader = await findCmd.ExecuteReaderAsync();
        if (!await reader.ReadAsync())
        {
            await reader.CloseAsync();
            var nik = $"DBGMOB{userId:D4}";
            var code = nik;
            var fullName = "Mobile Debug Operator";
            const string createEmployeeSql = @"
INSERT INTO public.tbl_r_employee
(company_id, user_id, nik, code, full_name, email, phone, job, position, status, created_at, updated_at)
VALUES
(@CompanyId, @UserId, @Nik, @Code, @FullName, @Email, @Phone, @Job, @Position, 'aktif', now(), now())
RETURNING id";
            await using var insertCmd = new NpgsqlCommand(createEmployeeSql, conn, tx);
            insertCmd.Parameters.AddWithValue("CompanyId", companyId);
            insertCmd.Parameters.AddWithValue("UserId", userId);
            insertCmd.Parameters.AddWithValue("Nik", nik);
            insertCmd.Parameters.AddWithValue("Code", code);
            insertCmd.Parameters.AddWithValue("FullName", fullName);
            insertCmd.Parameters.AddWithValue("Email", $"mobile.operator+{companyId}@savera.local");
            insertCmd.Parameters.AddWithValue("Phone", "081100000000");
            insertCmd.Parameters.AddWithValue("Job", "Operator");
            insertCmd.Parameters.AddWithValue("Position", "Operator Unit");
            var createdIdObj = await insertCmd.ExecuteScalarAsync();
            var createdId = createdIdObj is int id ? id : Convert.ToInt32(createdIdObj ?? 0);
            return $"{createdId} | {code} | {fullName} (created)";
        }

        var employeeId = reader.GetInt32(0);
        var employeeCode = reader.IsDBNull(1) ? "-" : reader.GetString(1);
        var employeeName = reader.IsDBNull(2) ? "-" : reader.GetString(2);
        var currentUserId = reader.IsDBNull(3) ? 0 : reader.GetInt32(3);
        await reader.CloseAsync();

        if (currentUserId <= 0)
        {
            const string updateEmployeeSql = @"
UPDATE public.tbl_r_employee
SET user_id=@UserId,
    updated_at=COALESCE(updated_at, now())
WHERE id=@EmployeeId";
            await using var updateCmd = new NpgsqlCommand(updateEmployeeSql, conn, tx);
            updateCmd.Parameters.AddWithValue("UserId", userId);
            updateCmd.Parameters.AddWithValue("EmployeeId", employeeId);
            await updateCmd.ExecuteNonQueryAsync();
        }

        return $"{employeeId} | {employeeCode} | {employeeName}";
    }

    private static async Task UpsertZonaPintarArticlesAsync(NpgsqlConnection conn, NpgsqlTransaction tx, int companyId, int userId)
    {
        foreach (var article in ZonaPintarSeeds)
        {
            const string existsSql = @"
SELECT id
FROM public.tbl_t_zona_pintar_article
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(title)=lower(@Title)
LIMIT 1";
            await using var existsCmd = new NpgsqlCommand(existsSql, conn, tx);
            existsCmd.Parameters.AddWithValue("CompanyId", companyId);
            existsCmd.Parameters.AddWithValue("Title", article.Title);
            var exists = await existsCmd.ExecuteScalarAsync();

            if (exists is not null && exists != DBNull.Value)
            {
                const string updateSql = @"
UPDATE public.tbl_t_zona_pintar_article
SET description=@Description,
    content=@Content,
    category=@Category,
    sort_order=@SortOrder,
    is_active=true,
    updated_by=@UserId,
    updated_at=now()
WHERE id=@Id";
                await using var updateCmd = new NpgsqlCommand(updateSql, conn, tx);
                updateCmd.Parameters.AddWithValue("Description", article.Description);
                updateCmd.Parameters.AddWithValue("Content", article.Content);
                updateCmd.Parameters.AddWithValue("Category", article.Category);
                updateCmd.Parameters.AddWithValue("SortOrder", article.SortOrder);
                updateCmd.Parameters.AddWithValue("UserId", userId);
                updateCmd.Parameters.AddWithValue("Id", Convert.ToInt64(exists));
                await updateCmd.ExecuteNonQueryAsync();
                continue;
            }

            const string insertSql = @"
INSERT INTO public.tbl_t_zona_pintar_article
(company_id, title, description, content, category, sort_order, is_active, published_at, created_by, updated_by, created_at, updated_at)
VALUES
(@CompanyId, @Title, @Description, @Content, @Category, @SortOrder, true, now(), @UserId, @UserId, now(), now())";
            await using var insertCmd = new NpgsqlCommand(insertSql, conn, tx);
            insertCmd.Parameters.AddWithValue("CompanyId", companyId);
            insertCmd.Parameters.AddWithValue("Title", article.Title);
            insertCmd.Parameters.AddWithValue("Description", article.Description);
            insertCmd.Parameters.AddWithValue("Content", article.Content);
            insertCmd.Parameters.AddWithValue("Category", article.Category);
            insertCmd.Parameters.AddWithValue("SortOrder", article.SortOrder);
            insertCmd.Parameters.AddWithValue("UserId", userId);
            await insertCmd.ExecuteNonQueryAsync();
        }
    }

    private static async Task UpsertLegacyP5MTableAsync(NpgsqlConnection conn, NpgsqlTransaction tx, int companyId, int userId)
    {
        var tables = await GetColumnsAsync(conn, tx, "tbl_m_p5m");
        if (tables.Count == 0)
        {
            return;
        }

        var typeMap = await GetColumnTypeMapAsync(conn, tx, "tbl_m_p5m");
        var hasDeletedAt = tables.Contains("deleted_at");
        var statusType = typeMap.TryGetValue("status", out var dt) ? dt : string.Empty;
        var statusValue = ResolveStatusValue(statusType);

        foreach (var question in P5MSeeds)
        {
            var whereDeleted = hasDeletedAt ? "AND deleted_at IS NULL" : string.Empty;
            const string findSqlBase = @"
SELECT id
FROM public.tbl_m_p5m
WHERE company_id=@CompanyId
  AND lower(judul)=lower(@Judul)";
            var findSql = $"{findSqlBase} {whereDeleted} LIMIT 1";
            await using var findCmd = new NpgsqlCommand(findSql, conn, tx);
            findCmd.Parameters.AddWithValue("CompanyId", companyId);
            findCmd.Parameters.AddWithValue("Judul", question.QuestionText);
            var found = await findCmd.ExecuteScalarAsync();

            if (found is not null && found != DBNull.Value)
            {
                var setParts = new List<string> { "konten=@Konten" };
                if (tables.Contains("status")) setParts.Add("status=@Status");
                if (tables.Contains("updated_by")) setParts.Add("updated_by=@UserId");
                if (tables.Contains("updated_at")) setParts.Add("updated_at=now()");

                var updateSql = $"UPDATE public.tbl_m_p5m SET {string.Join(", ", setParts)} WHERE id=@Id";
                await using var updateCmd = new NpgsqlCommand(updateSql, conn, tx);
                updateCmd.Parameters.AddWithValue("Konten", BuildLegacyContent(question));
                AddStatusParameter(updateCmd, statusValue);
                if (tables.Contains("updated_by")) updateCmd.Parameters.AddWithValue("UserId", userId);
                updateCmd.Parameters.AddWithValue("Id", Convert.ToInt64(found));
                await updateCmd.ExecuteNonQueryAsync();
                continue;
            }

            var columns = new List<string> { "company_id", "judul", "konten" };
            var values = new List<string> { "@CompanyId", "@Judul", "@Konten" };
            if (tables.Contains("status")) { columns.Add("status"); values.Add("@Status"); }
            if (tables.Contains("created_by")) { columns.Add("created_by"); values.Add("@UserId"); }
            if (tables.Contains("updated_by")) { columns.Add("updated_by"); values.Add("@UserId"); }
            if (tables.Contains("created_at")) { columns.Add("created_at"); values.Add("now()"); }
            if (tables.Contains("updated_at")) { columns.Add("updated_at"); values.Add("now()"); }

            var insertSql = $@"
INSERT INTO public.tbl_m_p5m ({string.Join(", ", columns)})
VALUES ({string.Join(", ", values)})";
            await using var insertCmd = new NpgsqlCommand(insertSql, conn, tx);
            insertCmd.Parameters.AddWithValue("CompanyId", companyId);
            insertCmd.Parameters.AddWithValue("Judul", question.QuestionText);
            insertCmd.Parameters.AddWithValue("Konten", BuildLegacyContent(question));
            AddStatusParameter(insertCmd, statusValue);
            if (tables.Contains("created_by") || tables.Contains("updated_by"))
            {
                insertCmd.Parameters.AddWithValue("UserId", userId);
            }
            await insertCmd.ExecuteNonQueryAsync();
        }
    }

    private static object ResolveStatusValue(string dataType)
    {
        var normalized = (dataType ?? string.Empty).Trim().ToLowerInvariant();
        if (normalized is "boolean" or "bool")
        {
            return true;
        }
        if (normalized.Contains("int") || normalized.Contains("numeric") || normalized.Contains("decimal"))
        {
            return 1;
        }
        return "aktif";
    }

    private static void AddStatusParameter(NpgsqlCommand command, object statusValue)
    {
        if (!command.CommandText.Contains("@Status", StringComparison.Ordinal))
        {
            return;
        }
        command.Parameters.AddWithValue("Status", statusValue);
    }

    private static string BuildLegacyContent(P5MSeed q)
    {
        string optionScores;
        if (!q.QuestionType.Equals("radio", StringComparison.OrdinalIgnoreCase))
        {
            optionScores = "{}";
        }
        else
        {
            string[] options;
            try
            {
                options = JsonSerializer.Deserialize<string[]>(q.OptionsJson) ?? Array.Empty<string>();
            }
            catch
            {
                options = Array.Empty<string>();
            }

            if (options.Length >= 3)
            {
                var second = q.Weight == 15 ? 5 : 10;
                optionScores = "{\"" + EscapeJson(options[0]) + "\":" + q.Weight +
                               ",\"" + EscapeJson(options[1]) + "\":" + second +
                               ",\"" + EscapeJson(options[2]) + "\":0}";
            }
            else
            {
                optionScores = "{}";
            }
        }
        return "{\"question_type\":\"" + q.QuestionType +
               "\",\"options\":" + q.OptionsJson +
               ",\"weight\":" + q.Weight +
               ",\"is_required\":" + (q.IsRequired ? "true" : "false") +
               ",\"option_scores\":" + optionScores + "}";
    }

    private static string EscapeJson(string value)
    {
        return (value ?? string.Empty)
            .Replace("\\", "\\\\")
            .Replace("\"", "\\\"");
    }

    private static async Task<Dictionary<string, string>> GetColumnTypeMapAsync(NpgsqlConnection conn, NpgsqlTransaction tx, string tableName)
    {
        const string sql = @"
SELECT lower(column_name) AS column_name, lower(data_type) AS data_type
FROM information_schema.columns
WHERE table_schema='public'
  AND table_name=@TableName";
        await using var cmd = new NpgsqlCommand(sql, conn, tx);
        cmd.Parameters.AddWithValue("TableName", tableName);
        await using var reader = await cmd.ExecuteReaderAsync();
        var map = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        while (await reader.ReadAsync())
        {
            var name = reader.IsDBNull(0) ? string.Empty : reader.GetString(0);
            var type = reader.IsDBNull(1) ? string.Empty : reader.GetString(1);
            if (!string.IsNullOrWhiteSpace(name))
            {
                map[name] = type;
            }
        }
        return map;
    }

    private static async Task SeedUserNotificationsAsync(NpgsqlConnection conn, NpgsqlTransaction tx, int companyId, string username, int createdBy)
    {
        if (string.IsNullOrWhiteSpace(username))
        {
            return;
        }

        var samples = new[]
        {
            new { Title = "P5M Hari Ini", Message = "Jangan lupa isi P5M sebelum mulai operasi unit.", Kind = "info", Status = 0, Payload = "{\"action\":\"open_p5m\",\"priority\":\"normal\"}" },
            new { Title = "Zona Pintar Update", Message = "Materi safety terbaru sudah tersedia. Silakan baca sebelum shift.", Kind = "info", Status = 0, Payload = "{\"action\":\"open_zona_pintar\"}" },
            new { Title = "Reminder Istirahat", Message = "Deteksi fatigue meningkat. Ambil istirahat terkontrol sesuai SOP.", Kind = "warning", Status = 1, Payload = "{\"action\":\"open_ftw\",\"level\":\"warning\"}" }
        };

        foreach (var n in samples)
        {
            const string existsSql = @"
SELECT id
FROM public.tbl_t_user_notification
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)
  AND lower(title)=lower(@Title)
LIMIT 1";
            await using var existsCmd = new NpgsqlCommand(existsSql, conn, tx);
            existsCmd.Parameters.AddWithValue("CompanyId", companyId);
            existsCmd.Parameters.AddWithValue("Username", username);
            existsCmd.Parameters.AddWithValue("Title", n.Title);
            var exists = await existsCmd.ExecuteScalarAsync();

            if (exists is not null && exists != DBNull.Value)
            {
                const string updateSql = @"
UPDATE public.tbl_t_user_notification
SET message=@Message,
    kind=@Kind,
    status=@Status,
    payload=CAST(@Payload AS jsonb),
    read_at=CASE WHEN @Status=1 THEN COALESCE(read_at, now()) ELSE NULL END,
    updated_at=now()
WHERE id=@Id";
                await using var updateCmd = new NpgsqlCommand(updateSql, conn, tx);
                updateCmd.Parameters.AddWithValue("Message", n.Message);
                updateCmd.Parameters.AddWithValue("Kind", n.Kind);
                updateCmd.Parameters.AddWithValue("Status", n.Status);
                updateCmd.Parameters.AddWithValue("Payload", n.Payload);
                updateCmd.Parameters.AddWithValue("Id", Convert.ToInt64(exists));
                await updateCmd.ExecuteNonQueryAsync();
                continue;
            }

            const string insertSql = @"
INSERT INTO public.tbl_t_user_notification
(company_id, username, title, message, kind, status, payload, created_by, read_at, created_at, updated_at)
VALUES
(@CompanyId, @Username, @Title, @Message, @Kind, @Status, CAST(@Payload AS jsonb), @CreatedBy,
 CASE WHEN @Status=1 THEN now() ELSE NULL END, now(), now())";
            await using var insertCmd = new NpgsqlCommand(insertSql, conn, tx);
            insertCmd.Parameters.AddWithValue("CompanyId", companyId);
            insertCmd.Parameters.AddWithValue("Username", username);
            insertCmd.Parameters.AddWithValue("Title", n.Title);
            insertCmd.Parameters.AddWithValue("Message", n.Message);
            insertCmd.Parameters.AddWithValue("Kind", n.Kind);
            insertCmd.Parameters.AddWithValue("Status", n.Status);
            insertCmd.Parameters.AddWithValue("Payload", n.Payload);
            insertCmd.Parameters.AddWithValue("CreatedBy", createdBy);
            await insertCmd.ExecuteNonQueryAsync();
        }
    }

    private static async Task<int> CountUserNotificationsAsync(NpgsqlConnection conn, int companyId, string username)
    {
        const string sql = @"
SELECT COUNT(1)
FROM public.tbl_t_user_notification
WHERE deleted_at IS NULL
  AND company_id=@CompanyId
  AND lower(username)=lower(@Username)";
        await using var cmd = new NpgsqlCommand(sql, conn);
        cmd.Parameters.AddWithValue("CompanyId", companyId);
        cmd.Parameters.AddWithValue("Username", username ?? string.Empty);
        var obj = await cmd.ExecuteScalarAsync();
        return obj is int i ? i : Convert.ToInt32(obj ?? 0);
    }

    private static async Task<int> CountRowsAsync(NpgsqlConnection conn, string tableName, int companyId)
    {
        var tableOnly = tableName.Contains('.') ? tableName.Split('.').Last() : tableName;
        var columns = await GetColumnsAsync(conn, null, tableOnly);
        var where = new List<string> { "company_id=@CompanyId" };
        if (columns.Contains("is_active"))
        {
            where.Add("COALESCE(is_active, true)=true");
        }
        if (columns.Contains("deleted_at"))
        {
            where.Add("deleted_at IS NULL");
        }

        var sql = $@"
SELECT COUNT(1)
FROM {tableName}
WHERE {string.Join(" AND ", where)}";
        await using var cmd = new NpgsqlCommand(sql, conn);
        cmd.Parameters.AddWithValue("CompanyId", companyId);
        var obj = await cmd.ExecuteScalarAsync();
        return obj is int i ? i : Convert.ToInt32(obj ?? 0);
    }
}
