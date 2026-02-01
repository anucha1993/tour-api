# Two-Phase Sync สำหรับ Wholesaler API

## ภาพรวม

บาง Wholesaler API แยก endpoint ระหว่างข้อมูลทัวร์และรอบเดินทาง เช่น:
- **ข้อมูลทัวร์**: `GET /tours/series`
- **รอบเดินทาง**: `GET /tours/series/{seriesId}/schedules`

ระบบรองรับ 2 โหมด:
| Mode | คำอธิบาย |
|------|----------|
| `single` | ทัวร์ + periods มาพร้อมกันใน response เดียว (default) |
| `two_phase` | เรียก tours ก่อน แล้ว dispatch job แยกเพื่อดึง periods |

## สถาปัตยกรรม

```
┌─────────────────────────────────────────────────────────────────┐
│                        sync_mode = 'single'                     │
├─────────────────────────────────────────────────────────────────┤
│   SyncToursJob                                                  │
│   ├── เรียก API ดึงข้อมูลทัวร์ (+ periods ในตัว)                  │
│   ├── บันทึกทัวร์                                                │
│   └── บันทึก periods                                            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      sync_mode = 'two_phase'                    │
├─────────────────────────────────────────────────────────────────┤
│   Phase 1: SyncToursJob                                         │
│   ├── เรียก API ดึงข้อมูลทัวร์                                    │
│   ├── บันทึกทัวร์                                                │
│   └── Dispatch SyncPeriodsJob (per tour)                        │
│                                                                 │
│   Phase 2: SyncPeriodsJob (x N tours)                           │
│   ├── รับ tour_id + external_id                                 │
│   ├── เรียก periods_endpoint                                    │
│   └── บันทึก periods                                            │
└─────────────────────────────────────────────────────────────────┘
```

## Configuration

### ตาราง `wholesaler_api_configs`

| Field | Type | Default | คำอธิบาย |
|-------|------|---------|----------|
| `sync_mode` | enum | 'single' | โหมดการ sync: 'single' หรือ 'two_phase' |
| `periods_endpoint` | string | null | Endpoint สำหรับดึง periods (two_phase mode) |
| `periods_id_field` | string | 'external_id' | Field ที่ใช้แทนใน URL pattern |

### ตัวอย่างการตั้งค่า

**NextTrip (single mode - default):**
```php
WholesalerApiConfig::create([
    'wholesaler_id' => 1,
    'base_url' => 'https://api.nexttrip.com',
    'tours_endpoint' => '/v2/tours',
    'sync_mode' => 'single', // periods มาพร้อมกับ tours
]);
```

**Godlike (two_phase mode):**
```php
WholesalerApiConfig::create([
    'wholesaler_id' => 2,
    'base_url' => 'https://api.godlikecenter.com',
    'tours_endpoint' => '/partner/v1/tours/series',
    'periods_endpoint' => '/partner/v1/tours/series/{external_id}/schedules',
    'sync_mode' => 'two_phase',
    'periods_id_field' => 'external_id', // จะแทนใน {external_id}
]);
```

## Jobs

### SyncToursJob

ทำหน้าที่ดึงข้อมูลทัวร์จาก API และตัดสินใจว่าจะ sync periods ทันทีหรือ dispatch job แยก

```php
// Logic หลัก
if ($apiConfig->sync_mode === 'two_phase') {
    // ไม่ sync periods ใน job นี้
    // dispatch SyncPeriodsJob แทน
    SyncPeriodsJob::dispatch($tour->id, $tour->external_id, $wholesaler->id)
        ->onQueue('periods')
        ->delay(now()->addSeconds(2));
} else {
    // single mode - sync periods จาก data ที่มาพร้อมกัน
    $this->syncPeriods($tour, $tourData['periods'] ?? []);
}
```

### SyncPeriodsJob

ทำหน้าที่ดึงข้อมูล periods สำหรับ tour เดียว

**Parameters:**
- `$tourId` - ID ของ tour ในระบบเรา
- `$externalId` - ID ของ tour ใน Wholesaler API
- `$wholesalerId` - ID ของ Wholesaler

**การทำงาน:**
1. ดึง tour และ apiConfig
2. สร้าง URL จาก periods_endpoint template
3. เรียก API
4. Map และบันทึก periods

## URL Template

`periods_endpoint` รองรับ placeholder:
- `{external_id}` - จาก tour.external_id
- `{tour_code}` - จาก tour.tour_code
- `{wholesaler_tour_code}` - จาก tour.wholesaler_tour_code

**ตัวอย่าง:**
```
/partner/v1/tours/series/{external_id}/schedules
→ /partner/v1/tours/series/GDLK-001/schedules
```

## Field Mapping สำหรับ Periods

เพิ่ม mapping สำหรับ periods ใน `wholesaler_field_mappings`:

```php
// ตัวอย่าง mapping สำหรับ Godlike
[
    'source_field' => 'scheduleId',
    'target_field' => 'external_id',
    'field_type' => 'period',
],
[
    'source_field' => 'departureDate',
    'target_field' => 'start_date',
    'field_type' => 'period',
],
[
    'source_field' => 'returnDate',
    'target_field' => 'end_date',
    'field_type' => 'period',
],
```

## Queue Configuration

แนะนำให้แยก queue สำหรับ periods:

```php
// config/queue.php หรือ .env
QUEUE_PERIODS=periods
```

```php
// ใน SyncToursJob
SyncPeriodsJob::dispatch(...)->onQueue('periods');
```

รัน worker แยก:
```bash
php artisan queue:work --queue=periods
```

## ข้อดีของ Two-Phase

1. **ไม่ timeout** - ไม่ต้องรอ 100+ API calls ใน job เดียว
2. **Retry แยกได้** - ถ้า periods ของ tour ใดมีปัญหา retry แค่ tour นั้น
3. **Scale ได้** - เพิ่ม queue workers รันขนาน
4. **ติดตามง่าย** - log แยกต่อ tour

## Backward Compatibility

- Integration เดิม (sync_mode = 'single') ทำงานเหมือนเดิม 100%
- ไม่ต้องแก้ไข configuration หรือ mapping เดิม
- Default value เป็น 'single' เสมอ
