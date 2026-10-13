@echo off
setlocal
title TrackEat - Proyecto
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\trackeat.ps1" -Mode Site %*
set "RESULT=%ERRORLEVEL%"
if not "%RESULT%"=="0" pause
exit /b %RESULT%
