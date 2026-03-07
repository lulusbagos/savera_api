[CmdletBinding()]
param(
    [string]$ProjectPath = "file savera.csproj",
    [string]$PublishDir = "D:\Publish\asvera-api",
    [string]$Configuration = "Release",
    [string]$BindHost = "0.0.0.0",
    [int]$Port = 8000,
    [string]$UploadRoot = "",
    [string]$AdminImageBaseUrl = ""
)

$ErrorActionPreference = "Stop"

function Write-Step([string]$Message) {
    Write-Host "==> $Message" -ForegroundColor Cyan
}

if (!(Test-Path $ProjectPath)) {
    throw "Project not found: $ProjectPath"
}

if (!(Test-Path $PublishDir)) {
    New-Item -ItemType Directory -Path $PublishDir | Out-Null
}

Write-Step "Publishing API to $PublishDir"
dotnet publish $ProjectPath -c $Configuration -o $PublishDir

$dllName = [System.IO.Path]::GetFileNameWithoutExtension($ProjectPath) + ".dll"
$dllPath = Join-Path $PublishDir $dllName
if (!(Test-Path $dllPath)) {
    throw "Published DLL not found: $dllPath"
}

$runScriptPath = Join-Path $PublishDir "run-api.ps1"
$runScript = @"
param(
    [string]`$BindHost = "$BindHost",
    [int]`$Port = $Port,
    [string]`$UploadRoot = "$UploadRoot",
    [string]`$AdminImageBaseUrl = "$AdminImageBaseUrl",
    [string]`$Urls = ""
)

if ([string]::IsNullOrWhiteSpace(`$Urls)) {
    `$Urls = "http://`$BindHost:`$Port"
}

`$env:ASPNETCORE_URLS = `$Urls

if (-not [string]::IsNullOrWhiteSpace(`$UploadRoot)) {
    `$env:App__UploadRoot = `$UploadRoot
}

if (-not [string]::IsNullOrWhiteSpace(`$AdminImageBaseUrl)) {
    `$env:App__AdminImageBaseUrl = `$AdminImageBaseUrl
}

Write-Host "Starting API on `$Urls" -ForegroundColor Green
if (`$env:App__UploadRoot) {
    Write-Host "UploadRoot: `$(`$env:App__UploadRoot)" -ForegroundColor Green
}
if (`$env:App__AdminImageBaseUrl) {
    Write-Host "AdminImageBaseUrl: `$(`$env:App__AdminImageBaseUrl)" -ForegroundColor Green
}

dotnet ".\$dllName"
"@
Set-Content -Path $runScriptPath -Value $runScript -Encoding UTF8

$readmePath = Join-Path $PublishDir "RUN-INSTRUCTIONS.txt"
$readme = @"
Publish output is ready.

Run API (default):
  powershell -ExecutionPolicy Bypass -File .\run-api.ps1

Run API (custom port):
  powershell -ExecutionPolicy Bypass -File .\run-api.ps1 -Port 8085

Run API (custom host+port):
  powershell -ExecutionPolicy Bypass -File .\run-api.ps1 -BindHost 127.0.0.1 -Port 9090

Run API (custom upload folder):
  powershell -ExecutionPolicy Bypass -File .\run-api.ps1 -UploadRoot "D:\Data\Uploads"

Run API (explicit URLs):
  powershell -ExecutionPolicy Bypass -File .\run-api.ps1 -Urls "http://0.0.0.0:8000;http://127.0.0.1:8001"
"@
Set-Content -Path $readmePath -Value $readme -Encoding UTF8

Write-Step "Done"
Write-Host "PublishDir: $PublishDir" -ForegroundColor Yellow
Write-Host "Run script: $runScriptPath" -ForegroundColor Yellow
