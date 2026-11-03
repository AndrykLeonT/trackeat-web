@echo off
setlocal
title TrackEat - Balanceo
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\balanceo.ps1" %*
set "RESULT=%ERRORLEVEL%"
if not "%RESULT%"=="0" pause
exit /b %RESULT%
