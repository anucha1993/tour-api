# Cities API Documentation

> เมือง (Cities) - Master data for cities/destinations

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/cities` | รายการเมืองทั้งหมด (พร้อม pagination) |
| GET | `/api/cities/countries` | Dropdown ประเทศ |
| GET | `/api/cities/{id}` | ดึงข้อมูลเมืองตาม ID |
| POST | `/api/cities` | สร้างเมืองใหม่ |
| PUT | `/api/cities/{id}` | อัพเดทข้อมูลเมือง |
| DELETE | `/api/cities/{id}` | ลบเมือง |
| PATCH | `/api/cities/{id}/toggle-status` | Toggle สถานะ active/inactive |
| PATCH | `/api/cities/{id}/toggle-popular` | Toggle เมืองยอดนิยม |

---

## Endpoints Detail

### GET /api/cities

ดึงรายการเมืองทั้งหมด (พร้อม pagination และ filters)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| search | string | ค้นหาด้วยชื่อ/code |
| country_id | integer | กรองตามประเทศ |
| is_popular | boolean | กรองเมืองยอดนิยม |
| is_active | boolean | กรองตามสถานะ |
| page | integer | หน้าที่ต้องการ |
| per_page | integer | จำนวนรายการต่อหน้า (default: 15) |

**Response:**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "code": "BKK",
      "name_en": "Bangkok",
      "name_th": "กรุงเทพมหานคร",
      "slug": "bangkok",
      "country_id": 8,
      "timezone": "Asia/Bangkok",
      "image": null,
      "description": null,
      "is_popular": true,
      "is_active": true,
      "created_at": "2026-01-26T10:00:00.000000Z",
      "updated_at": "2026-01-26T10:00:00.000000Z",
      "country": {
        "id": 8,
        "iso2": "TH",
        "name_en": "Thailand",
        "name_th": "ไทย"
      }
    }
  ],
  "last_page": 24,
  "per_page": 15,
  "total": 353
}
```

---

### GET /api/cities/countries

ดึงรายการประเทศสำหรับ dropdown

**Response:**
```json
[
  {
    "id": 1,
    "iso2": "JP",
    "name_en": "Japan",
    "name_th": "ญี่ปุ่น"
  },
  {
    "id": 2,
    "iso2": "KR",
    "name_en": "South Korea",
    "name_th": "เกาหลีใต้"
  }
]
```

---

### GET /api/cities/{id}

ดึงข้อมูลเมืองตาม ID

**Response:**
```json
{
  "id": 1,
  "code": "BKK",
  "name_en": "Bangkok",
  "name_th": "กรุงเทพมหานคร",
  "slug": "bangkok",
  "country_id": 8,
  "timezone": "Asia/Bangkok",
  "image": null,
  "description": null,
  "is_popular": true,
  "is_active": true,
  "created_at": "2026-01-26T10:00:00.000000Z",
  "updated_at": "2026-01-26T10:00:00.000000Z",
  "country": {
    "id": 8,
    "iso2": "TH",
    "name_en": "Thailand",
    "name_th": "ไทย"
  }
}
```

---

### POST /api/cities

สร้างเมืองใหม่

**Request Body:**
```json
{
  "code": "CNX",
  "name_en": "Chiang Mai",
  "name_th": "เชียงใหม่",
  "country_id": 8,
  "timezone": "Asia/Bangkok",
  "description": "เมืองเชียงใหม่ ทางภาคเหนือของประเทศไทย",
  "is_popular": true,
  "is_active": true
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| code | nullable, max:10, unique:cities |
| name_en | required, max:150 |
| name_th | nullable, max:150 |
| country_id | required, exists:countries,id |
| timezone | nullable, max:50 |
| image | nullable, max:255 |
| description | nullable |
| is_popular | boolean |
| is_active | boolean |

**Note:** slug จะถูกสร้างอัตโนมัติจาก name_en

**Response:** 201 Created
```json
{
  "id": 354,
  "code": "CNX",
  "name_en": "Chiang Mai",
  "name_th": "เชียงใหม่",
  "slug": "chiang-mai",
  "country_id": 8,
  "timezone": "Asia/Bangkok",
  "is_popular": true,
  "is_active": true,
  "created_at": "2026-01-26T12:00:00.000000Z",
  "updated_at": "2026-01-26T12:00:00.000000Z"
}
```

---

### PUT /api/cities/{id}

อัพเดทข้อมูลเมือง

**Request Body:**
```json
{
  "code": "CNX",
  "name_en": "Chiang Mai",
  "name_th": "เชียงใหม่",
  "country_id": 8,
  "timezone": "Asia/Bangkok",
  "description": "Updated description",
  "is_popular": true,
  "is_active": true
}
```

**Response:** 200 OK
```json
{
  "id": 354,
  "code": "CNX",
  "name_en": "Chiang Mai",
  "name_th": "เชียงใหม่",
  "slug": "chiang-mai",
  "country_id": 8,
  "timezone": "Asia/Bangkok",
  "description": "Updated description",
  "is_popular": true,
  "is_active": true,
  "updated_at": "2026-01-26T12:30:00.000000Z"
}
```

---

### DELETE /api/cities/{id}

ลบเมือง

**Response:** 204 No Content

---

### PATCH /api/cities/{id}/toggle-status

Toggle สถานะ active/inactive

**Response:**
```json
{
  "id": 1,
  "is_active": false,
  "message": "City status updated"
}
```

---

### PATCH /api/cities/{id}/toggle-popular

Toggle เมืองยอดนิยม

**Response:**
```json
{
  "id": 1,
  "is_popular": true,
  "message": "City popular status updated"
}
```

---

## Data Model

### City Entity
| Field | Type | Description |
|-------|------|-------------|
| id | integer | รหัสหลัก |
| code | string | รหัสเมือง (BKK, TYO) |
| name_en | string | ชื่อภาษาอังกฤษ |
| name_th | string | ชื่อภาษาไทย |
| slug | string | URL slug |
| country_id | integer | FK ไปยัง countries |
| timezone | string | เขตเวลา (Asia/Bangkok) |
| image | string | URL รูปเมือง |
| description | text | รายละเอียด |
| is_popular | boolean | เมืองยอดนิยม |
| is_active | boolean | สถานะใช้งาน |

### Relationships
- **Country** (belongsTo): ประเทศของเมือง
- **Airports** (hasMany): สนามบินในเมือง

---

## UI Locations

| Page | Path | Description |
|------|------|-------------|
| รายการเมือง | `/dashboard/cities` | Card grid + flags + popular star |
| เพิ่มเมือง | `/dashboard/cities/create` | Form with country dropdown |
| แก้ไขเมือง | `/dashboard/cities/[id]` | Edit form |

---

## Seeder Data

**CitySeeder** seeds 353 cities across 50+ countries:
- Thailand (TH): Bangkok, Pattaya, Phuket, Chiang Mai, etc.
- Japan (JP): Tokyo, Osaka, Kyoto, Hokkaido, etc.
- South Korea (KR): Seoul, Busan, Jeju, etc.
- And many more...

**Run seeder:**
```bash
php artisan db:seed --class=CitySeeder
```
