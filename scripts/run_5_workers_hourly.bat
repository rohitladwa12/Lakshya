@echo off
cd /d "%~dp0.."

echo [ %date% %time% ] Stopping existing AI workers...
powershell -Command "Get-Process php -ErrorAction SilentlyContinue | Where-Object { (Get-CimInstance Win32_Process -Filter \"ProcessId = $($_.Id)\").CommandLine -like '*AIWorker.php*' } | Stop-Process -Force"

echo [ %date% %time% ] Launching 5 silent workers...
set "VBS_FILE=%temp%\run_silent_worker.vbs"
echo Set WshShell = CreateObject("WScript.Shell") > "%VBS_FILE%"

:: Launch 5 workers with separate log files and a 1-second startup stagger
for /L %%i in (1,1,5) do (
    echo WshShell.Run "cmd /c php src\Workers\AIWorker.php >> logs\ai_worker_%%i.log 2>&1", 0 >> "%VBS_FILE%"
    echo WScript.Sleep 1000 >> "%VBS_FILE%"
)
echo Set WshShell = Nothing >> "%VBS_FILE%"

wscript.exe "%VBS_FILE%"
del "%VBS_FILE%"
echo [ %date% %time% ] 5 Silent workers have been refreshed.
