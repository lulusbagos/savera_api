param(
    [Parameter(Mandatory = $true)]
    [string]$RepoUrl,

    [Parameter(Mandatory = $true)]
    [string]$RunnerToken,

    [string]$RunnerRoot = "D:\actions-runner\savera-api",
    [string]$RunnerName = "",
    [string]$Labels = "savera-prod,api",
    [switch]$ReplaceExisting
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Assert-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Run PowerShell as Administrator."
    }
}

function Get-LatestRunnerAssetUrl {
    $release = Invoke-RestMethod -Uri "https://api.github.com/repos/actions/runner/releases/latest" -UseBasicParsing
    $asset = $release.assets | Where-Object { $_.name -like "actions-runner-win-x64-*.zip" } | Select-Object -First 1
    if (-not $asset) {
        throw "Cannot find latest actions runner win-x64 asset."
    }
    return @{
        Url  = $asset.browser_download_url
        Name = $asset.name
    }
}

Assert-Admin

if ([string]::IsNullOrWhiteSpace($RunnerName)) {
    $RunnerName = ("savera-prod-" + $env:COMPUTERNAME.ToLower())
}

if (-not (Test-Path -LiteralPath $RunnerRoot)) {
    New-Item -ItemType Directory -Path $RunnerRoot -Force | Out-Null
}

$runnerMarker = Join-Path $RunnerRoot ".runner"
if ((Test-Path -LiteralPath $runnerMarker) -and (-not $ReplaceExisting)) {
    throw "Runner already configured at $RunnerRoot. Use -ReplaceExisting to replace."
}

if ($ReplaceExisting -and (Test-Path -LiteralPath $runnerMarker)) {
    Push-Location $RunnerRoot
    try {
        if (Test-Path -LiteralPath ".\svc.cmd") {
            cmd /c ".\svc.cmd stop" | Out-Null
            cmd /c ".\svc.cmd uninstall" | Out-Null
        }
        cmd /c ".\config.cmd remove --token $RunnerToken" | Out-Null
    }
    finally {
        Pop-Location
    }
}

$asset = Get-LatestRunnerAssetUrl
$zipPath = Join-Path $RunnerRoot $asset.Name

Write-Host "Downloading runner: $($asset.Url)"
Invoke-WebRequest -Uri $asset.Url -OutFile $zipPath -UseBasicParsing

Write-Host "Extracting runner..."
Expand-Archive -Path $zipPath -DestinationPath $RunnerRoot -Force
Remove-Item -LiteralPath $zipPath -Force

Push-Location $RunnerRoot
try {
    Write-Host "Configuring runner..."
    $args = @(
        "--unattended",
        "--url", $RepoUrl,
        "--token", $RunnerToken,
        "--name", $RunnerName,
        "--labels", $Labels,
        "--work", "_work",
        "--replace"
    )

    & .\config.cmd @args
    if ($LASTEXITCODE -ne 0) {
        throw "config.cmd failed with exit code $LASTEXITCODE"
    }

    Write-Host "Installing service..."
    cmd /c ".\svc.cmd install"
    if ($LASTEXITCODE -ne 0) {
        throw "svc.cmd install failed with exit code $LASTEXITCODE"
    }

    Write-Host "Starting service..."
    cmd /c ".\svc.cmd start"
    if ($LASTEXITCODE -ne 0) {
        throw "svc.cmd start failed with exit code $LASTEXITCODE"
    }
}
finally {
    Pop-Location
}

Write-Host "Runner installed successfully."
