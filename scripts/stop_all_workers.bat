@echo off
title Stop All AI Workers
echo [ %date% %time% ] Stopping all AI Worker processes...

:: Kill all PHP processes that are running AIWorker.php
powershell -Command "$workers = Get-CimInstance Win32_Process -Filter \"Name='php.exe' AND CommandLine LIKE '%%AIWorker.php%%'\"; if ($workers) { $workers | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue } }"

:: Also close the minimized CMD windows if they are still open
taskkill /F /FI "WINDOWTITLE eq AI Worker *" /T >nul 2>&1

echo [ %date% %time% ] All workers stopped.
timeout /t 3
