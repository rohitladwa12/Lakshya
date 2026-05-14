@echo off
title AI Background Worker (Auto-Restart Monitor)
:: Move to the project root (one level up from /scripts)
cd /d "%~dp0.."

:loop
echo [ %date% %time% ] Starting AI Worker...
:: Use relative paths for PHP and Logs
php src\Workers\AIWorker.php >> logs\ai_worker.log 2>&1
echo [ %date% %time% ] Worker exited. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
