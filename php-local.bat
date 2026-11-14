@echo off
if not exist "%~dp0.tools\php84\php.exe" (
    echo Run powershell -ExecutionPolicy Bypass -File scripts\setup-php.ps1 first.
    exit /b 1
)
"%~dp0.tools\php84\php.exe" %*
