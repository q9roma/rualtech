@echo off
echo Switching to LOCAL development environment...
copy .env.local .env
echo Local environment activated!
echo.
echo Starting Laravel development server...
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan cache:clear
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
