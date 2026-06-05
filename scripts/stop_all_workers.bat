@echo off
title Stop All AI Workers
echo [ %date% %time% ] Stopping all AI Worker processes...

:: Kill all PHP processes that are running AIWorker.php
powershell -Command "Get-Process php -ErrorAction SilentlyContinue | Where-Object { (Get-CimInstance Win32_Process -Filter \"ProcessId = $($_.Id)\").CommandLine -like '*AIWorker.php*' } | Stop-Process -Force"

:: Also close the minimized CMD windows if they are still open
taskkill /F /FI "WINDOWTITLE eq AI Worker *" /T >nul 2>&1

echo [ %date% %time% ] All workers stopped.
pause
