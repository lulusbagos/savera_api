param(
    [string]$ApiPath = "D:\APPS\savera-api\savera-api",
    [string]$AdminPath = "D:\APPS\savera-admin\savera-admin",
    [string]$Branch = "main",
    [string]$ApiHealthcheckUrl = "",
    [string]$AdminHealthcheckUrl = "",
    [switch]$SkipAdmin
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

& (Join-Path $scriptDir "deploy_api.ps1") -RepoPath $ApiPath -Branch $Branch -HealthcheckUrl $ApiHealthcheckUrl

if (-not $SkipAdmin) {
    & (Join-Path $scriptDir "deploy_admin.ps1") -RepoPath $AdminPath -Branch $Branch -HealthcheckUrl $AdminHealthcheckUrl
}

Write-Host "All requested deployments completed."
