$ErrorActionPreference = "Stop"

$projectRoot = "D:\APPS\savera-api\savera-api"
$phpExe = "C:\xampp\php\php.exe"
$artisan = Join-Path $projectRoot "artisan"
$logDir = Join-Path $projectRoot "logs"
$logFile = Join-Path $logDir "queue-worker.log"

if (!(Test-Path $phpExe)) {
    throw "PHP executable not found at $phpExe"
}

if (!(Test-Path $artisan)) {
    throw "Artisan not found at $artisan"
}

if (!(Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

while ($true) {
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Add-Content -Path $logFile -Value "[$ts] Starting queue worker..."

    try {
        & $phpExe $artisan queue:work database --queue=mobile-metrics --tries=1 --sleep=1 --timeout=120 --max-time=3600 --memory=256 *>> $logFile
    } catch {
        $errTs = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        Add-Content -Path $logFile -Value "[$errTs] Worker crashed: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds 5
}
