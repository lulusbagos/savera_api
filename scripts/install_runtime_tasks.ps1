$ErrorActionPreference = "Stop"

$projectRoot = "D:\APPS\savera-api\savera-api"
$psExe = "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe"

$queueTaskName = "\Savera-Queue-Worker"
$queueScript = Join-Path $projectRoot "queue_worker_loop.ps1"
$queueCmd = "$psExe -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$queueScript`""

$schedulerTaskName = "\Savera-Laravel-Scheduler"
$schedulerScript = Join-Path $projectRoot "scheduler_loop.ps1"
$schedulerCmd = "$psExe -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$schedulerScript`""

Write-Host "Registering/updating $queueTaskName ..."
schtasks /Create /TN $queueTaskName /TR $queueCmd /SC ONSTART /RL HIGHEST /RU SYSTEM /F | Out-Null

Write-Host "Registering/updating $schedulerTaskName ..."
schtasks /Create /TN $schedulerTaskName /TR $schedulerCmd /SC ONSTART /RL HIGHEST /RU SYSTEM /F | Out-Null

Write-Host "Starting $queueTaskName ..."
schtasks /Run /TN $queueTaskName | Out-Null

Write-Host "Starting $schedulerTaskName ..."
schtasks /Run /TN $schedulerTaskName | Out-Null

Write-Host "Done. Runtime tasks are installed and started."
