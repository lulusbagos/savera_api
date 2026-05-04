param(
    [string]$RepoPath = "D:\APPS\savera-admin\savera-admin",
    [string]$Branch = "main",
    [string]$HealthcheckUrl = ""
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Invoke-Step {
    param(
        [string]$Command,
        [string]$WorkingDirectory
    )

    Write-Host ">> $Command"
    Push-Location $WorkingDirectory
    try {
        cmd /c $Command
        if ($LASTEXITCODE -ne 0) {
            throw "Command failed with exit code ${LASTEXITCODE}: $Command"
        }
    }
    finally {
        Pop-Location
    }
}

if (-not (Test-Path -LiteralPath $RepoPath)) {
    throw "Admin path not found: $RepoPath"
}

if (-not (Test-Path -LiteralPath (Join-Path $RepoPath ".git"))) {
    Write-Warning "Admin deploy skipped: $RepoPath is not a git repository."
    exit 0
}

Invoke-Step "git rev-parse --is-inside-work-tree" $RepoPath

$dirty = (git -C $RepoPath status --porcelain)
if ($dirty) {
    throw "Deployment aborted: working tree is dirty in $RepoPath. Commit/stash local changes first."
}

Invoke-Step "git fetch origin $Branch" $RepoPath
Invoke-Step "git checkout $Branch" $RepoPath
Invoke-Step "git pull --ff-only origin $Branch" $RepoPath

Invoke-Step "composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction" $RepoPath
Invoke-Step "php artisan migrate --force" $RepoPath
Invoke-Step "php artisan optimize:clear" $RepoPath
Invoke-Step "php artisan config:cache" $RepoPath

if ([string]::IsNullOrWhiteSpace($HealthcheckUrl) -eq $false) {
    Write-Host ">> Health check: $HealthcheckUrl"
    $resp = Invoke-WebRequest -Uri $HealthcheckUrl -Method GET -TimeoutSec 20 -UseBasicParsing
    if ($resp.StatusCode -lt 200 -or $resp.StatusCode -ge 400) {
        throw "Health check failed with status code $($resp.StatusCode)"
    }
}

Write-Host "Admin deployment completed successfully."
