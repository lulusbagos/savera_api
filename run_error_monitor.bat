@echo off
title SAVERA Error Monitor
cd /d D:\APPS\savera-api\savera-api
echo [%date% %time%] Memulai error monitor (cek tiap 5 menit)...
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\APPS\savera-api\savera-api\error_monitor_loop.ps1"
