#!/bin/bash
# ===========================================
# Deploy Script: tour-api (Laravel) → Plesk Linux Server
# ===========================================
# วิธีใช้:
#   1. แก้ค่า SERVER_USER, SERVER_HOST, SERVER_PATH ด้านล่าง
#   2. chmod +x deploy.sh
#   3. ./deploy.sh
# ===========================================

# ===== ตั้งค่า Server =====
SERVER_USER="root"
SERVER_HOST="147.50.254.113"
SERVER_PATH="/var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com"
SSH_PORT="22"

# ===== สี =====
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}Starting tour-api deployment...${NC}"

# ===== Step 1: Upload source code =====
echo -e "${YELLOW}Step 1: Uploading source code...${NC}"

rsync -avz --delete \
    -e "ssh -p ${SSH_PORT}" \
    --exclude='.env' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='.git/' \
    --exclude='dev-scripts/' \
    --exclude='tests/' \
    ./ \
    ${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}/

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to upload source code${NC}"
    exit 1
fi
echo -e "${GREEN}Upload complete!${NC}"

# ===== Step 1b: Purge any legacy debug/test scripts left in the server root =====
# Older deploys uploaded loose check_*/debug_*/fix_*/test_* etc. scripts that
# connect straight to the production DB. Remove them so they can't be executed.
echo -e "${YELLOW}Step 1b: Removing legacy debug scripts on server...${NC}"
ssh -p ${SSH_PORT} ${SERVER_USER}@${SERVER_HOST} \
    "cd ${SERVER_PATH} && rm -f _*.php check_*.php cleanup_*.php debug_*.php delete_*.php diag_*.php diagnose_*.php fix_*.php migrate_*.php patch_*.php remove_*.php reset_*.php sync_*.php test_*.php verify_*.php 2>/dev/null; echo '  cleaned legacy root scripts'"

# ===== Step 2: Install deps & optimize on server =====
echo -e "${YELLOW}Step 2: Installing & optimizing on server...${NC}"

ssh -p ${SSH_PORT} ${SERVER_USER}@${SERVER_HOST} << 'ENDSSH'
    cd /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com

    # Install composer dependencies
    /opt/plesk/php/8.4/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

    # Run migrations
    /opt/plesk/php/8.4/bin/php artisan migrate --force

    # Clear & rebuild caches
    /opt/plesk/php/8.4/bin/php artisan config:cache
    /opt/plesk/php/8.4/bin/php artisan route:cache
    /opt/plesk/php/8.4/bin/php artisan view:cache

    # Restart queue workers (gracefully)
    /opt/plesk/php/8.4/bin/php artisan queue:restart

    echo "tour-api deployed successfully"
ENDSSH

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to run server commands${NC}"
    exit 1
fi

echo -e "${GREEN}Deployment complete!${NC}"
