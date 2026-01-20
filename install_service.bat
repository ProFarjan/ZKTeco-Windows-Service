@echo off
REM ZKTeco Service Installation Script
REM Run this as Administrator

SET SERVICE_NAME=ZkTecoSync
SET NSSM_PATH=%~dp0nssm.exe
SET PHP_PATH=%~dp0php\php.exe
SET SCRIPT_PATH=%~dp0service_worker.php
SET WORK_DIR=%~dp0
SET CONFIG_FILE=%~dp0config.json

echo ======================================
echo ZKTeco Service Installation
echo ======================================
echo.
echo Installation Directory: %WORK_DIR%
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Must run as Administrator!
    echo Right-click -^> Run as Administrator
    pause
    exit /b 1
)

echo [1/6] Checking prerequisites...
echo.

if not exist "%NSSM_PATH%" (
    echo [ERROR] NSSM not found: %NSSM_PATH%
    echo Download from: https://nssm.cc/download
    pause
    exit /b 1
)
echo [OK] NSSM found

if not exist "%PHP_PATH%" (
    echo [ERROR] PHP not found: %PHP_PATH%
    echo Run: download_php.bat
    pause
    exit /b 1
)
echo [OK] PHP found

echo.
echo [2/6] Verifying PHP...
"%PHP_PATH%" -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP not working!
    echo Run: fix_php_extensions.bat
    pause
    exit /b 1
)
"%PHP_PATH%" -v | findstr "PHP"

echo.
echo [3/6] Checking extensions...
"%PHP_PATH%" -r "exit(extension_loaded('sockets')?0:1);" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Sockets extension missing!
    echo Run: fix_php_extensions.bat
    pause
    exit /b 1
)
echo [OK] Extensions loaded

if not exist "%CONFIG_FILE%" (
    echo [ERROR] config.json not found
    pause
    exit /b 1
)
echo [OK] Configuration found

if not exist "%SCRIPT_PATH%" (
    echo [ERROR] service_worker.php not found
    pause
    exit /b 1
)
echo [OK] Service worker found

echo.
echo [4/6] Checking existing service...

sc query %SERVICE_NAME% >nul 2>&1
if %errorlevel% equ 0 (
    echo [WARNING] Service exists!
    echo 1. Reinstall
    echo 2. Cancel
    set /p REINSTALL="Choice (1/2): "
    if "%REINSTALL%"=="1" (
        "%NSSM_PATH%" stop %SERVICE_NAME% >nul 2>&1
        timeout /t 2 >nul
        "%NSSM_PATH%" remove %SERVICE_NAME% confirm >nul 2>&1
        timeout /t 1 >nul
        echo [OK] Removed
    ) else (
        echo Cancelled
        pause
        exit /b 0
    )
) else (
    echo [OK] No existing service
)

if not exist "%WORK_DIR%logs" mkdir "%WORK_DIR%logs"

echo.
echo [5/6] Installing service...
"%NSSM_PATH%" install %SERVICE_NAME% "%PHP_PATH%" "%SCRIPT_PATH%" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Install failed!
    pause
    exit /b 1
)
echo [OK] Service installed

echo.
echo [6/6] Configuring...
"%NSSM_PATH%" set %SERVICE_NAME% AppDirectory %WORK_DIR% >nul
"%NSSM_PATH%" set %SERVICE_NAME% DisplayName "ZKTeco Attendance Sync Service" >nul
"%NSSM_PATH%" set %SERVICE_NAME% Description "Syncs attendance from ZKTeco devices to API every 5 minutes" >nul
"%NSSM_PATH%" set %SERVICE_NAME% Start SERVICE_AUTO_START >nul
"%NSSM_PATH%" set %SERVICE_NAME% AppExit Default Restart >nul
"%NSSM_PATH%" set %SERVICE_NAME% AppRestartDelay 5000 >nul
"%NSSM_PATH%" set %SERVICE_NAME% AppStdout %WORK_DIR%logs\service_stdout.log >nul
"%NSSM_PATH%" set %SERVICE_NAME% AppStderr %WORK_DIR%logs\service_stderr.log >nul
echo [OK] Configured

echo.
echo ======================================
echo Installation Complete!
echo ======================================
echo.
echo Service: %SERVICE_NAME%
echo Status: Installed (not started)
echo Startup: Automatic
echo.

"%PHP_PATH%" -r "$c=json_decode(file_get_contents('%CONFIG_FILE%'),true);echo 'Sync: '.$c['sync_interval'].'s ('.($c['sync_interval']/60).'min)'.PHP_EOL;echo 'API: '.($c['api']['enabled']?'Enabled':'Disabled').PHP_EOL;echo 'Devices: '.count($c['devices']).PHP_EOL;"

echo.
echo Start service now? (Y/N)
set /p START=

if /i "%START%"=="Y" (
    "%NSSM_PATH%" start %SERVICE_NAME%
    if %errorlevel% equ 0 (
        echo [OK] Service started!
        timeout /t 3 >nul
        sc query %SERVICE_NAME% | findstr "STATE"
        echo.
        echo Monitor: powershell Get-Content logs\service.log -Wait -Tail 20
    ) else (
        echo [ERROR] Start failed! Check logs\service_stderr.log
    )
) else (
    echo.
    echo Start manually: net start %SERVICE_NAME%
)

echo.
echo ======================================
pause
