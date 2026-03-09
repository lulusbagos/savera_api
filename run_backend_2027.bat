@echo off
setlocal EnableExtensions

REM ==========================================================
REM Savera Backend Runner (Port 2027)
REM - Auto create storage folder for JSON/avatar uploads
REM - Start ASP.NET API on http://0.0.0.0:2027
REM ==========================================================

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

where dotnet >nul 2>&1
if errorlevel 1 (
  echo [ERROR] .NET SDK/Runtime belum terpasang atau tidak ada di PATH.
  echo Install dulu .NET 8 lalu jalankan ulang file ini.
  pause
  exit /b 1
)

REM Samakan dengan nilai App:UploadRoot di appsettings.json
set "UPLOAD_ROOT=D:\4. PROJECT\6. Android\API\file savera\storage\app"

echo [INFO] Menyiapkan folder upload...
if not exist "%UPLOAD_ROOT%" mkdir "%UPLOAD_ROOT%"
if not exist "%UPLOAD_ROOT%\avatar" mkdir "%UPLOAD_ROOT%\avatar"

REM Folder metric akan dibuat otomatis saat write, tapi dipra-siapkan agar rapi
if not exist "%UPLOAD_ROOT%\data_summary" mkdir "%UPLOAD_ROOT%\data_summary"
if not exist "%UPLOAD_ROOT%\data_activity" mkdir "%UPLOAD_ROOT%\data_activity"
if not exist "%UPLOAD_ROOT%\data_sleep" mkdir "%UPLOAD_ROOT%\data_sleep"
if not exist "%UPLOAD_ROOT%\data_stress" mkdir "%UPLOAD_ROOT%\data_stress"
if not exist "%UPLOAD_ROOT%\data_spo2" mkdir "%UPLOAD_ROOT%\data_spo2"
if not exist "%UPLOAD_ROOT%\data_respiratory_rate" mkdir "%UPLOAD_ROOT%\data_respiratory_rate"
if not exist "%UPLOAD_ROOT%\data_pai" mkdir "%UPLOAD_ROOT%\data_pai"
if not exist "%UPLOAD_ROOT%\data_temperature" mkdir "%UPLOAD_ROOT%\data_temperature"
if not exist "%UPLOAD_ROOT%\data_cycling" mkdir "%UPLOAD_ROOT%\data_cycling"
if not exist "%UPLOAD_ROOT%\data_weight" mkdir "%UPLOAD_ROOT%\data_weight"
if not exist "%UPLOAD_ROOT%\data_heart_rate_max" mkdir "%UPLOAD_ROOT%\data_heart_rate_max"
if not exist "%UPLOAD_ROOT%\data_heart_rate_resting" mkdir "%UPLOAD_ROOT%\data_heart_rate_resting"
if not exist "%UPLOAD_ROOT%\data_heart_rate_manual" mkdir "%UPLOAD_ROOT%\data_heart_rate_manual"
if not exist "%UPLOAD_ROOT%\data_hrv_summary" mkdir "%UPLOAD_ROOT%\data_hrv_summary"
if not exist "%UPLOAD_ROOT%\data_hrv_value" mkdir "%UPLOAD_ROOT%\data_hrv_value"
if not exist "%UPLOAD_ROOT%\data_body_energy" mkdir "%UPLOAD_ROOT%\data_body_energy"

set "ASPNETCORE_URLS=http://0.0.0.0:2027"
set "ASPNETCORE_ENVIRONMENT=Production"

echo [INFO] Starting backend di %ASPNETCORE_URLS%
echo [INFO] Project: "%SCRIPT_DIR%file savera.csproj"
echo [INFO] Upload root: "%UPLOAD_ROOT%"
echo.

dotnet run --project "%SCRIPT_DIR%file savera.csproj" --no-launch-profile
set "EXITCODE=%ERRORLEVEL%"

if not "%EXITCODE%"=="0" (
  echo.
  echo [ERROR] Backend berhenti dengan kode %EXITCODE%.
  pause
)

exit /b %EXITCODE%
