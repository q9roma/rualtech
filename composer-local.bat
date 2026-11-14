@echo off
setlocal
set "PATH=%~dp0.tools\php84;%PATH%"
call composer %*
