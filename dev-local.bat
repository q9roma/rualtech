@echo off
cd /d "%~dp0"
if not exist .env.local (
    echo Missing .env.local. Follow docs/local-development.md to create local settings.
    exit /b 1
)
call "%~dp0php-local.bat" scripts\check-local-env.php
if errorlevel 1 exit /b 1
echo Switching to LOCAL development environment...
copy /y .env.local .env >nul
if errorlevel 1 exit /b 1
echo Local environment activated!
echo.
echo Starting Laravel development server...
call "%~dp0php-local.bat" artisan config:clear
if errorlevel 1 exit /b 1
echo Open http://127.0.0.1:8000/catalog after the server starts.
echo MySQL must be running in XAMPP for database-backed pages.
call "%~dp0php-local.bat" artisan serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload
