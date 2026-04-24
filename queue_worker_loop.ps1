# Savera Queue Worker - Loop otomatis restart jika berhenti
# Dijalankan oleh Task Scheduler saat boot (SYSTEM account)

$phpExe   = "C:\xampp\php\php.exe"
$artisan  = "D:\APPS\savera-api\savera-api\artisan"
$logFile  = "D:\APPS\savera-api\savera-api\logs\queue-worker.log"
$maxTime  = 3600   # worker akan restart setiap 1 jam (cegah memory leak)

function Write-Log {
    param([string]$msg)
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$ts] $msg"
    Add-Content -Path $logFile -Value $line -Encoding UTF8
    Write-Host $line
}

# Tunggu php-cgi dan nginx siap (maks 60 detik)
$waited = 0
while (-not (Get-Process php-cgi -ErrorAction SilentlyContinue) -and $waited -lt 60) {
    Start-Sleep -Seconds 5
    $waited += 5
}

Write-Log "Queue worker starting (php-cgi ready after ${waited}s)"

while ($true) {
    Write-Log "Starting queue:work --queue=mobile-metrics,default"
    try {
        & $phpExe $artisan queue:work `
            --queue=mobile-metrics,default `
            --tries=3 `
            --max-time=$maxTime `
            --sleep=3 `
            --memory=256
        Write-Log "queue:work exited normally (max-time reached), restarting..."
    }
    catch {
        Write-Log "queue:work ERROR: $($_.Exception.Message), restarting in 5s..."
    }
    Start-Sleep -Seconds 5
}
