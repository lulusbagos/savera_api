# Savera Error Monitor - cek error Laravel tiap 5 menit
# Menulis ringkasan error baru ke logs/error-monitor.log

$projectRoot = "D:\APPS\savera-api\savera-api"
$logsDir = Join-Path $projectRoot "logs"
$laravelLogDir = Join-Path $projectRoot "storage\logs"
$stateFile = Join-Path $logsDir "error-monitor.state.json"
$summaryFile = Join-Path $logsDir "error-monitor.log"
$intervalSeconds = 300

if (-not (Test-Path $logsDir)) {
    New-Item -Path $logsDir -ItemType Directory -Force | Out-Null
}

function Write-Summary {
    param([string]$Message)
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$ts] $Message"
    Add-Content -Path $summaryFile -Value $line -Encoding UTF8
    Write-Host $line
}

function Load-State {
    if (Test-Path $stateFile) {
        try {
            return Get-Content -Path $stateFile -Raw | ConvertFrom-Json
        } catch {
            return [PSCustomObject]@{ file = ""; line = 0 }
        }
    }
    return [PSCustomObject]@{ file = ""; line = 0 }
}

function Save-State {
    param([string]$FileName, [int]$LineNo)
    $obj = [PSCustomObject]@{
        file = $FileName
        line = $LineNo
        updated_at = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
    }
    $obj | ConvertTo-Json | Set-Content -Path $stateFile -Encoding UTF8
}

function Read-NewErrorLines {
    param(
        [string]$FilePath,
        [int]$LastLine
    )
    $all = Get-Content -Path $FilePath -ErrorAction SilentlyContinue
    if (-not $all) { return @(), 0 }
    $total = $all.Count
    if ($LastLine -lt 0) { $LastLine = 0 }
    if ($LastLine -gt $total) { $LastLine = 0 } # file rotated/truncated
    $newLines = @()
    if ($total -gt $LastLine) {
        $newLines = $all[$LastLine..($total - 1)]
    }
    return ,$newLines, $total
}

function Extract-ErrorSummaries {
    param([string[]]$Lines)
    $entries = @()
    for ($i = 0; $i -lt $Lines.Count; $i++) {
        $line = [string]$Lines[$i]
        if ($line -match '^\[(?<dt>[0-9\-\:\s]+)\]\s+production\.ERROR:\s+(?<msg>.+)$') {
            $entries += [PSCustomObject]@{
                timestamp = $Matches['dt'].Trim()
                message = $Matches['msg'].Trim()
            }
        }
    }
    return $entries
}

Write-Summary "Error monitor started. Interval=${intervalSeconds}s"

while ($true) {
    try {
        $state = Load-State
        $latestLog = Get-ChildItem -Path $laravelLogDir -Filter "laravel-*.log" -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTime -Descending |
            Select-Object -First 1

        if (-not $latestLog) {
            Write-Summary "No laravel log found in $laravelLogDir"
            Start-Sleep -Seconds $intervalSeconds
            continue
        }

        $isSameFile = ($state.file -eq $latestLog.Name)
        $lastLine = if ($isSameFile) { [int]$state.line } else { 0 }
        $result = Read-NewErrorLines -FilePath $latestLog.FullName -LastLine $lastLine
        $newLines = $result[0]
        $newPos = [int]$result[1]

        $errors = Extract-ErrorSummaries -Lines $newLines
        if ($errors.Count -gt 0) {
            Write-Summary ("{0} new ERROR(s) in {1}" -f $errors.Count, $latestLog.Name)
            foreach ($err in $errors) {
                Write-Summary ("ERROR @ {0} | {1}" -f $err.timestamp, $err.message)
            }
        } else {
            Write-Summary ("OK - no new production.ERROR in {0}" -f $latestLog.Name)
        }

        Save-State -FileName $latestLog.Name -LineNo $newPos
    } catch {
        Write-Summary ("MONITOR ERROR: {0}" -f $_.Exception.Message)
    }

    Start-Sleep -Seconds $intervalSeconds
}
