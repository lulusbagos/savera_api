param(
    [string]$PhpExe = "C:\xampp\php\php.exe",
    [string]$ProjectRoot = "D:\APPS\savera-api\savera-api",
    [switch]$SkipTests,
    [switch]$SkipLoadSim,
    [switch]$RunRealStress,
    [string]$PublicBaseUrl = "https://savera_api.ungguldinamika.com",
    [string]$LocalBaseUrl = "http://192.168.151.20:2026",
    [string]$CompanyCode = "UDU",
    [string]$UsersCsv = "scripts\users.working.csv",
    [int]$SimUsers = 40,
    [int]$SimRetries = 2,
    [int]$StressUsers = 50,
    [int]$StressConcurrency = 30,
    [int]$StressRequestsPerUser = 1
)

$ErrorActionPreference = "Stop"
Set-Location $ProjectRoot

function Assert-SafeTestingEnv {
    $testingEnvPath = Join-Path $ProjectRoot ".env.testing"
    if (-not (Test-Path $testingEnvPath)) {
        throw ".env.testing tidak ditemukan. Untuk keamanan, testing diblokir agar tidak menyentuh DB production."
    }

    $raw = Get-Content $testingEnvPath -Raw
    $dbConnectionMatch = [regex]::Match($raw, "(?m)^DB_CONNECTION=(.+)$")
    $dbDatabaseMatch = [regex]::Match($raw, "(?m)^DB_DATABASE=(.+)$")
    $dbConnection = if ($dbConnectionMatch.Success) { $dbConnectionMatch.Groups[1].Value.Trim() } else { "" }
    $dbDatabase = if ($dbDatabaseMatch.Success) { $dbDatabaseMatch.Groups[1].Value.Trim() } else { "" }

    $isSqlite = $dbConnection -eq "sqlite"
    $isTestDb = $dbDatabase.ToLower().Contains("test")
    if (-not $isSqlite -and -not $isTestDb) {
        throw "DB testing tidak aman di .env.testing (DB_CONNECTION=$dbConnection, DB_DATABASE=$dbDatabase). Gunakan sqlite atau nama DB yang mengandung 'test'."
    }
}

function Write-Step {
    param([string]$Message)
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$ts] $Message"
}

function Invoke-Checked {
    param(
        [string]$Name,
        [scriptblock]$Action
    )
    Write-Step "START: $Name"
    & $Action
    if ($LASTEXITCODE -ne 0) {
        throw "FAILED: $Name (exit code $LASTEXITCODE)"
    }
    Write-Step "OK: $Name"
}

Write-Step "Operational checklist started"

Invoke-Checked -Name "Queue task status" -Action {
    schtasks /Query /TN "\Savera-Queue-Worker" /V /FO LIST
}

Invoke-Checked -Name "Scheduler task status" -Action {
    schtasks /Query /TN "\Savera-Laravel-Scheduler" /V /FO LIST
}

Invoke-Checked -Name "Laravel schedule list" -Action {
    & $PhpExe artisan schedule:list
}

if (-not $SkipTests) {
    Assert-SafeTestingEnv

    Invoke-Checked -Name "Feature test suite" -Action {
        & $PhpExe artisan test --env=testing --testsuite=Feature
    }

    Invoke-Checked -Name "Unit test suite" -Action {
        & $PhpExe artisan test --env=testing --testsuite=Unit
    }
}

if (-not $SkipLoadSim) {
    Invoke-Checked -Name "Load simulation (internal kernel)" -Action {
        & $PhpExe artisan mobile:simulate-load --users=$SimUsers --upload-retries=$SimRetries --benchmark --benchmark-iterations=2 --persist-storage --delay-ms=120 --summary-delay-ms=180 --detail-delay-ms=260
    }
}

if ($RunRealStress) {
    if (-not (Test-Path $UsersCsv)) {
        throw "UsersCsv not found: $UsersCsv"
    }

    Invoke-Checked -Name "Real stress - public route" -Action {
        python scripts/stress_mobile_upload.py --base-url $PublicBaseUrl --company $CompanyCode --users-csv $UsersCsv --target-users $StressUsers --concurrency $StressConcurrency --requests-per-user $StressRequestsPerUser
    }

    Invoke-Checked -Name "Real stress - local route" -Action {
        python scripts/stress_mobile_upload.py --base-url $LocalBaseUrl --company $CompanyCode --users-csv $UsersCsv --target-users $StressUsers --concurrency $StressConcurrency --requests-per-user $StressRequestsPerUser
    }
}

Write-Step "Operational checklist finished successfully"
