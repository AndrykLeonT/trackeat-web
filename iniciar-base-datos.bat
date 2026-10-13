@echo off
setlocal
title TrackEat - Base de datos
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\trackeat.ps1" -Mode Base %*
set "RESULT=%ERRORLEVEL%"
pause
exit /b %RESULT%
