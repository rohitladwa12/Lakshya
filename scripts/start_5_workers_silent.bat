@echo off
title Lakshya AI Background Manager
cd /d "%~dp0.."

echo [ %date% %time% ] Launching 5 SILENT AI Workers...

:: Create a temporary VBScript to run processes hidden
set "VBS_FILE=%temp%\run_silent_worker.vbs"

echo Set WshShell = CreateObject("WScript.Shell") > "%VBS_FILE%"
echo command = "php src\Workers\AIWorker.php" >> "%VBS_FILE%"

:: Kill any existing workers first
taskkill /f /im php.exe /fi "WINDOWTITLE eq Lakshya AI Worker*" >nul 2>&1

:: Launch 5 workers with separate log files to prevent Windows write locks, allowing all 5 to run in parallel
for /L %%i in (1,1,5) do (
    echo WshShell.Run "cmd /c php src\Workers\AIWorker.php >> logs\ai_worker_%%i.log 2>&1", 0 >> "%VBS_FILE%"
)
echo Set WshShell = Nothing >> "%VBS_FILE%"

:: Execute the VBScript
wscript.exe "%VBS_FILE%"

:: Cleanup
del "%VBS_FILE%"

echo [ %date% %time% ] 5 Workers are now running in the BACKGROUND.
echo No windows will be visible. Monitor them via:
echo -- http://localhost/Lakshya/public/admin/ai_monitor.php
pause
