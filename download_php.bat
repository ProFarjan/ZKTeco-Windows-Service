@echo off
REM Download Standalone PHP (No WAMP dependency)
REM Run this as Administrator

echo ======================================
echo Downloading Standalone PHP
echo ======================================
echo.

SET PHP_VERSION=8.2.15
SET PHP_DIR=%~dp0php
SET PHP_ZIP=%~dp0php.zip
SET DOWNLOAD_URL=https://windows.php.net/downloads/releases/php-%PHP_VERSION%-Win32-vs16-x64.zip

echo This script will download PHP %PHP_VERSION% (Standalone)
echo Installation directory: %PHP_DIR%
echo.
echo Press any key to continue or Ctrl+C to cancel...
pause >nul

REM Check if PHP directory already exists
if exist "%PHP_DIR%" (
    echo PHP directory already exists at: %PHP_DIR%
    echo Please delete or rename it first.
    pause
    exit /b 1
)

echo.
echo Downloading PHP %PHP_VERSION%...
echo This may take a few minutes depending on your internet connection.
echo.

REM Download using PowerShell (built into Windows)
powershell -Command "& {[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri '%DOWNLOAD_URL%' -OutFile '%PHP_ZIP%'}"

if not exist "%PHP_ZIP%" (
    echo ERROR: Failed to download PHP
    echo Please check your internet connection and try again
    echo Or download manually from: https://windows.php.net/download/
    pause
    exit /b 1
)

echo Download complete!
echo.
echo Extracting PHP...

REM Extract using PowerShell
powershell -Command "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath '%PHP_DIR%' -Force"

if not exist "%PHP_DIR%\php.exe" (
    echo ERROR: Failed to extract PHP
    pause
    exit /b 1
)

echo Extraction complete!
echo.

REM Configure PHP
echo Configuring PHP...
copy "%PHP_DIR%\php.ini-production" "%PHP_DIR%\php.ini" >nul

REM Enable required extensions
powershell -Command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=curl', 'extension=curl' | Set-Content '%PHP_DIR%\php.ini'"
powershell -Command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=mbstring', 'extension=mbstring' | Set-Content '%PHP_DIR%\php.ini'"
powershell -Command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=openssl', 'extension=openssl' | Set-Content '%PHP_DIR%\php.ini'"
powershell -Command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=pdo_mysql', 'extension=pdo_mysql' | Set-Content '%PHP_DIR%\php.ini'"
powershell -Command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=mysqli', 'extension=mysqli' | Set-Content '%PHP_DIR%\php.ini'"
powershell -Command "(Get-Content '%PHP_DIR%\php.ini') -replace ';extension=sockets', 'extension=sockets' | Set-Content '%PHP_DIR%\php.ini'"

REM Clean up
echo Cleaning up...
del "%PHP_ZIP%" >nul

echo.
echo ======================================
echo Installation Complete!
echo ======================================
echo.
echo PHP %PHP_VERSION% installed to: %PHP_DIR%
echo PHP executable: %PHP_DIR%\php.exe
echo.
echo Testing PHP installation...
"%PHP_DIR%\php.exe" -v
echo.
echo Next steps:
echo 1. Update install_service.bat to use: %PHP_DIR%\php.exe
echo 2. Test the service: %PHP_DIR%\php.exe test_connection.php
echo 3. Install service: install_service.bat (as Administrator)
echo.
pause
