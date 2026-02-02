# Queue Worker Setup Guide

## Overview

ระบบ Tour Manager ใช้ Laravel Queue สำหรับ background jobs:
- **SyncToursJob** - Sync ทัวร์จาก Wholesaler API
- **SyncPeriodsJob** - Sync รอบเดินทางแบบ Two-Phase

## Development Environment

### วิธีรัน Queue Worker (Development)

เปิด terminal แยกและรันคำสั่ง:

```powershell
cd D:\Programing\tour-manager\tour-api
php artisan queue:listen database --queue=default,periods
```

**หมายเหตุ:**
- `queue:listen` = reload โค้ดใหม่ทุกครั้ง (ดีสำหรับ dev)
- `queue:work` = เร็วกว่า แต่ต้อง restart เมื่อแก้โค้ด

---

## Production Environment (Plesk)

### ขั้นตอนที่ 1: ตั้งค่า .env

```env
QUEUE_CONNECTION=database
```

### ขั้นตอนที่ 2: สร้าง Scheduled Task ใน Plesk

1. ไปที่ **Plesk > Scheduled Tasks**
2. คลิก **Add Task**
3. ตั้งค่าดังนี้:

#### Task 1: Queue Worker (หลัก)

| Field | Value |
|-------|-------|
| Task Type | Run a PHP script |
| PHP Version | 8.2 (หรือเวอร์ชันที่ใช้) |
| Script Path | `/var/www/vhosts/yourdomain.com/httpdocs/tour-api/artisan` |
| Arguments | `queue:work database --queue=default,periods --stop-when-empty --max-time=300` |
| Run | Every 1 minute |

**หรือใช้ Command:**
```
cd /var/www/vhosts/yourdomain.com/httpdocs/tour-api && /usr/bin/php artisan queue:work database --queue=default,periods --stop-when-empty --max-time=300
```

#### Task 2: Laravel Scheduler (สำหรับ scheduled sync)

| Field | Value |
|-------|-------|
| Task Type | Run a PHP script |
| Script Path | `/var/www/vhosts/yourdomain.com/httpdocs/tour-api/artisan` |
| Arguments | `schedule:run` |
| Run | Every 1 minute |

---

### ขั้นตอนที่ 3: ใช้ Supervisor (แนะนำสำหรับ Production)

ถ้า Plesk รองรับ Supervisor หรือมี SSH access:

1. สร้างไฟล์ config: `/etc/supervisor/conf.d/tour-queue.conf`

```ini
[program:tour-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vhosts/yourdomain.com/httpdocs/tour-api/artisan queue:work database --queue=default,periods --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/vhosts/yourdomain.com/logs/queue-worker.log
stopwaitsecs=3600
```

2. รันคำสั่ง:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tour-queue-worker:*
```

---

## Queue Parameters อธิบาย

| Parameter | คำอธิบาย |
|-----------|---------|
| `--queue=default,periods` | ประมวลผล queue ชื่อ default และ periods |
| `--stop-when-empty` | หยุดเมื่อไม่มี job (ใช้กับ cron) |
| `--max-time=300` | หยุดหลัง 5 นาที (ป้องกัน memory leak) |
| `--sleep=3` | รอ 3 วินาทีก่อนเช็ค job ใหม่ |
| `--tries=3` | retry 3 ครั้งถ้า fail |

---

## Monitoring & Debugging

### ดู Pending Jobs
```bash
php artisan tinker --execute="echo 'Pending: ' . DB::table('jobs')->count();"
```

### ดู Failed Jobs
```bash
php artisan queue:failed
```

### Retry Failed Jobs
```bash
php artisan queue:retry all
```

### Clear All Jobs
```bash
php artisan queue:clear database
```

### ดู Queue Status (API)
```
GET /api/queue/status
```

---

## Troubleshooting

### ปัญหา: Jobs ไม่ถูกประมวลผล

1. ตรวจสอบ QUEUE_CONNECTION ใน .env
```bash
grep QUEUE_CONNECTION .env
```

2. ตรวจสอบว่ามี jobs ค้าง
```bash
php artisan tinker --execute="print_r(DB::table('jobs')->count());"
```

3. Clear cache
```bash
php artisan config:clear
php artisan cache:clear
```

### ปัญหา: Error "foreach() argument must be of type array"

- Restart queue worker หลังแก้โค้ด
- ถ้าใช้ `queue:work` ต้อง restart ทุกครั้งที่แก้โค้ด
- แนะนำใช้ `queue:listen` สำหรับ development

### ปัญหา: OPcache ไม่ refresh

```bash
php artisan optimize:clear
```

หรือ restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

---

## Queue Architecture

```
User clicks "Sync" button
        ↓
IntegrationController::syncNow()
        ↓
SyncToursJob::dispatch() → Database Queue
        ↓
Queue Worker processes job
        ↓
SyncToursJob::handle()
    ├── Fetch tours from API
    ├── Transform using mappings
    ├── Save to database
    ├── Dispatch SyncPeriodsJob (Two-Phase)
    └── Fetch & save itineraries (Two-Phase)
        ↓
SyncPeriodsJob::handle() (ถ้าเป็น Two-Phase)
    ├── Fetch periods from API
    ├── Transform using mappings
    └── Save to database
```

---

## Related Files

- `.env` - QUEUE_CONNECTION setting
- `app/Jobs/SyncToursJob.php` - Main sync job
- `app/Jobs/SyncPeriodsJob.php` - Period sync job (Two-Phase)
- `config/queue.php` - Queue configuration
- `database/migrations/*_create_jobs_table.php` - Jobs table

---

## Quick Reference

### Development
```powershell
php artisan queue:listen database --queue=default,periods
```

### Production (Cron - ทุก 1 นาที)
```bash
php artisan queue:work database --queue=default,periods --stop-when-empty --max-time=300
```

### Production (Supervisor - แนะนำ)
```bash
php artisan queue:work database --queue=default,periods --sleep=3 --tries=3 --max-time=3600
```
