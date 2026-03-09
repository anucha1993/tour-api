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

## Production Environment (Plesk Windows)

Server: **Plesk Windows + PHP 8.0**

### วิธีที่ 1: Plesk Scheduled Tasks (แนะนำ)

ใช้ Task Type: **Run a PHP script** ใน Plesk

1. ไปที่ **Plesk > Scheduled Tasks**
2. คลิก **Add Task**

#### Task 1: Queue Worker (default + periods)

| Field | Value |
|-------|-------|
| Task Type | Run a PHP script |
| Script path | `api.nexttrip.world/artisan` |
| with arguments | `queue:work database --queue=default,periods --tries=1 --timeout=600 --sleep=3 --max-jobs=100 --max-time=3600` |
| Use PHP version | 8.2.30 |
| Run | Cron style `0 * * * *` (ทุก 1 ชั่วโมง) |

#### Task 2: Queue Worker (media)

| Field | Value |
|-------|-------|
| Task Type | Run a PHP script |
| Script path | `api.nexttrip.world/artisan` |
| with arguments | `queue:work database --queue=media --tries=2 --timeout=120 --sleep=5 --max-jobs=50 --max-time=3600` |
| Use PHP version | 8.2.30 |
| Run | Cron style `0 * * * *` (ทุก 1 ชั่วโมง) |

#### Task 3: Laravel Scheduler

| Field | Value |
|-------|-------|
| Task Type | Run a PHP script |
| Script path | `api.nexttrip.world/artisan` |
| with arguments | `schedule:run` |
| Use PHP version | 8.2.30 |
| Run | Cron style `* * * * *` (ทุก 1 นาที) |

### หมายเหตุสำคัญ

- Worker 1 รันยาวสูงสุด 1 ชม. หรือ 100 jobs (เหมือน Linux script)
- Worker 2 รันยาวสูงสุด 1 ชม. หรือ 50 jobs (เหมือน Linux script)
- Cron `0 * * * *` จะเริ่ม worker ใหม่ทุกชั่วโมง เมื่อตัวเก่าจบ
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
php artisan queue:listen database --queue=default,periods,media
```

### Production (Cron - ทุก 1 นาที)
```bash
php artisan queue:work database --queue=default,periods,media --stop-when-empty --max-time=300
```

### Production (Supervisor - แนะนำ)
```bash
php artisan queue:work database --queue=default,periods,media --sleep=3 --tries=3 --max-time=3600
```
### สรุปคำสั่งสำหรับ Production (Plesk)
cd /var/www/vhosts/yourdomain.com/httpdocs/tour-api && /usr/bin/php artisan queue:work database --queue=default,periods,media --stop-when-empty --max-time=55
### ตั้งค่า Scheduled Task ใน Plesk
### Cron style:

### Command:
### อธิบาย Parameters
### Parameter	ความหมาย	ทำไมต้องใช้
### --stop-when-empty	หยุดเมื่อ queue ว่าง	ไม่ให้ process ค้าง
### --max-time=55	หยุดหลัง 55 วินาที	ให้ทันก่อน cron รอบถัดไป (60 วิ)