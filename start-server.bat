@echo off
cd /d "%~dp0"
title Altech Local Server
call dev-local.bat
if errorlevel 1 (
    echo.
    echo Startup failed. Read the error above.
    pause
)
