@echo off
REM ZKTeco Service Uninstallation Script
REM Run this as Administrator

SET SERVICE_NAME=ZkTecoSync
SET NSSM_PATH=%~dp0nssm.exe

echo ======================================
echo ZKTeco Service Uninstallation
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
if not exist "%NSSM_PATH%" (
    echo ERROR: NSSM not found at %NSSM_PATH%
    pause
    exit /b 1
)

REM Check if service exists
sc query %SERVICE_NAME% >nul 2>&1
if %errorlevel% neq 0 (
    echo Service %SERVICE_NAME% does not exist!
    pause
    exit /b 0
)

echo Stopping service %SERVICE_NAME%...
"%NSSM_PATH%" stop %SERVICE_NAME%
timeout /t 3 >nul

echo Removing service %SERVICE_NAME%...
"%NSSM_PATH%" remove %SERVICE_NAME% confirm

echo.
echo Service uninstalled successfully!
echo.
pause
