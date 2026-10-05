@echo off
cd /d "%~dp0"
call "scripts\manage_workers.bat" %*
