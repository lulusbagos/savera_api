@echo off
title SAVERA Queue Worker
cd /d D:\APPS\savera-api\savera-api
echo [%date% %time%] Memulai queue worker...
:loop
C:\xampp\php\php.exe artisan queue:work --queue=mobile-metrics,default --tries=3 --max-time=3600 --sleep=3 --memory=256
echo [%date% %time%] Worker berhenti, restart dalam 3 detik...
timeout /t 3 /nobreak >nul
goto loop
