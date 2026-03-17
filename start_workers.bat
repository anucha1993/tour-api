@echo off
REM Laravel Queue Workers startup script for Plesk Windows
REM ตรงกับ start_workers_linux.sh 100%%
REM
REM วิธีตั้งค่าบน Plesk Windows:
REM   1. Plesk > Scheduled Tasks > Add Task
REM   2. Task Type: Run a command
REM   3. Command: C:\inetpub\vhosts\nexttrip.asia\api.nexttrip.asia\start_workers.bat
REM   4. Run: Cron style  0 * * * *  (ทุก 1 ชั่วโมง)

set "APP_DIR=C:\inetpub\vhosts\nexttrip.asia\api.nexttrip.asia"
set "PHP_BIN=C:\Program Files (x86)\Plesk\Additional\PleskPHP82\php.exe"
set "LOG_FILE=%APP_DIR%\storage\logs\worker.log"

REM Worker 1: default + periods (sync jobs)
REM --max-jobs=100 --max-time=3600: รันสูงสุด 100 jobs หรือ 1 ชม.
start "queue-worker-default" /B "%PHP_BIN%" -d memory_limit=256M "%APP_DIR%\artisan" queue:work database --queue=default,periods --tries=1 --timeout=600 --sleep=3 --max-jobs=100 --max-time=3600
echo [%date% %time%] Started Worker 1 (default+periods) >> "%LOG_FILE%"

REM Worker 2: media (media upload jobs)
REM --max-jobs=50 --max-time=3600: รันสูงสุด 50 jobs หรือ 1 ชม.
start "queue-worker-media" /B "%PHP_BIN%" -d memory_limit=256M "%APP_DIR%\artisan" queue:work database --queue=media --tries=2 --timeout=120 --sleep=5 --max-jobs=50 --max-time=3600
echo [%date% %time%] Started Worker 2 (media) >> "%LOG_FILE%"
