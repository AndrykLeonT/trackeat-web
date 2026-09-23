@echo off
title TrackEat - Servidores de Desarrollo
echo ==============================================
echo   Iniciando TrackEat (Backend + Frontend)
echo ==============================================

REM 1. Detectar comando o ruta de PHP
set "PHP_CMD="

REM Opcion A: PHP disponible globalmente
where php >nul 2>&1
if %errorlevel% equ 0 (
    set "PHP_CMD=php"
    goto :found_php
)

REM Opcion B: Laragon local
for /d %%D in ("C:\laragon\bin\php\php-8.3*" "C:\laragon\bin\php\php-8.2*" "C:\laragon\bin\php\php-8.4*" "C:\laragon\bin\php\php-8.1*") do (
    if exist "%%D\php.exe" (
        set "PHP_CMD=%%D\php.exe"
        goto :found_php
    )
)

REM Opcion C: XAMPP local
if exist "C:\xampp\php\php.exe" (
    set "PHP_CMD=C:\xampp\php\php.exe"
    goto :found_php
)

:found_php
if "%PHP_CMD%"=="" (
    echo [ALERTA] No se encontro PHP en el sistema ni en Laragon/XAMPP.
    echo Por favor asegurate de tener PHP instalado.
    pause
    exit /b 1
)

echo [OK] Usando PHP: %PHP_CMD%

REM 2. Levantar Servidores
start "TrackEat - Backend (Laravel)" cmd /k "cd backend && %PHP_CMD% artisan serve"
start "TrackEat - Frontend (Vite)" cmd /k "cd frontend && npm run dev"

REM 3. Abrir Navegador
timeout /t 3 /nobreak >nul 2>&1
start http://127.0.0.1:8000

echo ==============================================
echo   Servidores iniciados en http://127.0.0.1:8000
echo ==============================================
