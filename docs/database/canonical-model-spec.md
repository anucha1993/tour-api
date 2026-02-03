# Canonical Model Database Specification

> อ้างอิงจาก: `wholesale-contract-v1-tour-bundle.md`
> 
> วันที่: 26 มกราคม 2569

---

## สถานะการดำเนินการ

| Table | Migration | Seeder | Model | Controller | API | UI (Next.js) | Status |
|-------|-----------|--------|-------|------------|-----|--------------|--------|
| countries | ✅ | ✅ (202 ประเทศ) | ✅ | ✅ | ✅ | ✅ CRUD + Flag | ✅ เสร็จสมบูรณ์ |
| transports | ✅ | ✅ (109 รายการ) | ✅ | ✅ | ✅ | ✅ CRUD + Image | ✅ เสร็จสมบูรณ์ |
| cities | ✅ | ✅ (5,071 เมือง) | ✅ | ✅ | ✅ | ✅ CRUD + Grouped by Country | ✅ เสร็จสมบูรณ์ |
| airports | ✅ | ⏳ | ⏳ | ⏳ | ⏳ | ⏳ | รอดำเนินการ |
| tours | ✅ | - | ⏳ | ⏳ | ⏳ | ⏳ | รอดำเนินการ |
| departures | ✅ | - | ⏳ | ⏳ | ⏳ | ⏳ | รอดำเนินการ |
| offers | ✅ | - | ⏳ | ⏳ | ⏳ | ⏳ | รอดำเนินการ |

### รายละเอียดที่ดำเนินการแล้ว

#### Countries (ประเทศ) ✅
- **Model:** `app/Models/Country.php` - REGIONS constants, scopes (active, inRegion)
- **Controller:** `app/Http/Controllers/CountryController.php` - Full CRUD + toggleStatus + regions
- **Routes:** `routes/api.php` - RESTful endpoints
- **UI:** 
  - List: `/dashboard/countries` - Card grid layout with flags from flagcdn.com
  - Create: `/dashboard/countries/create`
  - Edit: `/dashboard/countries/[id]`
- **Features:** ค้นหา, กรองตาม region, กรองตาม status, toggle active/inactive

#### Transports (ขนส่ง) ✅
- **Model:** `app/Models/Transport.php` - TYPE constants, scopes
- **Controller:** `app/Http/Controllers/TransportController.php` - Full CRUD + toggleStatus + Cloudflare Images
- **Routes:** `routes/api.php` - RESTful endpoints
- **UI:**
  - List: `/dashboard/transports` - Card grid layout with logos
  - Create: `/dashboard/transports/create`
  - Edit: `/dashboard/transports/[id]`
- **Features:** ค้นหา, กรองตาม type, toggle status, อัพโหลดรูปไป Cloudflare Images (WebP)
- **Cloudflare Images:** Account Hash `yixdo-GXTcyjkoSkBzfBcA`, 109 รูปอัพโหลดแล้ว

#### Cities (เมือง) ✅
- **Model:** `app/Models/City.php` - scopes (active, popular, inCountry), relationships (belongsTo Country, hasMany Airport)
- **Controller:** `app/Http/Controllers/CityController.php` - Full CRUD + toggleStatus + togglePopular + countriesWithCities
- **Routes:** `routes/api.php` - RESTful endpoints
- **UI:**
  - List: `/dashboard/cities` - Countries grouped by region, click to view cities
  - Cities by Country: `/dashboard/cities/country/[countryId]` - Table layout with all cities in country
  - Create: `/dashboard/cities/create`
  - Edit: `/dashboard/cities/[id]`
- **Features:** ค้นหา, กรองตาม popular/status, toggle popular, toggle active/inactive
- **Import Command:** `php artisan cities:import-legacy` - Import 5,071 เมืองจากฐานข้อมูลเดิม (tb_city.sql)
- **Data:** 5,071 เมือง จาก 195 ประเทศ

---

## ภาพรวมโครงสร้างข้อมูล

ข้อมูลแบ่งเป็น 3 ชั้นหลัก:
1. **Tour** - ข้อมูลทัวร์หลัก (เปลี่ยนไม่บ่อย)
2. **Departure** - รอบเดินทาง (เปลี่ยนบ่อย)
3. **Offer** - ราคา/โปรโมชัน (เปลี่ยนบ่อยที่สุด)

---

## 1. Master Tables (ตารางอ้างอิง)

### 1.1 countries - ประเทศ
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | รหัสหลัก |
| iso2 | VARCHAR(2) | UNIQUE, NOT NULL | ISO 3166-1 alpha-2 (TH, JP, CN) |
| iso3 | VARCHAR(3) | UNIQUE, NOT NULL | ISO 3166-1 alpha-3 (THA, JPN, CHN) |
| name_en | VARCHAR(100) | NOT NULL | ชื่อภาษาอังกฤษ |
| name_th | VARCHAR(100) | | ชื่อภาษาไทย |
| slug | VARCHAR(100) | UNIQUE, NOT NULL | URL slug (thailand, japan) |
| region | VARCHAR(50) | | ภูมิภาค (Asia, Europe, etc.) |
| flag_emoji | VARCHAR(10) | | Emoji ธงชาติ (🇹🇭 🇯🇵) |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**หมายเหตุ Flag:**
- ใช้ `iso2` ร่วมกับ CSS library `flag-icons` สำหรับแสดงธง: `<span class="fi fi-th"></span>`
- ใช้ `flag_emoji` สำหรับแสดงในที่จำกัด หรือ fallback

### 1.2 transports - ผู้ให้บริการขนส่ง
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| code | VARCHAR(100) | | IATA code 2 ตัว (TG, AQ, FD) |
| code1 | VARCHAR(100) | | ICAO code 3 ตัว (THA, ANK, AFR) |
| name | VARCHAR(250) | | ชื่อผู้ให้บริการ |
| type | ENUM | 'airline','bus','boat','train','van','other' DEFAULT 'airline' | ประเภทยานพาหนะ |
| image | VARCHAR(255) | | รูปโลโก้ |
| status | ENUM | 'on','off' DEFAULT 'on' | สถานะ |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |
| deleted_at | TIMESTAMP | | Soft delete |

**ประเภทยานพาหนะ (type):**
| Value | Description |
|-------|-------------|
| airline | สายการบิน ✈️ |
| bus | รถบัส 🚌 |
| boat | เรือ ⛴️ |
| train | รถไฟ 🚄 |
| van | รถตู้ 🚐 |
| other | อื่นๆ |

> **หมายเหตุ:** โครงสร้างอ้างอิงจาก `tb_travel_type` ในฐานข้อมูลเดิม + เพิ่ม field `type`

### 1.3 cities - เมือง
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | รหัสหลัก |
| name_en | VARCHAR(150) | NOT NULL | ชื่อภาษาอังกฤษ |
| name_th | VARCHAR(150) | | ชื่อภาษาไทย |
| slug | VARCHAR(150) | UNIQUE, NOT NULL | URL slug |
| country_id | BIGINT | FK → countries, NOT NULL | ประเทศ |
| description | TEXT | | รายละเอียด |
| is_popular | BOOLEAN | DEFAULT false | เมืองยอดนิยม |
| is_active | BOOLEAN | DEFAULT true | สถานะใช้งาน |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Indexes:**
- `INDEX (country_id)`
- `INDEX (is_popular)`
- `INDEX (is_active)`

**หมายเหตุ:** ลบ columns `code`, `timezone`, `image` ออกแล้ว (ไม่จำเป็นต้องใช้)

### 1.4 airports - สนามบิน
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| code | VARCHAR(4) | UNIQUE, NOT NULL | IATA code (BKK, DMK, HKG) |
| name_en | VARCHAR(150) | NOT NULL | |
| name_th | VARCHAR(150) | | |
| city_en | VARCHAR(100) | | |
| city_th | VARCHAR(100) | | |
| country_id | BIGINT | FK → countries | |
| timezone | VARCHAR(50) | | Asia/Bangkok |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

---

## 2. Tours (ทัวร์)

### 2.1 tours - ข้อมูลทัวร์หลัก
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| wholesaler_id | BIGINT | FK → wholesalers, NOT NULL | |
| external_id | VARCHAR(50) | NOT NULL | รหัสจาก Wholesale |
| tour_code | VARCHAR(50) | NOT NULL | รหัสทัวร์ในระบบเรา |
| title | VARCHAR(255) | NOT NULL | ชื่อทัวร์ |
| country_id | BIGINT | FK → countries | ประเทศหลัก |
| duration_days | TINYINT | NOT NULL | จำนวนวัน |
| duration_nights | TINYINT | NOT NULL | จำนวนคืน |
| highlights | TEXT | | ไฮไลต์ |
| slug | VARCHAR(255) | UNIQUE | URL slug สำหรับ SEO |
| meta_title | VARCHAR(200) | | SEO meta title |
| meta_description | VARCHAR(300) | | SEO meta description |
| keywords | JSON | | SEO keywords array |
| cover_image_url | VARCHAR(500) | | รูปปกหลัก |
| cover_image_alt | VARCHAR(255) | | alt text รูปปก |
| pdf_url | VARCHAR(500) | | เอกสาร PDF |
| docx_url | VARCHAR(500) | | เอกสาร Word |
| status | ENUM | 'draft','active','inactive' | สถานะ |
| is_published | BOOLEAN | DEFAULT false | แสดงหน้าเว็บหรือไม่ |
| published_at | TIMESTAMP | | วันที่เผยแพร่ |
| updated_at_source | TIMESTAMP | | เวลาอัปเดตจาก Wholesale |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Indexes:**
- `UNIQUE (wholesaler_id, external_id)`
- `INDEX (country_id)`
- `INDEX (status, is_published)`
- `INDEX (slug)`

### 2.2 tour_locations - สถานที่/เมืองที่ไป
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| tour_id | BIGINT | FK → tours, NOT NULL | |
| name | VARCHAR(100) | NOT NULL | ชื่อสถานที่/เมือง |
| sort_order | TINYINT | DEFAULT 0 | ลำดับ |
| created_at | TIMESTAMP | | |

### 2.3 tour_gallery - รูปภาพแกลเลอรี่
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| tour_id | BIGINT | FK → tours, NOT NULL | |
| url | VARCHAR(500) | NOT NULL | URL รูป |
| alt | VARCHAR(255) | | alt text |
| sort_order | TINYINT | DEFAULT 0 | ลำดับ |
| created_at | TIMESTAMP | | |

### 2.4 tour_transports - ยานพาหนะ
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| tour_id | BIGINT | FK → tours, NOT NULL | |
| transport_id | BIGINT | FK → transports | |
| flight_no | VARCHAR(10) | | หมายเลขเที่ยวบิน/รหัสเที่ยว |
| route_from | VARCHAR(100) | | ต้นทาง |
| route_to | VARCHAR(100) | | ปลายทาง |
| depart_time | TIME | | เวลาออก |
| arrive_time | TIME | | เวลาถึง |
| transport_type | ENUM | 'outbound','inbound' | ขาไป/ขากลับ |
| sort_order | TINYINT | DEFAULT 0 | ลำดับ |
| created_at | TIMESTAMP | | |

### 2.5 tour_itineraries - โปรแกรมรายวัน
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| tour_id | BIGINT | FK → tours, NOT NULL | |
| day_no | TINYINT | NOT NULL | วันที่ |
| title | VARCHAR(255) | | หัวข้อวัน (ถ้ามี) |
| description | TEXT | NOT NULL | รายละเอียด |
| hotel_name | VARCHAR(150) | | ชื่อโรงแรม |
| hotel_star | TINYINT | | ระดับดาว |
| meal_breakfast | BOOLEAN | DEFAULT false | มีอาหารเช้า |
| meal_lunch | BOOLEAN | DEFAULT false | มีอาหารกลางวัน |
| meal_dinner | BOOLEAN | DEFAULT false | มีอาหารเย็น |
| created_at | TIMESTAMP | | |

---

## 3. Departures (รอบเดินทาง)

### 3.1 departures - รอบเดินทาง
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| tour_id | BIGINT | FK → tours, NOT NULL | |
| external_id | VARCHAR(50) | NOT NULL | รหัสจาก Wholesale |
| departure_code | VARCHAR(50) | NOT NULL | รหัสรอบ |
| start_date | DATE | NOT NULL | วันเดินทางไป |
| end_date | DATE | NOT NULL | วันกลับ |
| capacity | SMALLINT | NOT NULL DEFAULT 0 | ที่นั่งทั้งหมด |
| booked | SMALLINT | NOT NULL DEFAULT 0 | จองแล้ว |
| available | SMALLINT | NOT NULL DEFAULT 0 | คงเหลือ |
| status | ENUM | 'open','closed','sold_out','cancelled' | สถานะ |
| updated_at_source | TIMESTAMP | | เวลาอัปเดตจาก Wholesale |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Indexes:**
- `UNIQUE (tour_id, external_id)`
- `INDEX (start_date)`
- `INDEX (status)`

---

## 4. Offers (ราคา/โปรโมชัน)

### 4.1 offers - ข้อเสนอราคา
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| departure_id | BIGINT | FK → departures, UNIQUE, NOT NULL | 1 departure = 1 offer |
| currency | VARCHAR(3) | DEFAULT 'THB' | สกุลเงิน |
| price_adult | DECIMAL(10,2) | NOT NULL | ราคาผู้ใหญ่ |
| price_child | DECIMAL(10,2) | | ราคาเด็ก |
| price_child_nobed | DECIMAL(10,2) | | เด็กไม่มีเตียง |
| price_infant | DECIMAL(10,2) | | ทารก |
| price_joinland | DECIMAL(10,2) | | ไม่รวมตั๋ว |
| price_single | DECIMAL(10,2) | | พักเดี่ยวเพิ่ม |
| deposit | DECIMAL(10,2) | | มัดจำ |
| commission_agent | DECIMAL(10,2) | | ค่าคอมตัวแทน |
| commission_sale | DECIMAL(10,2) | | ค่าคอมขาย |
| cancellation_policy | TEXT | NOT NULL | เงื่อนไขยกเลิก |
| refund_policy | TEXT | | เงื่อนไขคืนเงิน |
| notes | TEXT | | หมายเหตุ |
| ttl_minutes | SMALLINT | DEFAULT 10 | อายุข้อมูลราคา (นาที) |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### 4.2 offer_promotions - โปรโมชัน
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| offer_id | BIGINT | FK → offers, NOT NULL | |
| promo_code | VARCHAR(50) | | รหัสโปรโมชัน |
| name | VARCHAR(255) | NOT NULL | ชื่อโปรโมชัน |
| type | ENUM | 'discount_amount','discount_percent','freebie' | ประเภท |
| value | DECIMAL(10,2) | | มูลค่า (จำนวนเงิน หรือ %) |
| apply_to | ENUM | 'per_pax','per_booking' | คิดต่อคน/ต่อ booking |
| start_at | TIMESTAMP | | วันเริ่มโปร |
| end_at | TIMESTAMP | | วันสิ้นสุด |
| conditions | JSON | | เงื่อนไขเพิ่มเติม (min_pax, booking_before_days) |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | | |

---

## 5. Sync & Logging

### 5.1 sync_batches - ประวัติ batch sync
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| request_id | VARCHAR(100) | UNIQUE, NOT NULL | Idempotency key |
| wholesaler_id | BIGINT | FK → wholesalers, NOT NULL | |
| mode | ENUM | 'delta','full' | โหมดการ sync |
| status | ENUM | 'pending','processing','completed','partial','failed' | สถานะ |
| total_items | INT | DEFAULT 0 | จำนวนรายการทั้งหมด |
| success_count | INT | DEFAULT 0 | สำเร็จ |
| failed_count | INT | DEFAULT 0 | ล้มเหลว |
| skipped_count | INT | DEFAULT 0 | ข้าม (ข้อมูลเก่า) |
| error_message | TEXT | | ข้อผิดพลาดหลัก |
| sent_at | TIMESTAMP | | เวลาที่ partner ส่ง |
| processed_at | TIMESTAMP | | เวลาที่ประมวลผลเสร็จ |
| created_at | TIMESTAMP | | |

### 5.2 sync_batch_items - รายละเอียดแต่ละ item
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| sync_batch_id | BIGINT | FK → sync_batches, NOT NULL | |
| entity_type | ENUM | 'tour','departure' | ประเภท |
| external_id | VARCHAR(50) | NOT NULL | รหัสจาก Wholesale |
| result | ENUM | 'created','updated','skipped','error' | ผลลัพธ์ |
| error_code | VARCHAR(10) | | รหัส error (E001, E002, ...) |
| error_message | TEXT | | รายละเอียด error |
| created_at | TIMESTAMP | | |

### 5.3 price_history - ประวัติราคา (Audit)
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| offer_id | BIGINT | FK → offers, NOT NULL | |
| price_adult_old | DECIMAL(10,2) | | ราคาเดิม |
| price_adult_new | DECIMAL(10,2) | | ราคาใหม่ |
| changed_by | VARCHAR(50) | | sync / admin / api |
| changed_at | TIMESTAMP | | |

---

## 6. Security Tables

### 6.1 partner_api_keys - API Credentials
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| wholesaler_id | BIGINT | FK → wholesalers, NOT NULL | |
| api_key | VARCHAR(64) | UNIQUE, NOT NULL | Public key |
| api_secret | VARCHAR(128) | NOT NULL | Secret สำหรับ signature |
| name | VARCHAR(100) | | ชื่อ key (Production, Test) |
| is_active | BOOLEAN | DEFAULT true | |
| last_used_at | TIMESTAMP | | |
| expires_at | TIMESTAMP | | วันหมดอายุ (ถ้ามี) |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### 6.2 partner_ip_whitelist - IP ที่อนุญาต
| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | |
| wholesaler_id | BIGINT | FK → wholesalers, NOT NULL | |
| ip_address | VARCHAR(45) | NOT NULL | IPv4/IPv6 |
| description | VARCHAR(100) | | คำอธิบาย |
| is_active | BOOLEAN | DEFAULT true | |
| created_at | TIMESTAMP | | |

---

## ER Diagram (Text)

```
wholesalers ─────┬───────────────────────────────────────┐
                 │                                       │
                 ▼                                       ▼
              tours ◄──────────────────────────── partner_api_keys
                 │                                       
                 ├─► tour_locations                     
                 ├─► tour_gallery                       
                 ├─► tour_transports ◄──── transports   
                 ├─► tour_itineraries                   
                 │                                       
                 ▼                                       
           departures                                   
                 │                                       
                 ▼                                       
              offers ◄──── offer_promotions             
                 │                                       
                 ▼                                       
          price_history                                 

countries ◄──── airports
          ◄──── tours

sync_batches ─► sync_batch_items
```

---

## Notes

1. **Soft Delete**: ใช้ `is_active = false` แทนการลบจริง
2. **Timestamps**: ทุกตารางมี `created_at`, `updated_at`
3. **External IDs**: ใช้ composite unique `(wholesaler_id, external_id)` เพื่อป้องกัน ID ซ้ำ
4. **TTL**: ระบบควร cache ตาม `ttl_minutes` และ recheck ก่อน booking
5. **Audit**: เก็บ `price_history` สำหรับตรวจสอบย้อนหลัง
