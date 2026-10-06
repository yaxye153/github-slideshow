@echo off
REM Sends the NADIIF LAUNDRY daily email backup.
REM Use this file in Windows Task Scheduler (see README).
"C:\xampp\php\php.exe" "%~dp0cron.php"
