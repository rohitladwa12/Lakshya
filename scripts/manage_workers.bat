@echo off
setlocal EnableDelayedExpansion
title Lakshya AI Worker Manager
cd /d "%~dp0.."

:: Detect PHP Executable
where php >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    set "PHP_BIN=php"
) else if exist "C:\php82\php.exe" (
    set "PHP_BIN=C:\php82\php.exe"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else (
    set "PHP_BIN=php"
)

:: Ensure logs directory exists
if not exist "logs" mkdir "logs"

:: If an argument was provided (Task Scheduler, CLI, etc.), execute directly
if /i "%~1"=="restart" goto :RESTART_GRACEFUL
if /i "%~1"=="start" goto :START_SILENT
if /i "%~1"=="start-silent" goto :START_SILENT
if /i "%~1"=="start-visible" goto :START_VISIBLE
if /i "%~1"=="stop" goto :STOP_WORKERS
if /i "%~1"=="status" goto :STATUS_WORKERS
if /i "%~1"=="monitor" goto :OPEN_MONITOR

:MENU
cls
echo ======================================================================
echo                 LAKSHYA AI WORKER MASTER CONTROLLER
echo ======================================================================
echo.
echo   [1] Start 5 Workers (Silent Background - Recommended)
echo   [2] Start 5 Workers (Visible Debug Windows)
echo   [3] Graceful Restart / Refresh (Safe for active tests)
echo   [4] Stop All AI Workers
echo   [5] Check Live Status
echo   [6] Open Web Monitor Dashboard
echo   [0] Exit
echo.
echo ======================================================================
set /p choice="Enter your choice (0-6): "

if "%choice%"=="1" goto :START_SILENT
if "%choice%"=="2" goto :START_VISIBLE
if "%choice%"=="3" goto :RESTART_GRACEFUL
if "%choice%"=="4" goto :STOP_WORKERS
if "%choice%"=="5" goto :STATUS_WORKERS
if "%choice%"=="6" goto :OPEN_MONITOR
if "%choice%"=="0" exit /b 0

echo Invalid choice!
timeout /t 2 >nul
goto :MENU

:START_SILENT
echo.
echo [ %date% %time% ] Stopping any existing workers before launch...
call :INTERNAL_STOP_SILENT

echo [ %date% %time% ] Launching 5 Silent Background AI Workers...
set "VBS_FILE=%temp%\run_silent_worker.vbs"
echo Set WshShell = CreateObject("WScript.Shell") > "%VBS_FILE%"
for /L %%i in (1,1,5) do (
    echo WshShell.Run "cmd /c ""%PHP_BIN%"" src\Workers\AIWorker.php %%i >> logs\ai_worker_%%i.log 2>&1", 0 >> "%VBS_FILE%"
    echo WScript.Sleep 1500 >> "%VBS_FILE%"
)
echo Set WshShell = Nothing >> "%VBS_FILE%"
wscript.exe "%VBS_FILE%"
del "%VBS_FILE%"

echo [ %date% %time% ] 5 AI Workers successfully launched in background.
echo Monitor them in logs\ai_worker_*.log or via public/admin/ai_monitor.php
if "%~1"=="" (
    echo.
    pause
    goto :MENU
)
exit /b 0

:START_VISIBLE
echo.
echo [ %date% %time% ] Stopping any existing workers before launch...
call :INTERNAL_STOP_SILENT

echo [ %date% %time% ] Launching 5 Visible AI Workers in minimized windows...
for /L %%i in (1,1,5) do (
    start /min "AI Worker %%i" cmd /c ""%PHP_BIN%" src\Workers\AIWorker.php %%i >> logs\ai_worker_%%i.log 2>&1"
    powershell -NoProfile -Command "Start-Sleep -Milliseconds 500"
)
echo [ %date% %time% ] 5 Visible Workers launched.
if "%~1"=="" (
    echo.
    pause
    goto :MENU
)
exit /b 0

:RESTART_GRACEFUL
echo.
echo [ %date% %time% ] Requesting graceful exit of running workers...
for /f %%t in ('powershell -NoProfile -Command "(Get-Date).ToString('o')"') do set "REFRESH_START=%%t"

"%PHP_BIN%" scripts\request_worker_restart.php
if errorlevel 1 echo [ %date% %time% ] WARNING: restart signal NOT sent - old workers will only stop at the force-clean step below.
powershell -NoProfile -Command "Start-Sleep -Seconds 2"

echo [ %date% %time% ] Launching 5 fresh silent workers...
set "VBS_FILE=%temp%\run_silent_worker.vbs"
echo Set WshShell = CreateObject("WScript.Shell") > "%VBS_FILE%"
for /L %%i in (1,1,5) do (
    echo WshShell.Run "cmd /c ""%PHP_BIN%"" src\Workers\AIWorker.php %%i >> logs\ai_worker_%%i.log 2>&1", 0 >> "%VBS_FILE%"
    echo WScript.Sleep 1500 >> "%VBS_FILE%"
)
echo Set WshShell = Nothing >> "%VBS_FILE%"
wscript.exe "%VBS_FILE%"
del "%VBS_FILE%"

:: A fixed 30s wait killed any job still running (one aptitude set takes ~2 min,
:: worst case ~272s = 3 retries x 90s cURL timeout). Instead, wait until every old
:: worker has exited on its own, up to 330s, then force-clean whatever is left.
echo [ %date% %time% ] Waiting for old workers to finish active jobs (max 330s)...
powershell -NoProfile -Command "$cutoff = [datetime]::Parse('%REFRESH_START%'); $deadline = (Get-Date).AddSeconds(330); do { $old = @(Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" | Where-Object { $_.CommandLine -like '*AIWorker.php*' -and $_.CreationDate -lt $cutoff }); if ($old.Count -eq 0) { break }; Start-Sleep -Seconds 5 } while ((Get-Date) -lt $deadline); Write-Host ('Old workers still running after wait: ' + $old.Count)"

echo [ %date% %time% ] Force-cleaning lingering old workers...
powershell -NoProfile -Command "$cutoff = [datetime]::Parse('%REFRESH_START%'); Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" | Where-Object { $_.CommandLine -like '*AIWorker.php*' -and $_.CreationDate -lt $cutoff } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"

echo [ %date% %time% ] 5 Workers successfully refreshed!
if "%~1"=="" (
    echo.
    pause
    goto :MENU
)
exit /b 0

:STOP_WORKERS
echo.
echo [ %date% %time% ] Stopping all AI Worker processes...
call :INTERNAL_STOP_SILENT
echo [ %date% %time% ] All AI Workers stopped.
if "%~1"=="" (
    echo.
    pause
    goto :MENU
)
exit /b 0

:STATUS_WORKERS
echo.
echo ======================================================================
echo                     CURRENT RUNNING AI WORKERS
echo ======================================================================
powershell -NoProfile -Command "$w = Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" | Where-Object { $_.CommandLine -like '*AIWorker.php*' }; if ($w) { $w | Select-Object ProcessId, @{N='Worker';E={ if ($_.CommandLine -match 'AIWorker\.php\s+(\d+)') { 'Instance #' + $matches[1] } else { 'Instance' } }}, @{N='Started';E={$_.CreationDate}} | Format-Table -AutoSize; Write-Host ('Total Active Workers: ' + $w.Count) -ForegroundColor Green } else { Write-Host 'No AI Workers currently running.' -ForegroundColor Yellow }"
echo ======================================================================
if "%~1"=="" (
    echo.
    pause
    goto :MENU
)
exit /b 0

:OPEN_MONITOR
echo Opening AI Monitor in default browser...
start http://localhost/Lakshya/public/admin/ai_monitor.php
if "%~1"=="" goto :MENU
exit /b 0

:INTERNAL_STOP_SILENT
powershell -NoProfile -Command "$workers = Get-CimInstance Win32_Process -Filter \"Name='php.exe' AND CommandLine LIKE '%%AIWorker.php%%'\"; if ($workers) { $workers | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue } }" >nul 2>&1
taskkill /F /FI "WINDOWTITLE eq AI Worker *" /T >nul 2>&1
exit /b 0
