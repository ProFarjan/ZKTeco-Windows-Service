@echo off
REM ZKTeco Service Installation Script using NSSM
REM Run this as Administrator

SET SERVICE_NAME=ZkTecoSync
REM Use standalone PHP (no WAMP dependency)
SET PHP_PATH=%~dp0php\php.exe
REM Fallback to WAMP PHP if standalone not found
if not exist "%PHP_PATH%" SET PHP_PATH=C:\wamp64\bin\php\php7.4.33\php.exe
SET SCRIPT_PATH=%~dp0service_worker.php
SET WORK_DIR=%~dp0

echo ======================================
echo ZKTeco Service Installation
echo ======================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: This script must be run as Administrator!
    echo Right-click and select "Run as Administrator"
    pause
    exit /b 1
)

REM Check if NSSM exists
where nssm >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: NSSM not found in PATH!
    echo.
    echo Please download NSSM from: https://nssm.cc/download
    echo Extract nssm.exe to C:\Windows\System32 or add to PATH
    pause
    exit /b 1
)

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please update PHP_PATH in this script
    pause
    exit /b 1
)

REM Check if service already exists
sc query %SERVICE_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo Service %SERVICE_NAME% already exists!
    echo Stopping and removing existing service...
    nssm stop %SERVICE_NAME%
    timeout /t 3 >nul
    nssm remove %SERVICE_NAME% confirm
    timeout /t 2 >nul
)

REM Install service
echo Installing %SERVICE_NAME% service...
nssm install %SERVICE_NAME% "%PHP_PATH%" "%SCRIPT_PATH%"

REM Configure service
echo Configuring service...
nssm set %SERVICE_NAME% AppDirectory "%WORK_DIR%"
nssm set %SERVICE_NAME% DisplayName "ZKTeco Attendance Sync Service"
nssm set %SERVICE_NAME% Description "Synchronizes attendance data from ZKTeco biometric devices to database"
nssm set %SERVICE_NAME% Start SERVICE_AUTO_START
nssm set %SERVICE_NAME% AppStopMethodSkip 0
nssm set %SERVICE_NAME% AppStopMethodConsole 1500
nssm set %SERVICE_NAME% AppKillConsole 1500
nssm set %SERVICE_NAME% AppExit Default Restart
nssm set %SERVICE_NAME% AppRestartDelay 5000

REM Set output logging
nssm set %SERVICE_NAME% AppStdout "%WORK_DIR%logs\service_stdout.log"
nssm set %SERVICE_NAME% AppStderr "%WORK_DIR%logs\service_stderr.log"

echo.
echo ======================================
echo Service installed successfully!
echo ======================================
echo.
echo Service Name: %SERVICE_NAME%
echo Status: Installed (not started)
echo.
echo To start the service now, run:
echo   nssm start %SERVICE_NAME%
echo   OR
echo   net start %SERVICE_NAME%
echo.
echo To check service status:
echo   sc query %SERVICE_NAME%
echo.
echo To view logs:
echo   type logs\service.log
echo.
echo Would you like to start the service now? (Y/N)
set /p START_NOW=

if /i "%START_NOW%"=="Y" (
    echo Starting service...
    nssm start %SERVICE_NAME%
    timeout /t 2 >nul
    sc query %SERVICE_NAME%
)

echo.
echo Installation complete!
pause
