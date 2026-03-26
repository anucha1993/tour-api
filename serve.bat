@echo off
REM ────────────────────────────────────────────────────────────────────────────
REM  serve.bat  —  Development server with 5 concurrent PHP workers
REM  ใช้แทน  "php artisan serve"  เพื่อรองรับ concurrent requests
REM  (กัน ECONNRESET เวลา fetchSample / long-running sync ทำงานพร้อม polling)
REM
REM  วิธีใช้:
REM    cd tour-api
REM    serve.bat
REM ────────────────────────────────────────────────────────────────────────────

set "PHP_CLI_SERVER_WORKERS=5"
php artisan serve --host=127.0.0.1 --port=8000
