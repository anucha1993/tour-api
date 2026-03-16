#!/bin/bash
# ===========================================
# Laravel Queue Workers - Plesk Linux
# ===========================================
# Cron in Plesk: every 1 hour (0 * * * *)
#
# Setup:
#   1. Plesk > Scheduled Tasks (Cron Jobs)
#   2. Command: bash /var/www/vhosts/nexttrip.asia/api.nexttrip.asia/start_workers_linux.sh
#   3. Run as: domain owner
#   4. Schedule: 0 * * * *
# ===========================================

# ===== Config =====
APP_DIR="/var/www/vhosts/nexttrip.asia/api.nexttrip.asia"
PHP_BIN="/opt/plesk/php/8.4/bin/php"
LOG_DIR="/var/www/vhosts/nexttrip.asia/logs"
LOG_FILE="${LOG_DIR}/worker.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S %z')

# Create log directory
mkdir -p "${LOG_DIR}"

# ===== Worker 1: default + periods (sync jobs) =====
WORKER1_COUNT=$(pgrep -f 'queue:work.*--queue=default,periods' | wc -l)
if [ "$WORKER1_COUNT" -lt 1 ]; then
    cd "${APP_DIR}" || exit 1
    nohup "${PHP_BIN}" -d memory_limit=256M artisan queue:work database \
        --queue=default,periods \
        --tries=1 \
        --timeout=600 \
        --sleep=3 \
        --max-jobs=100 \
        --max-time=3600 \
        >> "${LOG_DIR}/worker-default.log" 2>&1 &
    echo "[${TIMESTAMP}] Started Worker 1 (default+periods) PID=$!" >> "${LOG_FILE}"
fi

# ===== Worker 2: media (media upload jobs) =====
WORKER2_COUNT=$(pgrep -f 'queue:work.*--queue=media' | wc -l)
if [ "$WORKER2_COUNT" -lt 1 ]; then
    cd "${APP_DIR}" || exit 1
    nohup "${PHP_BIN}" -d memory_limit=256M artisan queue:work database \
        --queue=media \
        --tries=2 \
        --timeout=120 \
        --sleep=5 \
        --max-jobs=50 \
        --max-time=3600 \
        >> "${LOG_DIR}/worker-media.log" 2>&1 &
    echo "[${TIMESTAMP}] Started Worker 2 (media) PID=$!" >> "${LOG_FILE}"
fi

# ===== Status =====
echo "[${TIMESTAMP}] Worker check complete. default=$(pgrep -f 'queue:work.*--queue=default,periods' | wc -l) media=$(pgrep -f 'queue:work.*--queue=media' | wc -l)" >> "${LOG_FILE}"
