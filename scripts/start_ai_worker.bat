@echo off
title AI Background Worker (Auto-Restart Monitor)
cd /d C:\htdocs\Lakshya
:loop
echo [ %date% %time% ] Starting AI Worker...
php C:\htdocs\Lakshya\src\Workers\AIWorker.php >> C:\htdocs\Lakshya\logs\app.log 2>&1
echo [ %date% %time% ] Worker exited. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
