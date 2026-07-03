# Queue Worker Setup Guide

## Overview

ระบบ Tour Manager ใช้ Laravel Queue สำหรับ background jobs:
- **SyncToursJob** - Sync ทัวร์จาก Wholesaler API
- **SyncPeriodsJob** - Sync รอบเดินทางแบบ Two-Phase
- **ProcessTourMediaJob** - Upload รูปภาพไป Cloudflare Images

## Queues

| Queue | Jobs | ความสำคัญ |
|-------|------|----------|
| `default` | SyncToursJob, AutoCloseExpiredJob, SendNewsletterJob | หลัก |
| `periods` | SyncPeriodsJob | sync รอบเดินทาง |
| `media` | ProcessTourMediaJob | upload รูปภาพ |

## Development Environment

### วิธีรัน Queue Worker (Development)

เปิด terminal แยกและรันคำสั่ง:

```powershell
cd D:\Programing\tour-manager\tour-api
php artisan queue:listen database --queue=default,periods,media
```

**หมายเหตุ:**
- `queue:listen` = reload โค้ดใหม่ทุกครั้ง (ดีสำหรับ dev)
- `queue:work` = เร็วกว่า แต่ต้อง restart เมื่อแก้โค้ด

---

## Production Environment (Plesk Linux)

Server: **Plesk Linux + PHP 8.4**

### วิธีที่ 1: Cron Script (แนะนำ)

ใช้ `start_workers_linux.sh` ตั้ง Cron ใน Plesk:

1. ไปที่ **Plesk > Scheduled Tasks (Cron Jobs)**
2. คลิก **Add Task**

| Field | Value |
|-------|-------|
| Command | `bash /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com/start_workers_linux.sh` |
| Run as | domain owner หรือ root |
| Schedule | `0 * * * *` (ทุก 1 ชั่วโมง) |

Script จะตรวจสอบว่า worker ยังรันอยู่หรือไม่ ถ้าไม่มีจะเริ่มใหม่

### วิธีที่ 2: Plesk Scheduled Tasks (ทางเลือก)

#### Task 1: Queue Worker (default + periods)

| Field | Value |
|-------|-------|
| Task Type | Run a command |
| Command | `/opt/plesk/php/8.4/bin/php /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com/artisan queue:work database --queue=default,periods --stop-when-empty --max-time=55` |
| Run | Cron style `* * * * *` (ทุก 1 นาที) |

#### Task 2: Queue Worker (media)

| Field | Value |
|-------|-------|
| Task Type | Run a command |
| Command | `/opt/plesk/php/8.4/bin/php /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com/artisan queue:work database --queue=media --stop-when-empty --max-time=55` |
| Run | Cron style `* * * * *` (ทุก 1 นาที) |

#### Task 3: Laravel Scheduler

| Field | Value |
|-------|-------|
| Task Type | Run a command |
| Command | `/opt/plesk/php/8.4/bin/php /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com/artisan schedule:run` |
| Run | Cron style `* * * * *` (ทุก 1 นาที) |

### หมายเหตุสำคัญ

- วิธี 1: Worker รันค้าง background สูงสุด 1 ชม. (--max-time=3600) แล้ว Cron จะเริ่มใหม่
- วิธี 2: ใช้ --stop-when-empty --max-time=55 เพื่อให้จบก่อน Cron รอบถัดไป (60 วิ)
- Scheduler ต้องรันทุก 1 นาที (`* * * * *`) เสมอ
- **`.env`**: ต้องตั้ง `QUEUE_CONNECTION=database`

---

## Queue Parameters อธิบาย

| Parameter | คำอธิบาย |
|-----------|---------|
| `--queue=default,periods,media` | ประมวลผล queue ชื่อ default, periods และ media |
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

หรือ restart PHP-FPM (Plesk Linux):
```bash
sudo systemctl restart plesk-php84-fpm
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
php artisan queue:listen database --queue=default,periods,media
```

### Production (Plesk Linux - Cron ทุก 1 นาที)
```bash
/opt/plesk/php/8.4/bin/php /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com/artisan queue:work database --queue=default,periods,media --stop-when-empty --max-time=55
```

### Production (Plesk Linux - Background Worker)
```bash
bash /var/www/vhosts/nexttripholiday.com/api.nexttripholiday.com/start_workers_linux.sh
```

### Parameters

| Parameter | ความหมาย | ทำไมต้องใช้ |
|-----------|---------|------------|
| `--stop-when-empty` | หยุดเมื่อ queue ว่าง | ไม่ให้ process ค้าง (ใช้กับ Cron) |
| `--max-time=55` | หยุดหลัง 55 วินาที | ให้ทันก่อน cron รอบถัดไป (60 วิ) |
| `--max-time=3600` | หยุดหลัง 1 ชม. | ใช้กับ background worker |
| `--max-jobs=100` | หยุดหลัง 100 jobs | ป้องกัน memory leak |
| `--sleep=3` | รอ 3 วินาทีก่อนเช็ค job ใหม่ | ลด CPU usage |