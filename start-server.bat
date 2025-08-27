@echo off
title Laravel Server - Altech
echo ====================================
echo    Starting Laravel Server (Altech)
echo ====================================
echo.
cd /d "c:\xampp\htdocs\altech"

echo Clearing caches...
c:\xampp\php\php.exe artisan config:clear
c:\xampp\php\php.exe artisan cache:clear
c:\xampp\php\php.exe artisan view:clear

echo.
echo Starting server on http://127.0.0.1:8001
echo Press Ctrl+C to stop the server
echo.

c:\xampp\php\php.exe -d max_execution_time=0 -d memory_limit=2G -d max_input_vars=3000 artisan serve --host=127.0.0.1 --port=8001

echo.
echo Server stopped.
pause
