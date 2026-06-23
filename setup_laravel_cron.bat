@echo off
title Laravel Scheduler Setup
setlocal enabledelayedexpansion

:: Test: First pause to ensure window stays open
echo [Step 0] Checking privileges...
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Not running as admin. Requesting elevation...
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    pause
    exit /b
)
echo Privileges: OK

:: Paths
set "PROJECT_DIR=F:\borzanScraper"
set "PHP_PATH=C:\laragon\bin\php\php-8.2.0-Win32-vs16-x64\php.exe"
set "BAT_FILE=%PROJECT_DIR%\run_schedule.bat"
set "TASK_NAME=LaravelScheduler"

echo.
echo Project Directory : %PROJECT_DIR%
echo PHP Executable     : %PHP_PATH%
echo.

:: Check project folder
if not exist "%PROJECT_DIR%" (
    echo ERROR: Project directory does not exist.
    pause
    exit /b 1
)
echo Project directory exists.

:: Check PHP
if not exist "%PHP_PATH%" (
    echo ERROR: PHP executable not found.
    pause
    exit /b 1
)
echo PHP executable exists.

:: Create launcher
echo.
echo Creating launcher script...
(
    echo @echo off
    echo cd /d "%PROJECT_DIR%"
    echo "%PHP_PATH%" artisan schedule:run
) > "%BAT_FILE%"

if exist "%BAT_FILE%" (
    echo Launcher created: %BAT_FILE%
) else (
    echo ERROR: Failed to create launcher.
    pause
    exit /b 1
)

:: Remove old task
schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1

:: Create new task
echo.
echo Registering scheduled task...
schtasks /create ^
    /tn "%TASK_NAME%" ^
    /sc MINUTE /mo 1 ^
    /tr "\"%BAT_FILE%\"" ^
    /ru SYSTEM ^
    /rl HIGHEST ^
    /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Task "%TASK_NAME%" is now running every minute.
) else (
    echo FAILED: Error code %errorlevel%
)
echo.
echo All done. You can close this window.
pause