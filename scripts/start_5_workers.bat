@echo off
title Lakshya AI Multi-Worker Manager
cd /d "%~dp0.."

echo [ %date% %time% ] Launching 5 Parallel AI Workers...

:: Launch 5 workers in separate minimized windows
start /min "AI Worker 1" cmd /c "php src\Workers\AIWorker.php >> logs\ai_worker_1.log 2>&1"
start /min "AI Worker 2" cmd /c "php src\Workers\AIWorker.php >> logs\ai_worker_2.log 2>&1"
start /min "AI Worker 3" cmd /c "php src\Workers\AIWorker.php >> logs\ai_worker_3.log 2>&1"
start /min "AI Worker 4" cmd /c "php src\Workers\AIWorker.php >> logs\ai_worker_4.log 2>&1"
start /min "AI Worker 5" cmd /c "php src\Workers\AIWorker.php >> logs\ai_worker_5.log 2>&1"

echo [ %date% %time% ] 5 Workers launched in background.
echo Check public/admin/ai_monitor.php to see them live.
pause
