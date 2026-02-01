# External Wholesaler API Integration

> เอกสารออกแบบระบบเชื่อมต่อ API จาก Wholesaler ภายนอก  
> Version: 3.1 | Updated: 2026-01-30

---

## 🚦 สถานะการพัฒนา (Implementation Status)

> อัพเดทล่าสุด: 30 มกราคม 2569

### ✅ เสร็จแล้ว (Completed)

#### Database & Models
| รายการ | รายละเอียด | ไฟล์/ตำแหน่ง |
|--------|------------|-------------|
| **Wholesalers CRUD** | Table + Model + Controller + API | `wholesalers` table, `WholesalerController.php` |
| **`wholesaler_api_configs` table** | การตั้งค่า API ของ Wholesaler | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`section_definitions` table** | กำหนด fields ในแต่ละ section | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`wholesaler_field_mappings` table** | mapping fields ของ wholesaler | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`sync_cursors` table** | เก็บ cursor สำหรับ incremental sync | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`sync_logs` table** | บันทึกประวัติ sync | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`sync_error_logs` table** | บันทึก errors ระหว่าง sync | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`outbound_api_logs` table** | บันทึก API calls ที่ส่งออก | `2026_01_27_200001_create_wholesaler_integration_tables.php` |
| **`webhook_logs` table** | บันทึก webhook events | `2026_01_28_100002_create_webhook_logs_table.php` |
| **`settings` table** | Global settings รวมถึง aggregation config | `2026_01_30_021442_create_settings_table.php` |
| **Section Definitions Seeder** | Seed ค่า default fields | `SectionDefinitionsSeeder.php` |
| **Models** | WholesalerApiConfig, SectionDefinition, WholesalerFieldMapping, SyncCursor, SyncLog, SyncErrorLog, OutboundApiLog, Setting | `app/Models/*.php` |

#### Sync System
| รายการ | รายละเอียด | ไฟล์/ตำแหน่ง |
|--------|------------|-------------|
| **`SyncToursJob`** | Queue job สำหรับ sync tours | `app/Jobs/SyncToursJob.php` |
| **`RunSyncCommand`** | Artisan command: `php artisan sync:tours` | `app/Console/Commands/RunSyncCommand.php` |
| **`recalculateAggregates()`** | คำนวณ price_adult, discount_adult, etc. | `app/Models/Tour.php` |
| **Tour Aggregation Config** | Global + Per-Wholesaler override | `settings.tour_aggregations` + `wholesaler_api_configs.aggregation_config` |

#### PDF Branding
| รายการ | รายละเอียด | ไฟล์/ตำแหน่ง |
|--------|------------|-------------|
| **`PdfBrandingService`** | เพิ่ม Header/Footer ใน PDF | `app/Services/PdfBrandingService.php` |
| **R2 Storage** | Upload PDF ไป Cloudflare R2 | `config/filesystems.php` (r2 disk) |

#### Adapter Pattern (Core)
| รายการ | รายละเอียด | ไฟล์/ตำแหน่ง |
|--------|------------|-------------|
| **`AdapterInterface`** | Contract หลักสำหรับ adapters | `app/Services/WholesalerAdapters/Contracts/AdapterInterface.php` |
| **`BaseAdapter`** | Logic ร่วม (auth, retry, logging) | `app/Services/WholesalerAdapters/BaseAdapter.php` |
| **`AdapterFactory`** | Factory pattern สร้าง adapter | `app/Services/WholesalerAdapters/AdapterFactory.php` |
| **`GenericRestAdapter`** | Generic REST API adapter | `app/Services/WholesalerAdapters/Adapters/GenericRestAdapter.php` |
| **DTOs** | SyncResult, AvailabilityResult, HoldResult, BookingResult | `app/Services/WholesalerAdapters/Contracts/DTOs/*.php` |

#### Mapping Engine
| รายการ | รายละเอียด | ไฟล์/ตำแหน่ง |
|--------|------------|-------------|
| **`SectionMapper`** | Core mapping engine | `app/Services/WholesalerAdapters/Mapper/SectionMapper.php` |
| **`TypeValidator`** | Data type validation | `app/Services/WholesalerAdapters/Mapper/TypeValidator.php` |
| **`LookupResolver`** | Resolve lookups (country, city) | `app/Services/WholesalerAdapters/Mapper/LookupResolver.php` |

#### API Endpoints (IntegrationController)
| รายการ | Method | Endpoint |
|--------|--------|----------|
| **List Integrations** | GET | `/api/integrations` |
| **Show Integration** | GET | `/api/integrations/{id}` |
| **Create Integration** | POST | `/api/integrations` |
| **Update Integration** | PUT | `/api/integrations/{id}` |
| **Delete Integration** | DELETE | `/api/integrations/{id}` |
| **Test Connection** | POST | `/api/integrations/test-connection` |
| **Fetch Sample Data** | GET | `/api/integrations/{id}/fetch-sample` |
| **Get Section Definitions** | GET | `/api/integrations/section-definitions` |
| **Get Field Mappings** | GET | `/api/integrations/{id}/mappings` |
| **Save Field Mappings** | POST | `/api/integrations/{id}/mappings` |
| **Test Mapping (Dry Run)** | POST | `/api/integrations/{id}/test-mapping` |
| **Preview Mapping** | POST | `/api/integrations/{wholesalerId}/preview-mapping` |
| **Toggle Sync** | POST | `/api/integrations/{id}/toggle-sync` |
| **Health Check** | POST | `/api/integrations/{id}/health-check` |
| **Get Sync History** | GET | `/api/integrations/{wholesalerId}/sync-history` |

#### Admin UI
| รายการ | รายละเอียด | ไฟล์/ตำแหน่ง |
|--------|------------|-------------|
| **Admin UI - Wholesalers** | รายการ, เพิ่ม, แก้ไข, ลบ | `/dashboard/wholesalers/*` |
| **Integration List UI** | หน้ารายการ Integrations (Real data) | `/dashboard/integrations/page.tsx` |
| **Integration Wizard UI** | 5 Steps: Wholesaler → API → Test → Mapping → Preview | `/dashboard/integrations/new/page.tsx` |

### 🔄 กำลังทำ / มี UI แต่ยังไม่มี Backend

| รายการ | Frontend | Backend | หมายเหตุ |
|--------|:--------:|:-------:|----------|
| Sync Now Button | ✅ UI | ❌ | ต้องทำ SyncToursJob |
| Real-time Sync Progress | ✅ Placeholder | ❌ | ต้องใช้ WebSocket/Pusher |

### ❌ ยังไม่ได้ทำ (Pending)

#### Phase 4: Sync System (ต่อ)
| Task | สถานะ | Priority | หมายเหตุ |
|------|:------:|:--------:|----------|
| `SyncToursJob` | ✅ | 🔴 High | Queue job สำหรับ sync |
| `RunSyncCommand` | ✅ | 🔴 High | Artisan command: `php artisan sync:tours` |
| `recalculateAggregates()` | ✅ | 🔴 High | คำนวณ price_adult, discount_adult |
| `settings` table | ✅ | 🔴 High | Global aggregation config |
| ACK Callback Implementation | ❌ | 🟡 Medium | แจ้ง wholesaler ว่ารับแล้ว |
| Scheduler (cron) | ❌ | 🟡 Medium | Auto sync ตาม schedule |

#### Phase 5: Booking Outbound
| Task | สถานะ | Priority |
|------|:------:|:--------:|
| `booking_holds` table | ❌ | 🟠 Later |
| `booking_syncs` table | ❌ | 🟠 Later |
| Availability Check API | ❌ | 🟠 Later |
| Hold Booking (TTL) | ❌ | 🟠 Later |
| Confirm/Cancel Booking | ❌ | 🟠 Later |
| Auto-Expire Job | ❌ | 🟠 Later |

#### Phase 6: Webhooks
| Task | สถานะ | Priority |
|------|:------:|:--------:|
| Webhook Receive Endpoint | ❌ | 🟠 Later |
| Signature Verification | ❌ | 🟠 Later |
| Event Handlers | ❌ | 🟠 Later |

#### Phase 7: Admin UI (Advanced)
| Task | สถานะ | Priority |
|------|:------:|:--------:|
| Real-time Sync Progress | ❌ | 🟡 Medium |
| Error Dashboard with Charts | ❌ | 🟡 Medium |

### 📊 Overall Progress

```
Foundation:     ████████████████ 100% ✅
Mapping Engine: ████████████████ 100% ✅
Adapter Pattern:████████████████ 100% ✅
API Endpoints:  ██████████████░░ 90%
Sync System:    ████████████████ 100% ✅
Settings:       ████████████████ 100% ✅
PDF Branding:   ████████████████ 100% ✅
Booking Flow:   ░░░░░░░░░░░░░░░░ 0%
Webhooks:       ██░░░░░░░░░░░░░░ 15%
Admin UI:       ██████████████░░ 85%
────────────────────────────────────
Total:          ████████████░░░░ ~75%
```

### 🎯 แนะนำขั้นตอนถัดไป

1. ~~**สร้าง `SyncToursJob`**~~ ✅ เสร็จแล้ว
2. ~~**สร้าง Artisan Command**~~ ✅ เสร็จแล้ว - `php artisan sync:tours {wholesaler_id}`
3. **เชื่อม Sync Now Button กับ Backend** - เรียก API endpoint ที่ dispatch SyncToursJob
4. **เพิ่ม Real-time Progress** - ใช้ Laravel Echo/Pusher แสดง progress ระหว่าง sync
5. **เชื่อม UI Wizard กับ Backend** - Save integration config
6. **สร้าง Settings UI** - หน้า Settings > Aggregation สำหรับ config วิธีคำนวณราคา

---

## 📊 Tour Aggregation Settings

### Overview

ระบบคำนวณค่า aggregate สำหรับ Tour (price_adult, discount_adult, etc.) จาก offers/periods โดยสามารถ config วิธีคำนวณได้

### Database

#### `settings` table
```sql
CREATE TABLE settings (
    id BIGINT PRIMARY KEY,
    `group` VARCHAR(255) DEFAULT 'general',
    `key` VARCHAR(255) UNIQUE,
    value TEXT,
    type VARCHAR(50) DEFAULT 'string', -- string, integer, boolean, json
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at, updated_at
);

-- Default aggregation config
INSERT INTO settings (`group`, `key`, value, type) VALUES 
('aggregation', 'tour_aggregations', '{"price_adult":"min","discount_adult":"max","min_price":"min","max_price":"max","display_price":"min","discount_amount":"max"}', 'json');
```

#### `wholesaler_api_configs.aggregation_config` column
```sql
ALTER TABLE wholesaler_api_configs 
ADD COLUMN aggregation_config JSON NULL;
-- NULL = ใช้ global settings
-- มีค่า = override เฉพาะ field ที่ระบุ
```

### Config Options

| Field | คำอธิบาย | Options |
|-------|----------|---------|
| `price_adult` | ราคาผู้ใหญ่ที่แสดง | `min`, `max`, `avg`, `first` |
| `discount_adult` | ส่วนลดผู้ใหญ่ | `min`, `max`, `avg`, `first` |
| `min_price` | ราคาต่ำสุด | `min`, `max`, `avg`, `first` |
| `max_price` | ราคาสูงสุด | `min`, `max`, `avg`, `first` |
| `display_price` | ราคาที่แสดงบน card | `min`, `max`, `avg`, `first` |
| `discount_amount` | จำนวนส่วนลด | `min`, `max`, `avg`, `first` |

### Priority (Cascade)

```
Default Config (hardcoded)
    ↓
Global Settings (settings table)
    ↓
Wholesaler Override (wholesaler_api_configs.aggregation_config)
    ↓
Method Parameter Override
```

### Usage

```php
// ใน Tour model
$tour->recalculateAggregates();

// หรือ override config
$tour->recalculateAggregates(['price_adult' => 'avg']);
```

### Setting Model

```php
// Get setting
$config = Setting::get('tour_aggregations'); // returns array

// Get nested value
$priceMethod = Setting::get('tour_aggregations.price_adult'); // returns 'min'

// Set setting
Setting::set('tour_aggregations', ['price_adult' => 'avg', ...], 'aggregation', 'json');
```

---

## 📄 PDF Branding (Header/Footer Overlay)

### Overview

ระบบแทรก Header/Footer ลงบน PDF โบรชัวร์ที่ได้รับจาก Wholesaler ก่อน upload ไป Cloudflare

### Configuration

| Field | Type | Description |
|-------|------|-------------|
| `pdf_header_image` | URL | รูป Header (Cloudflare) |
| `pdf_footer_image` | URL | รูป Footer (Cloudflare) |
| `pdf_header_height` | INT | ความสูง Header (auto จากรูป) |
| `pdf_footer_height` | INT | ความสูง Footer (auto จากรูป) |

### Processing Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PDF Branding Flow                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   1. ตั้งค่า (ครั้งเดียวต่อ Integration)                                      │
│      ┌──────────────────────┐                                               │
│      │  Integration Config  │                                               │
│      ├──────────────────────┤                                               │
│      │ Header Image: [📤]   │──▶ Upload to Cloudflare                       │
│      │ Footer Image: [📤]   │──▶ Upload to Cloudflare                       │
│      └──────────────────────┘                                               │
│                                                                              │
│   2. เมื่อ Sync ทัวร์ที่มี PDF                                                │
│                                                                              │
│      ┌─────────────┐      ┌─────────────┐      ┌─────────────┐              │
│      │ Wholesaler  │      │ Overlay     │      │  Upload to  │              │
│      │ PDF (เดิม)  │──▶  │ Header +    │──▶  │ Cloudflare  │              │
│      │             │      │ Footer      │      │ (Branded)   │              │
│      └─────────────┘      └─────────────┘      └─────────────┘              │
│                                                                              │
│   3. ผลลัพธ์ (ทุกหน้า)                                                        │
│      ┌─────────────────────┐                                                │
│      │ ┌─────────────────┐ │                                                │
│      │ │  YOUR HEADER    │ │ ← Overlay รูป Header (dynamic size)           │
│      │ ├─────────────────┤ │                                                │
│      │ │                 │ │                                                │
│      │ │  WHOLESALER     │ │ ← เนื้อหา PDF เดิม (ถูกทับบางส่วน)             │
│      │ │  CONTENT        │ │                                                │
│      │ │                 │ │                                                │
│      │ ├─────────────────┤ │                                                │
│      │ │  YOUR FOOTER    │ │ ← Overlay รูป Footer (dynamic size)           │
│      │ └─────────────────┘ │                                                │
│      └─────────────────────┘                                                │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Technical Implementation

- **Library**: `setasign/fpdi` + `tecnickcom/tcpdf`
- **Overlay Mode**: วางทับบนเนื้อหาเดิม (ไม่ resize)
- **Apply To**: ทุกหน้าของ PDF
- **Size**: Dynamic ตามขนาดรูปที่ upload
- **Storage**: Cloudflare Images

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/integrations/{id}/upload-header` | Upload header image |
| POST | `/integrations/{id}/upload-footer` | Upload footer image |
| DELETE | `/integrations/{id}/header` | Remove header |
| DELETE | `/integrations/{id}/footer` | Remove footer |

---

## 🔄 SyncToursJob - หลักการทำงาน

### Overview

SyncToursJob รองรับ 2 โหมดการทำงาน:

### Mode 1: Manual Sync (จาก Wizard UI)

Frontend ส่ง `transformed_data` ที่ mapping แล้วไปให้ Backend insert ได้เลย

```
┌─────────────────────────────────────────────────────────────────────────────┐
│   FRONTEND (Wizard)                        BACKEND (Laravel)                 │
│   ─────────────────                        ─────────────────                 │
│                                                                              │
│   1. Fetch Sample → Adapter.fetchTours()                                    │
│   2. User ทำ Field Mapping (UI)                                              │
│   3. Test Mapping (Dry Run) → Validate only                                  │
│   4. Save Mapping Config → wholesaler_field_mappings                        │
│   5. Sync Now                                                                │
│      POST /sync-now { transformed_data[] }                                   │
│      └──▶ Insert to DB + Process PDF Branding                               │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Mode 2: Automatic Sync (Cron Job)

Backend fetch + mapping + insert ทั้งหมด

```
┌─────────────────────────────────────────────────────────────────────────────┐
│   SCHEDULER (Cron)                         BACKEND                           │
│   ────────────────                         ────────                          │
│                                                                              │
│   1. Every 2 hours → SyncToursJob::dispatch(wholesaler_id)                  │
│   2. Adapter.fetchTours(cursor) → raw data                                  │
│   3. SectionMapper.mapTour(raw_data) → normalized data                      │
│   4. PdfBrandingService.process(pdf_url) → branded PDF URL                  │
│   5. Tour::updateOrCreate(), Period::updateOrCreate()                       │
│   6. Update SyncCursor + SyncLog                                            │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Sync Process Flow

```php
// SyncToursJob.php
public function handle()
{
    $syncLog = SyncLog::create([...]);
    
    try {
        // 1. Get transformed data (from request or fetch+map)
        $tours = $this->getTransformedData();
        
        foreach ($tours as $tourData) {
            // 2. Process PDF if exists
            if ($tourData['pdf_url'] ?? null) {
                $tourData['pdf_url'] = $this->pdfBranding->process(
                    $tourData['pdf_url'],
                    $this->config->pdf_header_image,
                    $this->config->pdf_footer_image
                );
            }
            
            // 3. Create/Update Tour
            $tour = Tour::updateOrCreate(
                ['wholesaler_tour_code' => $tourData['tour_code']],
                $tourData['tour']
            );
            
            // 4. Sync Periods
            foreach ($tourData['departure'] as $dep) {
                Period::updateOrCreate([...], $dep);
            }
            
            // 5. Sync Itineraries
            foreach ($tourData['itinerary'] as $itin) {
                TourItinerary::updateOrCreate([...], $itin);
            }
            
            $syncLog->increment('tours_created');
        }
        
        $syncLog->update(['status' => 'completed']);
        
    } catch (\Exception $e) {
        $syncLog->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
    }
}
```

### Files to Create

| File | Description |
|------|-------------|
| `app/Jobs/SyncToursJob.php` | Main sync queue job |
| `app/Services/PdfBrandingService.php` | PDF overlay service |
| `app/Console/Commands/SyncToursCommand.php` | Artisan command |
| Migration: add pdf fields | `pdf_header_image`, `pdf_footer_image` |

---

## 📋 สารบัญ

1. [ภาพรวมของระบบ](#1-ภาพรวมของระบบ)
2. [Bidirectional Sync Flow](#2-bidirectional-sync-flow)
3. [Adapter Pattern](#3-adapter-pattern)
4. [Section-based Mapping](#4-section-based-mapping-with-fixed-data-types)
5. [Data Types Schema](#5-data-types-schema)
6. [Sync Acknowledgment (ACK)](#6-sync-acknowledgment-ack)
7. [Booking Flow & Outbound API](#7-booking-flow--outbound-api)
8. [TTL & Hold Management](#8-ttl--hold-management)
9. [Retry & Error Handling](#9-retry--error-handling)
10. [Webhook Support](#10-webhook-support)
11. [Database Schema](#11-database-schema)
12. [Admin UI](#12-admin-ui)
13. [Implementation Plan](#13-implementation-plan)

---

## 1. ภาพรวมของระบบ

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        NextTrip Platform                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐    ┌──────────────────┐    ┌──────────────────┐  │
│  │  Wholesaler  │    │  Section-based   │    │   Normalized     │  │
│  │   Adapters   │───▶│  Mapper Engine   │───▶│   Tours Data     │  │
│  │              │    │  (Type-safe)     │    │                  │  │
│  └──────────────┘    └──────────────────┘    └──────────────────┘  │
│        ▲                     │                       │              │
│        │                     ▼                       ▼              │
│  ┌─────┴────────────────────────────────────────────────────────┐  │
│  │                 Dynamic Section Configuration                 │  │
│  │  • Section: tour_info    • Section: period                   │  │
│  │  • Section: pricing      • Section: content                  │  │
│  │  • Fixed Data Types (TEXT, INT, DECIMAL, DATE, ARRAY, etc.)  │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
          ▲                    ▲                    ▲
          │                    │                    │
    ┌─────┴─────┐        ┌─────┴─────┐        ┌─────┴─────┐
    │Wholesaler │        │Wholesaler │        │Wholesaler │
    │    A      │        │    B      │        │    C      │
    │ (REST)    │        │ (SOAP)    │        │ (GraphQL) │
    └───────────┘        └───────────┘        └───────────┘
```

### หลักการสำคัญ

1. **Section-based Mapping** - ไม่ fix field names, แต่ fix data types
2. **Bidirectional Sync** - รับเข้า + ส่งออก (ACK, Booking)
3. **Type-safe** - Validate ทุก field ตาม data type ก่อน save
4. **Extensible** - เพิ่ม field ใน section ได้ไม่จำกัด

---

## 2. Bidirectional Sync Flow

### 2-Way Communication

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      2-Way Communication Flow                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────┐                              ┌──────────────┐         │
│  │   NextTrip   │                              │  Wholesaler  │         │
│  │   Platform   │                              │     API      │         │
│  └──────┬───────┘                              └──────┬───────┘         │
│         │                                             │                  │
│         │  ═══════════ INBOUND (รับเข้า) ═══════════  │                  │
│         │                                             │                  │
│         │◀────────── 1. Fetch Tours ─────────────────│                  │
│         │                                             │                  │
│         │───────── 2. ACK: รับแล้ว ─────────────────▶│                  │
│         │            {tour_codes[], sync_id}         │                  │
│         │                                             │                  │
│         │  ═══════════ OUTBOUND (ส่งออก) ════════════ │                  │
│         │                                             │                  │
│         │──────── 3. Check Availability ────────────▶│                  │
│         │◀─────────── {seats, prices} ───────────────│                  │
│         │                                             │                  │
│         │──────── 4. Hold Booking ──────────────────▶│                  │
│         │◀─────────── {hold_id, TTL} ────────────────│                  │
│         │                                             │                  │
│         │──────── 5. Confirm Booking ───────────────▶│                  │
│         │◀───────── {booking_ref} ───────────────────│                  │
│         │                                             │                  │
│         │──────── 6. Update Booking ────────────────▶│                  │
│         │          (cancel, modify, paid)            │                  │
│         │                                             │                  │
└─────────────────────────────────────────────────────────────────────────┘
```

### Communication Types

| Direction | Type | Description |
|-----------|------|-------------|
| **INBOUND** | Fetch Tours | ดึงข้อมูลทัวร์จาก Wholesaler |
| **INBOUND** | Webhook | รับ notification จาก Wholesaler |
| **OUTBOUND** | ACK | แจ้ง Wholesaler ว่ารับข้อมูลแล้ว |
| **OUTBOUND** | Availability | เช็คที่นั่งว่าง real-time |
| **OUTBOUND** | Hold | จองที่นั่งชั่วคราว |
| **OUTBOUND** | Confirm | ยืนยันการจอง |
| **OUTBOUND** | Cancel/Modify | แก้ไข/ยกเลิกการจอง |

---

## 3. Adapter Pattern

### โครงสร้างไฟล์

```
app/Services/
└── WholesalerAdapters/
    ├── Contracts/
    │   ├── AdapterInterface.php      # Contract หลัก
    │   └── DTOs/
    │       ├── AvailabilityResult.php
    │       ├── HoldResult.php
    │       ├── BookingResult.php
    │       └── SyncResult.php
    ├── BaseAdapter.php               # Logic ร่วม (auth, retry, logging)
    ├── Adapters/
    │   ├── WholesalerAAdapter.php
    │   ├── WholesalerBAdapter.php
    │   └── ...
    ├── AdapterFactory.php            # Factory pattern
    └── Mapper/
        ├── SectionMapper.php         # Section-based mapping engine
        ├── TypeValidator.php         # Data type validation
        └── LookupResolver.php        # Resolve lookups (country, city, etc.)
```

### AdapterInterface

```php
<?php

namespace App\Services\WholesalerAdapters\Contracts;

interface AdapterInterface
{
    // ═══════════ INBOUND ═══════════
    
    /**
     * ดึงรายการทัวร์ (ใช้ cursor เพื่อไม่ให้ซ้ำ)
     */
    public function fetchTours(?string $cursor = null): SyncResult;
    
    /**
     * ดึงรายละเอียดทัวร์
     */
    public function fetchTourDetail(string $code): ?array;
    
    // ═══════════ OUTBOUND ═══════════
    
    /**
     * แจ้ง ACK ว่ารับข้อมูลแล้ว
     */
    public function acknowledgeSynced(array $tourCodes, string $syncId): bool;
    
    /**
     * เช็คที่นั่งว่าง (Real-time)
     */
    public function checkAvailability(
        string $code, 
        string $date, 
        int $paxAdult, 
        int $paxChild = 0
    ): AvailabilityResult;
    
    /**
     * จองที่นั่งชั่วคราว (Hold with TTL)
     */
    public function holdBooking(
        string $code, 
        string $date, 
        int $paxAdult, 
        int $paxChild = 0
    ): HoldResult;
    
    /**
     * ยืนยันการจอง
     */
    public function confirmBooking(string $holdId, array $passengers, array $paymentInfo): BookingResult;
    
    /**
     * ยกเลิกการจอง
     */
    public function cancelBooking(string $bookingRef, string $reason): BookingResult;
    
    /**
     * แก้ไขการจอง
     */
    public function modifyBooking(string $bookingRef, array $changes): BookingResult;
    
    /**
     * ตรวจสอบสถานะ API
     */
    public function healthCheck(): bool;
}
```

### Result DTOs

```php
// SyncResult - ผลการ sync tours
class SyncResult
{
    public bool $success;
    public array $tours;           // Raw tour data
    public ?string $nextCursor;    // สำหรับ fetch ครั้งถัดไป
    public bool $hasMore;          // ยังมีข้อมูลอีกไหม
    public int $totalCount;
    public ?string $errorMessage;
}

// AvailabilityResult - ผลเช็คที่นั่ง
class AvailabilityResult
{
    public bool $available;
    public int $remainingSeats;
    public float $priceAdult;
    public float $priceChild;
    public ?string $currency;
    public ?Carbon $cachedAt;
    public ?Carbon $expiresAt;     // TTL
}

// HoldResult - ผลการ hold
class HoldResult
{
    public bool $success;
    public ?string $holdId;
    public ?Carbon $expiresAt;     // เวลาที่ hold หมดอายุ
    public ?string $errorMessage;
    public ?string $errorCode;
}

// BookingResult - ผลการจอง
class BookingResult
{
    public bool $success;
    public ?string $bookingRef;
    public ?string $confirmationNumber;
    public ?string $status;
    public ?string $errorMessage;
    public ?string $errorCode;
    public ?array $metadata;
}
```

---

## 4. Section-based Mapping with Fixed Data Types

### แนวคิดหลัก

**แทนที่จะ fix field names → เราใช้ Sections + Fixed Data Types**

- Wholesaler ส่ง field ชื่ออะไรก็ได้
- เรา map เข้า section ที่กำหนด
- Validate ตาม data type ก่อน save
- เพิ่ม field ใน section ได้ไม่จำกัด

### Section Definitions

```
┌─────────────────────────────────────────────────────────────────┐
│                    Section-based Mapping                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐     ┌─────────────────┐                    │
│  │ Section: tour   │     │ Section: period │                    │
│  ├─────────────────┤     ├─────────────────┤                    │
│  │ title: TEXT     │     │ start_date: DATE│                    │
│  │ code: TEXT      │     │ end_date: DATE  │                    │
│  │ days: INT       │     │ price: DECIMAL  │                    │
│  │ countries: []   │     │ capacity: INT   │                    │
│  │ highlights: []  │     │ status: ENUM    │                    │
│  └─────────────────┘     └─────────────────┘                    │
│                                                                  │
│  ┌─────────────────┐     ┌─────────────────┐                    │
│  │Section: pricing │     │Section: content │                    │
│  ├─────────────────┤     ├─────────────────┤                    │
│  │ adult: DECIMAL  │     │ highlights: []  │                    │
│  │ child: DECIMAL  │     │ inclusions: TEXT│                    │
│  │ single: DECIMAL │     │ exclusions: TEXT│                    │
│  │ discount: DECIMAL│    │ images: []      │                    │
│  └─────────────────┘     └─────────────────┘                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Sections Schema

#### Section: `tour` (ข้อมูลทัวร์)

| Field | Data Type | Required | Description |
|-------|-----------|----------|-------------|
| `title` | TEXT | ✅ | ชื่อทัวร์ |
| `code` | TEXT | ✅ | รหัสทัวร์ของ wholesaler |
| `tour_type` | ENUM | | join, incentive, collective |
| `duration_days` | INT | ✅ | จำนวนวัน |
| `duration_nights` | INT | | จำนวนคืน (default: days-1) |
| `hotel_star` | INT | | ระดับโรงแรม (3, 4, 5) |
| `countries` | ARRAY[TEXT] | ✅ | ประเทศ → lookup to IDs |
| `cities` | ARRAY[TEXT] | | เมือง → lookup to IDs |
| `transport` | TEXT | | สายการบิน → lookup to ID |
| `description` | TEXT | | รายละเอียด |
| `*` | ANY | | เพิ่มได้ไม่จำกัด |

#### Section: `period` (รอบเดินทาง)

| Field | Data Type | Required | Description |
|-------|-----------|----------|-------------|
| `start_date` | DATE | ✅ | วันเริ่มเดินทาง |
| `end_date` | DATE | ✅ | วันสิ้นสุด |
| `capacity` | INT | | จำนวนที่นั่ง |
| `booked` | INT | | จองแล้ว |
| `status` | ENUM | | open, closed, full, cancelled |
| `is_visible` | BOOLEAN | | แสดงหรือไม่ |
| `*` | ANY | | เพิ่มได้ไม่จำกัด |

#### Section: `pricing` (ราคา)

| Field | Data Type | Required | Description |
|-------|-----------|----------|-------------|
| `price_adult` | DECIMAL | ✅ | ราคาผู้ใหญ่ |
| `price_child` | DECIMAL | | ราคาเด็ก |
| `price_child_nobed` | DECIMAL | | ราคาเด็กไม่มีเตียง |
| `price_single` | DECIMAL | | พักเดี่ยว |
| `discount_adult` | DECIMAL | | ส่วนลดผู้ใหญ่ |
| `discount_child` | DECIMAL | | ส่วนลดเด็ก |
| `currency` | TEXT | | สกุลเงิน (default: THB) |
| `*` | ANY | | เพิ่มได้ไม่จำกัด |

#### Section: `content` (เนื้อหา)

| Field | Data Type | Required | Description |
|-------|-----------|----------|-------------|
| `highlights` | ARRAY[TEXT] | | ไฮไลท์การเดินทาง |
| `food_highlights` | ARRAY[TEXT] | | ไฮไลท์อาหาร |
| `shopping_highlights` | ARRAY[TEXT] | | ไฮไลท์ช้อปปิ้ง |
| `inclusions` | TEXT | | สิ่งที่รวม (HTML ok) |
| `exclusions` | TEXT | | สิ่งที่ไม่รวม |
| `conditions` | TEXT | | เงื่อนไข |
| `itinerary` | JSON | | โปรแกรมการเดินทาง |
| `*` | ANY | | เพิ่มได้ไม่จำกัด |

#### Section: `media` (สื่อ)

| Field | Data Type | Required | Description |
|-------|-----------|----------|-------------|
| `cover_image` | TEXT | | URL รูปปก |
| `cover_alt` | TEXT | | Alt text |
| `gallery` | ARRAY[TEXT] | | URLs รูปภาพ |
| `pdf_url` | TEXT | | PDF โปรแกรม |
| `video_url` | TEXT | | Video |
| `*` | ANY | | เพิ่มได้ไม่จำกัด |

#### Section: `seo`

| Field | Data Type | Required | Description |
|-------|-----------|----------|-------------|
| `slug` | TEXT | | URL slug |
| `meta_title` | TEXT | | Meta title |
| `meta_description` | TEXT | | Meta description |
| `keywords` | ARRAY[TEXT] | | Keywords |
| `*` | ANY | | เพิ่มได้ไม่จำกัด |

---

## 5. Data Types Schema

### Fixed Data Types

| Type | Format | Validation | Example |
|------|--------|------------|---------|
| `TEXT` | string, max 65535 | - | "ทัวร์ญี่ปุ่น โตเกียว" |
| `INT` | integer | numeric | 5, 30, 100 |
| `DECIMAL` | float(12,2) | numeric | 29900.00, 5000.50 |
| `DATE` | Y-m-d | date format | "2026-03-15" |
| `DATETIME` | Y-m-d H:i:s | datetime | "2026-03-15 08:00:00" |
| `BOOLEAN` | true/false | boolean | true, false |
| `ENUM[values]` | predefined | in list | "join" \| "incentive" |
| `ARRAY[TEXT]` | string[] | array of strings | ["โตเกียว", "โอซาก้า"] |
| `ARRAY[INT]` | int[] | array of integers | [1, 5, 12] |
| `ARRAY[DECIMAL]` | float[] | array of decimals | [1000.00, 2000.50] |
| `JSON` | object | valid JSON | {"key": "value"} |

### Type Conversion Rules

| From Wholesaler | Our Type | Conversion |
|-----------------|----------|------------|
| "5" (string) | INT | `intval()` |
| 29900 (int) | DECIMAL | `floatval()` |
| "2026/03/15" | DATE | parse + format |
| "yes", "1", "true" | BOOLEAN | true |
| "no", "0", "false" | BOOLEAN | false |
| "Japan,Korea" | ARRAY[TEXT] | explode(",") |
| Nested object | JSON | `json_encode()` |

### Lookup Resolution

สำหรับ field ที่ต้อง lookup เป็น ID:

```php
// Lookup Config
$lookups = [
    'countries' => [
        'table' => 'countries',
        'match_fields' => ['name_en', 'name_th', 'iso2', 'iso3'],
        'return_field' => 'id',
        'create_if_not_found' => false,
    ],
    'cities' => [
        'table' => 'cities',
        'match_fields' => ['name_en', 'name_th'],
        'return_field' => 'id',
        'create_if_not_found' => true,  // สร้างใหม่ถ้าไม่มี
        'parent_field' => 'country_id',
    ],
    'transport' => [
        'table' => 'transports',
        'match_fields' => ['code', 'name'],
        'return_field' => 'id',
    ],
];
```

---

## 6. Sync Acknowledgment (ACK)

### ป้องกันการส่งทัวร์ซ้ำ

เมื่อ sync สำเร็จ เราต้องแจ้ง Wholesaler เพื่อไม่ให้ส่งซ้ำ

### Option A: Cursor-based (แนะนำ)

```
# Request
GET /api/tours?cursor={last_cursor}

# Response
{
  "tours": [...],
  "next_cursor": "eyJpZCI6MTAwfQ==",
  "has_more": true
}
```

- เราเก็บ `next_cursor` ไว้ใน `sync_cursors` table
- ครั้งถัดไปส่ง cursor → ได้เฉพาะ tours ใหม่/updated
- **ไม่ต้อง callback กลับ**

### Option B: Explicit ACK Callback

```php
// หลังจาก sync สำเร็จ
$adapter->acknowledgeSynced(
    tourCodes: ['TH001', 'TH002', 'JP015'],
    syncId: 'sync_20260127_143000'
);

// API Call
POST https://wholesaler.com/api/sync/acknowledge
{
  "sync_id": "sync_20260127_143000",
  "tour_codes": ["TH001", "TH002", "JP015"],
  "status": "success",
  "received_at": "2026-01-27T14:30:00Z"
}
```

- Wholesaler รู้ว่าเรารับแล้ว
- จะไม่ส่ง tours เหล่านี้ซ้ำ (จนกว่าจะมี update)

### Option C: Last-Modified + ETag

```
# Request with conditional headers
GET /api/tours
If-Modified-Since: Mon, 27 Jan 2026 10:00:00 GMT
If-None-Match: "abc123"

# Response
- 200 OK + tours ที่เปลี่ยน
- 304 Not Modified (ไม่มีอะไรใหม่)
```

### Sync Tracking Table

```sql
CREATE TABLE sync_cursors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    sync_type ENUM('tours', 'periods', 'prices') NOT NULL,
    cursor_value VARCHAR(500),
    last_sync_id VARCHAR(100),
    last_synced_at TIMESTAMP,
    tours_received INT DEFAULT 0,
    
    UNIQUE KEY (wholesaler_id, sync_type)
);
```

---

## 7. Booking Flow & Outbound API

### Complete Booking Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Complete Booking Flow                            │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Customer        NextTrip              Wholesaler API                   │
│     │               │                        │                           │
│     │──Select Tour──▶│                        │                           │
│     │               │                        │                           │
│     │               │───Check Availability──▶│                           │
│     │               │◀──{seats, price}───────│                           │
│     │◀──Show Price──│                        │                           │
│     │               │                        │                           │
│     │──Proceed──────▶│                        │                           │
│     │               │───Hold Booking────────▶│  ← จองชั่วคราว            │
│     │               │◀──{hold_id, TTL:15m}───│                           │
│     │               │                        │                           │
│     │◀─Fill Form────│       ⏱️ TTL Timer      │                           │
│     │──Submit───────▶│                        │                           │
│     │──Payment──────▶│                        │                           │
│     │               │                        │                           │
│     │               │───Confirm Booking─────▶│  ← ยืนยันจอง              │
│     │               │   {hold_id, pax_info,  │                           │
│     │               │    payment_ref}        │                           │
│     │               │◀──{booking_ref}────────│                           │
│     │               │                        │                           │
│     │◀──Confirmation─│                        │                           │
│     │   {booking_ref}│                        │                           │
│     │               │                        │                           │
│  ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ Later Changes ─ ─ ─ ─ ─ ─ ─ ─ ─ ─                  │
│     │               │                        │                           │
│     │──Request Cancel▶│                        │                           │
│     │               │───Cancel Booking──────▶│  ← แจ้งยกเลิก             │
│     │               │◀──{cancelled, refund}──│                           │
│     │◀──Refund Info──│                        │                           │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Outbound API Endpoints

| Action | Method | Endpoint Example | Request Body |
|--------|--------|------------------|--------------|
| **Check Availability** | GET | `/tours/{code}/availability` | `?date=2026-03-15&pax_adult=2&pax_child=1` |
| **Hold Booking** | POST | `/bookings/hold` | `{tour_code, date, pax_adult, pax_child, hold_minutes}` |
| **Confirm Booking** | POST | `/bookings/confirm` | `{hold_id, passengers[], payment_ref, contact}` |
| **Cancel Booking** | POST | `/bookings/{ref}/cancel` | `{reason, refund_requested}` |
| **Modify Booking** | PUT | `/bookings/{ref}` | `{changes...}` |
| **Get Booking Status** | GET | `/bookings/{ref}` | - |
| **ACK Sync** | POST | `/sync/acknowledge` | `{tour_codes[], sync_id}` |

### Passengers Data Structure

```json
{
  "passengers": [
    {
      "type": "adult",
      "title": "Mr",
      "first_name": "John",
      "last_name": "Doe",
      "passport_no": "AB1234567",
      "passport_expiry": "2030-12-31",
      "nationality": "TH",
      "date_of_birth": "1990-05-15"
    },
    {
      "type": "child",
      "title": "Master",
      "first_name": "Tom",
      "last_name": "Doe",
      "date_of_birth": "2018-08-20"
    }
  ],
  "contact": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+66812345678"
  }
}
```

---

## 8. TTL & Hold Management

### TTL Configuration

| Stage | TTL | Description |
|-------|-----|-------------|
| **Availability Cache** | 5 min | Cache ผลเช็คที่นั่ง ลด API calls |
| **Booking Hold** | 15-30 min | เวลาให้ลูกค้ากรอกข้อมูล + ชำระเงิน |
| **Payment Session** | 15 min | เวลาสำหรับ payment gateway |

### Hold Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                      Hold Lifecycle                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  PENDING ──▶ HELD ──▶ CONFIRMED                                 │
│     │          │           │                                     │
│     │          │           └──▶ COMPLETED                       │
│     │          │                                                 │
│     │          ├──▶ EXPIRED (TTL หมด)                           │
│     │          │                                                 │
│     │          └──▶ RELEASED (ลูกค้ายกเลิก)                     │
│     │                                                            │
│     └──▶ FAILED (API error)                                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Auto-Expire Job

```php
// app/Console/Kernel.php
Schedule::call(function () {
    BookingHold::where('status', 'held')
        ->where('hold_expires_at', '<', now())
        ->each(function ($hold) {
            $hold->update(['status' => 'expired']);
            
            // Optional: Notify wholesaler
            if ($hold->wholesaler->supports_release) {
                dispatch(new ReleaseHoldJob($hold));
            }
        });
})->everyMinute();
```

---

## 9. Retry & Error Handling

### Retry Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    Retry Strategy                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Attempt 1 ──▶ Failed ──▶ Wait 1 min                            │
│                              │                                   │
│  Attempt 2 ◀─────────────────┘                                  │
│       │                                                          │
│       └──▶ Failed ──▶ Wait 5 min                                │
│                           │                                      │
│  Attempt 3 ◀──────────────┘                                     │
│       │                                                          │
│       └──▶ Failed ──▶ Wait 15 min                               │
│                           │                                      │
│  Attempt 4 ◀──────────────┘                                     │
│       │                                                          │
│       └──▶ Failed ──▶ Wait 60 min                               │
│                           │                                      │
│  Attempt 5 ◀──────────────┘                                     │
│       │                                                          │
│       └──▶ Failed ──▶ 🚨 Alert Admin + Manual Queue             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Error Categories

| Category | Example | Action |
|----------|---------|--------|
| **Transient** | Timeout, 5xx | Auto-retry with backoff |
| **Rate Limit** | 429 Too Many Requests | Wait + retry |
| **Validation** | 400 Bad Request | Log + alert, no retry |
| **Auth** | 401/403 | Alert admin, pause sync |
| **Not Found** | 404 | Log, skip tour/booking |
| **Business** | "No seats available" | Notify customer |

### Error Handler

```php
class OutboundApiHandler
{
    public function handle(WholesalerException $e, OutboundApiLog $log)
    {
        $log->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'retry_count' => $log->retry_count + 1,
        ]);
        
        match($e->getCategory()) {
            'transient' => $this->scheduleRetry($log),
            'rate_limit' => $this->scheduleRetry($log, delay: 60),
            'validation' => $this->alertAdmin($log),
            'auth' => $this->pauseWholesaler($log),
            'not_found' => $this->markNotFound($log),
            'business' => $this->notifyCustomer($log),
        };
    }
}
```

### Failure Scenarios

| Scenario | Action |
|----------|--------|
| **Hold Failed** | แจ้งลูกค้าว่าที่นั่งอาจไม่ว่าง, retry 3 ครั้ง |
| **Hold Expired** | Release booking, แจ้งลูกค้าจองใหม่ |
| **Confirm Failed** | Retry ทันที, ถ้า fail → Manual review |
| **API Timeout** | Retry with exponential backoff |
| **API Down** | Queue requests, alert admin |

---

## 10. Webhook Support

### Receiving Webhooks from Wholesaler

บาง Wholesaler ส่ง webhook มาแจ้งเราเมื่อมีการเปลี่ยนแปลง

### Webhook Endpoint

```php
// routes/api.php
Route::post('/webhooks/wholesaler/{secret_code}', [WebhookController::class, 'handle'])
    ->middleware('verify.webhook.signature');
```

### Webhook Events

| Event | Description | Action |
|-------|-------------|--------|
| `tour.created` | ทัวร์ใหม่ | Queue sync job |
| `tour.updated` | ทัวร์อัพเดท | Queue sync job |
| `tour.deleted` | ทัวร์ถูกลบ | Soft delete |
| `period.updated` | รอบเดินทางเปลี่ยน | Update period |
| `period.sold_out` | เต็ม | Update status |
| `booking.confirmed` | ยืนยันแล้ว | Update local booking |
| `booking.cancelled` | ถูกยกเลิก | Handle cancellation |
| `price.changed` | ราคาเปลี่ยน | Update prices |

### Webhook Handler

```php
class WebhookController extends Controller
{
    public function handle(Request $request, string $secretCode)
    {
        $wholesaler = Wholesaler::where('webhook_secret', $secretCode)->firstOrFail();
        
        $event = $request->input('event');
        $payload = $request->input('data');
        
        // Log webhook
        WebhookLog::create([
            'wholesaler_id' => $wholesaler->id,
            'event' => $event,
            'payload' => $payload,
        ]);
        
        // Dispatch handler
        match($event) {
            'tour.created', 'tour.updated' => dispatch(new ProcessTourWebhookJob($wholesaler, $payload)),
            'period.updated', 'period.sold_out' => dispatch(new ProcessPeriodWebhookJob($wholesaler, $payload)),
            'booking.confirmed' => dispatch(new HandleBookingConfirmedJob($payload)),
            'booking.cancelled' => dispatch(new HandleBookingCancelledJob($payload)),
            default => Log::warning("Unknown webhook event: {$event}"),
        };
        
        return response()->json(['received' => true]);
    }
}
```

### Webhook Signature Verification

```php
// Middleware: VerifyWebhookSignature
public function handle($request, Closure $next)
{
    $signature = $request->header('X-Webhook-Signature');
    $payload = $request->getContent();
    $secret = config('wholesalers.webhook_secret');
    
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
    
    return $next($request);
}
```

---

## 11. Database Schema

### wholesaler_api_configs

```sql
CREATE TABLE wholesaler_api_configs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- API Connection
    api_base_url VARCHAR(500) NOT NULL,
    api_version VARCHAR(20) DEFAULT 'v1',
    api_format ENUM('rest', 'soap', 'graphql') DEFAULT 'rest',
    
    -- Authentication
    auth_type ENUM('api_key', 'oauth2', 'basic', 'bearer', 'custom') NOT NULL,
    auth_credentials TEXT,  -- Encrypted JSON
    auth_header_name VARCHAR(100) DEFAULT 'Authorization',
    
    -- Rate Limiting
    rate_limit_per_minute INT DEFAULT 60,
    rate_limit_per_day INT DEFAULT 10000,
    
    -- Timeouts
    connect_timeout_seconds INT DEFAULT 10,
    request_timeout_seconds INT DEFAULT 30,
    retry_attempts INT DEFAULT 3,
    
    -- Sync Settings
    sync_enabled BOOLEAN DEFAULT TRUE,
    sync_method ENUM('cursor', 'ack_callback', 'last_modified') DEFAULT 'cursor',
    sync_schedule VARCHAR(100) DEFAULT '0 */2 * * *',  -- Every 2 hours
    full_sync_schedule VARCHAR(100) DEFAULT '0 3 * * *',  -- Daily 3 AM
    
    -- Webhook
    webhook_enabled BOOLEAN DEFAULT FALSE,
    webhook_secret VARCHAR(200),
    webhook_url VARCHAR(500),
    
    -- Features Support
    supports_availability_check BOOLEAN DEFAULT TRUE,
    supports_hold_booking BOOLEAN DEFAULT TRUE,
    supports_modify_booking BOOLEAN DEFAULT FALSE,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    last_health_check_at TIMESTAMP NULL,
    last_health_check_status BOOLEAN,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wholesaler (wholesaler_id)
);
```

### section_definitions

```sql
CREATE TABLE section_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    section_name VARCHAR(50) NOT NULL,  -- tour, period, pricing, content, media, seo
    field_name VARCHAR(100) NOT NULL,
    
    -- Data Type
    data_type ENUM(
        'TEXT', 'INT', 'DECIMAL', 'DATE', 'DATETIME', 
        'BOOLEAN', 'ENUM', 'ARRAY_TEXT', 'ARRAY_INT', 
        'ARRAY_DECIMAL', 'JSON'
    ) NOT NULL,
    enum_values JSON NULL,  -- For ENUM type: ["join", "incentive", "collective"]
    
    -- Validation
    is_required BOOLEAN DEFAULT FALSE,
    default_value VARCHAR(500),
    validation_rules VARCHAR(500),  -- Laravel validation rules
    
    -- Lookup
    lookup_table VARCHAR(100),  -- countries, cities, transports
    lookup_match_fields JSON,   -- ["name_en", "name_th", "iso2"]
    lookup_return_field VARCHAR(100) DEFAULT 'id',
    lookup_create_if_not_found BOOLEAN DEFAULT FALSE,
    
    -- Meta
    description VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_system BOOLEAN DEFAULT FALSE,  -- System fields can't be deleted
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_section_field (section_name, field_name)
);
```

### wholesaler_field_mappings

```sql
CREATE TABLE wholesaler_field_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- Section & Field
    section_name VARCHAR(50) NOT NULL,
    our_field VARCHAR(100) NOT NULL,
    
    -- Their Field (flexible)
    their_field VARCHAR(200),           -- Simple field name
    their_field_path VARCHAR(500),      -- JSON path: "data.tour.details.name"
    
    -- Transformation
    transform_type ENUM(
        'direct',       -- Copy as-is
        'value_map',    -- Map values
        'formula',      -- Calculate
        'split',        -- Split string
        'concat',       -- Concatenate
        'lookup',       -- Lookup from table
        'custom'        -- Custom function
    ) DEFAULT 'direct',
    transform_config JSON,
    
    -- Override
    default_value VARCHAR(500),
    is_required_override BOOLEAN,  -- Override section definition
    
    -- Meta
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mapping (wholesaler_id, section_name, our_field)
);
```

### sync_cursors

```sql
CREATE TABLE sync_cursors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    sync_type ENUM('tours', 'periods', 'prices', 'all') NOT NULL,
    cursor_value VARCHAR(500),
    cursor_type ENUM('string', 'timestamp', 'integer') DEFAULT 'string',
    
    -- Last Sync Info
    last_sync_id VARCHAR(100),
    last_synced_at TIMESTAMP,
    
    -- Stats
    total_received INT DEFAULT 0,
    last_batch_count INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cursor (wholesaler_id, sync_type)
);
```

### booking_holds

```sql
CREATE TABLE booking_holds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- References
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    tour_id BIGINT UNSIGNED NOT NULL,
    period_id BIGINT UNSIGNED,
    booking_id BIGINT UNSIGNED,  -- After booking created
    
    -- External Reference
    external_hold_id VARCHAR(200) NOT NULL,
    
    -- Hold Details
    travel_date DATE NOT NULL,
    pax_adult INT NOT NULL DEFAULT 1,
    pax_child INT NOT NULL DEFAULT 0,
    pax_infant INT NOT NULL DEFAULT 0,
    
    -- Pricing (at time of hold)
    price_adult DECIMAL(12,2) NOT NULL,
    price_child DECIMAL(12,2),
    price_infant DECIMAL(12,2),
    total_price DECIMAL(12,2) NOT NULL,
    currency CHAR(3) DEFAULT 'THB',
    
    -- TTL
    hold_expires_at TIMESTAMP NOT NULL,
    
    -- Status
    status ENUM('pending', 'held', 'confirmed', 'expired', 'released', 'failed') DEFAULT 'pending',
    
    -- Customer Session
    customer_session_id VARCHAR(200),
    customer_id BIGINT UNSIGNED,
    
    -- API Response
    request_data JSON,
    response_data JSON,
    error_message TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    held_at TIMESTAMP,
    confirmed_at TIMESTAMP,
    expired_at TIMESTAMP,
    released_at TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id),
    FOREIGN KEY (tour_id) REFERENCES tours(id),
    FOREIGN KEY (period_id) REFERENCES periods(id),
    
    INDEX idx_status_expires (status, hold_expires_at),
    INDEX idx_external_hold (external_hold_id),
    INDEX idx_customer_session (customer_session_id)
);
```

### booking_syncs

```sql
CREATE TABLE booking_syncs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- External References
    external_hold_id VARCHAR(200),
    external_booking_ref VARCHAR(200),
    external_confirmation_no VARCHAR(200),
    
    -- Sync Status
    sync_status ENUM('pending', 'synced', 'failed', 'cancelled') DEFAULT 'pending',
    
    -- Hold Status
    hold_status ENUM('none', 'pending', 'held', 'expired', 'released') DEFAULT 'none',
    hold_expires_at TIMESTAMP,
    
    -- Confirm Status
    confirm_status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    confirmed_at TIMESTAMP,
    
    -- Last Communication
    last_action ENUM('hold', 'confirm', 'cancel', 'modify', 'check_status'),
    last_action_at TIMESTAMP,
    last_action_success BOOLEAN,
    last_error_message TEXT,
    
    -- Retry
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 5,
    next_retry_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id),
    
    UNIQUE KEY unique_booking (booking_id),
    INDEX idx_sync_status (sync_status),
    INDEX idx_retry (sync_status, next_retry_at)
);
```

### outbound_api_logs

```sql
CREATE TABLE outbound_api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- Request
    action ENUM(
        'fetch_tours', 'fetch_detail', 'check_availability',
        'hold', 'confirm', 'cancel', 'modify', 'check_status',
        'ack_sync', 'health_check'
    ) NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    method ENUM('GET', 'POST', 'PUT', 'PATCH', 'DELETE') NOT NULL,
    request_headers JSON,
    request_body JSON,
    
    -- Response
    response_code INT,
    response_headers JSON,
    response_body JSON,
    response_time_ms INT,
    
    -- Context
    booking_hold_id BIGINT UNSIGNED,
    booking_id BIGINT UNSIGNED,
    tour_id BIGINT UNSIGNED,
    sync_log_id BIGINT UNSIGNED,
    
    -- Status
    status ENUM('success', 'failed', 'timeout', 'error') NOT NULL,
    error_type VARCHAR(50),
    error_message TEXT,
    
    -- Retry
    retry_of_id BIGINT UNSIGNED,  -- If this is a retry
    retry_count INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id),
    INDEX idx_action_status (action, status, created_at),
    INDEX idx_wholesaler_date (wholesaler_id, created_at)
);
```

### sync_logs

```sql
CREATE TABLE sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- Sync Info
    sync_type ENUM('full', 'incremental', 'webhook', 'manual') NOT NULL,
    sync_id VARCHAR(100) UNIQUE,  -- e.g., sync_20260127_143000
    
    -- Timing
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP,
    duration_seconds INT,
    
    -- Results
    status ENUM('running', 'completed', 'failed', 'partial') DEFAULT 'running',
    
    -- Tour Stats
    tours_received INT DEFAULT 0,
    tours_created INT DEFAULT 0,
    tours_updated INT DEFAULT 0,
    tours_skipped INT DEFAULT 0,
    tours_failed INT DEFAULT 0,
    
    -- Period Stats
    periods_received INT DEFAULT 0,
    periods_created INT DEFAULT 0,
    periods_updated INT DEFAULT 0,
    
    -- Errors
    error_count INT DEFAULT 0,
    error_summary JSON,
    
    -- ACK Status
    ack_sent BOOLEAN DEFAULT FALSE,
    ack_sent_at TIMESTAMP,
    ack_accepted BOOLEAN,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id) ON DELETE CASCADE,
    INDEX idx_wholesaler_date (wholesaler_id, started_at)
);
```

### sync_error_logs

```sql
CREATE TABLE sync_error_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_log_id BIGINT UNSIGNED NOT NULL,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- Error Context
    external_tour_code VARCHAR(200),
    tour_id BIGINT UNSIGNED,
    section_name VARCHAR(50),
    field_name VARCHAR(100),
    
    -- Error Details
    error_type ENUM('mapping', 'validation', 'lookup', 'type_cast', 'api', 'database', 'unknown') NOT NULL,
    error_message TEXT NOT NULL,
    
    -- Values
    received_value TEXT,
    expected_type VARCHAR(50),
    
    -- Debug
    raw_data JSON,
    stack_trace TEXT,
    
    -- Resolution
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP,
    resolved_by BIGINT UNSIGNED,
    resolution_notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sync_log_id) REFERENCES sync_logs(id) ON DELETE CASCADE,
    INDEX idx_unresolved (wholesaler_id, is_resolved, created_at)
);
```

### webhook_logs

```sql
CREATE TABLE webhook_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wholesaler_id BIGINT UNSIGNED NOT NULL,
    
    -- Webhook Info
    event VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    
    -- Headers
    signature VARCHAR(500),
    headers JSON,
    
    -- Processing
    status ENUM('received', 'processing', 'processed', 'failed') DEFAULT 'received',
    processed_at TIMESTAMP,
    
    -- Error
    error_message TEXT,
    retry_count INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (wholesaler_id) REFERENCES wholesalers(id),
    INDEX idx_event_status (event, status),
    INDEX idx_wholesaler_date (wholesaler_id, created_at)
);
```

---

## 12. Admin UI

### Navigation Structure

```
Dashboard/
├── Wholesaler Integrations/
│   │
│   ├── Overview Dashboard
│   │   ├── Active Integrations (count, health)
│   │   ├── Recent Syncs (last 24h)
│   │   ├── Error Summary
│   │   └── Pending Bookings
│   │
│   ├── Integrations List
│   │   └── [Each Wholesaler]
│   │       ├── Status (🟢 Active / 🔴 Error / 🟡 Paused)
│   │       ├── Last Sync
│   │       ├── Tours Count
│   │       └── Actions (Edit, Sync Now, View Logs)
│   │
│   ├── Add New Integration
│   │   ├── Step 1: Wholesaler Info
│   │   ├── Step 2: API Credentials
│   │   ├── Step 3: Test Connection
│   │   ├── Step 4: Section Mapping
│   │   └── Step 5: Test Sync (5 tours)
│   │
│   ├── [Wholesaler] Detail/
│   │   ├── Overview
│   │   │   ├── Health Status
│   │   │   ├── Sync Stats (chart)
│   │   │   └── Recent Errors
│   │   │
│   │   ├── API Configuration
│   │   │   ├── Base URL, Auth
│   │   │   ├── Rate Limits
│   │   │   └── Features Toggle
│   │   │
│   │   ├── Section Mappings
│   │   │   ├── Visual Mapper (drag & drop)
│   │   │   ├── [tour] Fields
│   │   │   ├── [period] Fields
│   │   │   ├── [pricing] Fields
│   │   │   ├── [content] Fields
│   │   │   └── [media] Fields
│   │   │
│   │   ├── Sample Data Preview
│   │   │   ├── Raw API Response
│   │   │   ├── Transformed Data
│   │   │   └── Validation Warnings
│   │   │
│   │   ├── Sync Settings
│   │   │   ├── Schedule
│   │   │   ├── ACK Method
│   │   │   └── Webhook Config
│   │   │
│   │   ├── Sync History
│   │   │   └── [Each Sync]
│   │   │       ├── Stats
│   │   │       ├── Errors
│   │   │       └── Tours List
│   │   │
│   │   └── API Logs
│   │       └── Outbound calls log
│   │
│   ├── Pending Tours
│   │   └── Tours awaiting approval
│   │       ├── Preview
│   │       ├── Approve
│   │       └── Reject
│   │
│   └── Error Dashboard
│       ├── Unresolved Errors
│       ├── By Wholesaler
│       ├── By Error Type
│       └── Resolution Tools
```

### Key UI Features

1. **Visual Section Mapper**
   - Left: API Response fields (tree view)
   - Right: Our sections with fields
   - Drag & drop to create mapping
   - Auto-detect data types

2. **Live Preview Panel**
   - Input: Sample API response
   - Output: Transformed tour data
   - Highlight: Validation errors, missing required

3. **Test Sync**
   - Sync 5-10 tours as preview
   - Show what will be created/updated
   - Confirm before full sync

4. **Error Resolution**
   - Group errors by type
   - Bulk resolve similar errors
   - Add mapping rules from errors

---

## 13. Implementation Plan

### Phase 1: Foundation (Week 1-2)

| Task | Description | Days |
|------|-------------|------|
| Database Migrations | All tables above | 2 |
| Models & Relationships | Eloquent models | 2 |
| Section Definitions Seeder | Seed default fields | 1 |
| AdapterInterface | Contract + DTOs | 1 |
| BaseAdapter | Auth, retry, logging | 2 |
| TypeValidator | Data type validation | 2 |

### Phase 2: Mapping Engine (Week 3)

| Task | Description | Days |
|------|-------------|------|
| SectionMapper | Core mapping logic | 2 |
| Transform Functions | All transform types | 2 |
| LookupResolver | Country, city, transport lookup | 1 |

### Phase 3: First Wholesaler (Week 4)

| Task | Description | Days |
|------|-------------|------|
| Adapter Implementation | First real wholesaler | 3 |
| Integration Testing | Real API tests | 2 |

### Phase 4: Sync System (Week 5)

| Task | Description | Days |
|------|-------------|------|
| SyncToursJob | Main sync job | 1 |
| ACK Implementation | Cursor or callback | 1 |
| Scheduler | Cron schedules | 0.5 |
| Sync Logging | Full logging | 1.5 |
| Error Handling | Categorize, retry | 1 |

### Phase 5: Booking Outbound (Week 6-7)

| Task | Description | Days |
|------|-------------|------|
| Availability Check | Real-time check | 2 |
| Hold Booking | Hold with TTL | 2 |
| Confirm Booking | Confirm flow | 2 |
| Cancel/Modify | Cancel and modify | 2 |
| BookingSync Tracking | Track sync status | 1 |
| Auto-Expire Job | Handle expired holds | 1 |

### Phase 6: Webhooks (Week 8)

| Task | Description | Days |
|------|-------------|------|
| Webhook Endpoint | Receive webhooks | 1 |
| Signature Verification | Security | 1 |
| Event Handlers | Process events | 2 |
| Webhook Logs | Logging | 1 |

### Phase 7: Admin UI (Week 9-10)

| Task | Description | Days |
|------|-------------|------|
| Integration List Page | List + status | 2 |
| Add Integration Wizard | Step by step | 3 |
| Visual Section Mapper | Drag & drop | 3 |
| Preview Panel | Live preview | 1 |
| Sync History | View logs | 1 |

### Phase 8: Polish (Week 11)

| Task | Description | Days |
|------|-------------|------|
| Error Dashboard | Error management | 2 |
| Performance Tuning | Optimize | 1 |
| Testing | E2E tests | 2 |

---

## 📝 Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2026-01-27 | 2.0 | Complete rewrite with Section-based mapping, 2-way sync, Booking flow |
| 2026-01-27 | 1.0 | Initial design document |

---

## ❓ Questions Before Starting

1. **First Wholesaler** - มี API documentation ไหม?
2. **API Format** - REST / SOAP / GraphQL?
3. **ACK Method** - Wholesaler รองรับ cursor หรือ callback?
4. **Booking Flow** - ต้องการ real-time availability หรือ cached?
5. **Auto-publish** - ทัวร์ที่ sync มา publish เลยหรือ review ก่อน?

---

## 👥 Contributors

- System Design: NextTrip Development Team
