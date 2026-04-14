@echo off
title AI Background Worker (Auto-Restart Monitor)
:loop
echo [ %date% %time% ] Starting AI Worker...
php src\Workers\AIWorker.php
echo [ %date% %time% ] Worker exited gracefully. Restarting in 5 seconds to clear memory...
timeout /t 5 /nobreak >nul
goto loop
