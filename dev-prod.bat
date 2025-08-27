@echo off
echo Switching to PRODUCTION environment...
copy .env.production .env
echo Production environment activated!
echo.
echo Clearing caches...
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan cache:clear
echo Production environment ready!
