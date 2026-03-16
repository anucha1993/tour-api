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
SERVER_PATH="/var/www/vhosts/nexttrip.asia/api.nexttrip.asia"
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
    ./ \
    ${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}/

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to upload source code${NC}"
    exit 1
fi
echo -e "${GREEN}Upload complete!${NC}"

# ===== Step 2: Install deps & optimize on server =====
echo -e "${YELLOW}Step 2: Installing & optimizing on server...${NC}"

ssh -p ${SSH_PORT} ${SERVER_USER}@${SERVER_HOST} << 'ENDSSH'
    cd /var/www/vhosts/nexttrip.asia/api.nexttrip.asia

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
