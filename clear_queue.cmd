@echo off
chcp 65001 >nul
setlocal

set PHP="C:\Program Files (x86)\Plesk\Additional\PleskPHP82\php.exe"
set API_DIR=C:\inetpub\vhosts\nexttrip.world\api.nexttrip.world

:: Change to API directory
cd /d %API_DIR%
if errorlevel 1 (
    echo [ERROR] Cannot cd to %API_DIR%
    pause
    goto :EOF
)

echo ============================================
echo   Queue Management - NextTrip API
echo ============================================
echo.

:: Show current queue status using artisan
echo [INFO] Current queue status:
echo.
%PHP% artisan queue:monitor database:default,periods,media
echo.

echo ============================================
echo   Select option:
echo ============================================
echo   1. Clear ALL queues (default + periods + media)
echo   2. Clear media queue only
echo   3. Clear failed jobs only
echo   4. Clear ALL queues + failed jobs
echo   5. Cancel
echo ============================================
echo.

set /p CHOICE="Enter choice (1-5): "

if "%CHOICE%"=="1" goto CLEAR_ALL
if "%CHOICE%"=="2" goto CLEAR_MEDIA
if "%CHOICE%"=="3" goto CLEAR_FAILED
if "%CHOICE%"=="4" goto CLEAR_EVERYTHING
if "%CHOICE%"=="5" goto CANCEL
echo Invalid choice.
pause
goto :EOF

:CLEAR_ALL
echo.
echo [CLEARING] default queue...
%PHP% artisan queue:clear database --queue=default --force
echo [CLEARING] periods queue...
%PHP% artisan queue:clear database --queue=periods --force
echo [CLEARING] media queue...
%PHP% artisan queue:clear database --queue=media --force
goto DONE

:CLEAR_MEDIA
echo.
echo [CLEARING] media queue...
%PHP% artisan queue:clear database --queue=media --force
goto DONE

:CLEAR_FAILED
echo.
echo [CLEARING] failed jobs...
%PHP% artisan queue:flush
goto DONE

:CLEAR_EVERYTHING
echo.
echo [CLEARING] default queue...
%PHP% artisan queue:clear database --queue=default --force
echo [CLEARING] periods queue...
%PHP% artisan queue:clear database --queue=periods --force
echo [CLEARING] media queue...
%PHP% artisan queue:clear database --queue=media --force
echo [CLEARING] failed jobs...
%PHP% artisan queue:flush
goto DONE

:DONE
echo.
echo ============================================
echo [DONE] Queue status after clearing:
echo.
%PHP% artisan queue:monitor database:default,periods,media
echo ============================================
pause
goto :EOF

:CANCEL
echo Cancelled.
pause
goto :EOF
