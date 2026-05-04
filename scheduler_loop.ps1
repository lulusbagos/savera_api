$ErrorActionPreference = "Stop"

$projectRoot = "D:\APPS\savera-api\savera-api"
$phpExe = "C:\xampp\php\php.exe"
$artisan = Join-Path $projectRoot "artisan"
$logFile = "D:\APPS\savera-api\savera-api\logs\scheduler.log"

function Write-Log {
    param([string]$Message)
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$ts] $Message"
    Add-Content -Path $logFile -Value $line -Encoding UTF8
    Write-Host $line
}

if (-not (Test-Path $phpExe)) {
    throw "PHP executable not found at $phpExe"
}

if (-not (Test-Path $artisan)) {
    throw "Artisan file not found at $artisan"
}

$null = Set-Location $projectRoot

$logDir = Split-Path -Parent $logFile
if (-not (Test-Path $logDir)) {
    New-Item -Path $logDir -ItemType Directory -Force | Out-Null
}

Write-Log "Laravel scheduler loop started"

while ($true) {
    try {
        Write-Log "Starting schedule:work"
        & $phpExe $artisan schedule:work *>> $logFile
        Write-Log "schedule:work exited, restarting in 5s"
    } catch {
        Write-Log "schedule:work crashed: $($_.Exception.Message). Restarting in 5s"
    }

    Start-Sleep -Seconds 5
}
