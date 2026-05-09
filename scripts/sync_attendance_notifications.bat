@echo off
cd /d D:\APPS\savera-api\savera-api
C:\xampp\php\php.exe artisan notifications:sync-attendance --company-id=2 --days=30
