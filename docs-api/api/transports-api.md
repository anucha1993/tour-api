# Transports API

> วันที่อัพเดท: 26 มกราคม 2569

## Base URL
```
/api/transports
```

## Authentication
ต้องมี Bearer Token ใน header ทุก request

```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. List Transports
```http
GET /api/transports
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| search | string | ค้นหาจาก code, code1, name |
| type | string | กรองตาม type (airline, bus, etc.) |
| status | string | กรองตาม status (on/off) |
| page | integer | หน้าที่ต้องการ |
| per_page | integer | จำนวนต่อหน้า (default: 50) |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "TG",
      "code1": "THA",
      "name": "Thai Airways",
      "type": "airline",
      "image": "https://imagedelivery.net/yixdo-GXTcyjkoSkBzfBcA/xxxxx/public",
      "status": "on",
      "created_at": "2026-01-25T00:00:00.000000Z",
      "updated_at": "2026-01-25T00:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 109
  }
}
```

**Sorting:**
- Active transports first (`status = 'on'`)
- Transports with images first
- Then by name

---

### 2. Get Transport
```http
GET /api/transports/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "TG",
    "code1": "THA",
    "name": "Thai Airways",
    "type": "airline",
    "image": "https://imagedelivery.net/...",
    "status": "on"
  }
}
```

---

### 3. Create Transport
```http
POST /api/transports
Content-Type: multipart/form-data
```

**Form Data:**
| Field | Type | Description |
|-------|------|-------------|
| code | string | IATA code (2 ตัว) |
| code1 | string | ICAO code (3 ตัว) |
| name | string | ชื่อผู้ให้บริการ |
| type | string | ประเภท |
| image | file | รูปโลโก้ (optional) |
| status | string | on/off |

**Image Upload:**
- รูปจะถูกอัพโหลดไป Cloudflare Images อัตโนมัติ
- รองรับ format: jpg, jpeg, png, gif, webp
- ขนาดสูงสุด: 10MB
- จะถูกแปลงเป็น WebP อัตโนมัติ

---

### 4. Update Transport
```http
POST /api/transports/{id}
Content-Type: multipart/form-data
```

**Note:** ใช้ POST method พร้อม `_method=PUT` เพราะต้องรองรับ file upload

**Form Data:** Same as Create

---

### 5. Delete Transport
```http
DELETE /api/transports/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Transport deleted successfully"
}
```

**Note:** ใช้ Soft Delete - ข้อมูลยังอยู่ใน database แต่จะไม่แสดง

---

### 6. Toggle Status
```http
PATCH /api/transports/{id}/toggle-status
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "off"
  },
  "message": "Status updated successfully"
}
```

---

## Transport Types

| Value | Description | Icon |
|-------|-------------|------|
| airline | สายการบิน | ✈️ |
| bus | รถบัส | 🚌 |
| boat | เรือ | ⛴️ |
| train | รถไฟ | 🚄 |
| van | รถตู้ | 🚐 |
| other | อื่นๆ | 🚗 |

---

## Cloudflare Images Integration

### Configuration
```env
CLOUDFLARE_IMAGES_ACCOUNT_ID=xxxxxxxx
CLOUDFLARE_IMAGES_API_TOKEN=xxxxxxxx
CLOUDFLARE_IMAGES_ACCOUNT_HASH=yixdo-GXTcyjkoSkBzfBcA
```

### Image URL Format
```
https://imagedelivery.net/{account_hash}/{image_id}/{variant}
```

**Variants:**
- `public` - Original size
- `thumbnail` - Thumbnail size

### Upload Flow
1. Frontend ส่ง file ไป Laravel API
2. Laravel อัพโหลดไป Cloudflare Images API
3. Cloudflare ส่ง image_id กลับมา
4. Laravel เก็บ full URL ใน database

---

## UI Implementation

### Pages
- List: `/dashboard/transports` - Card grid layout
- Create: `/dashboard/transports/create`
- Edit: `/dashboard/transports/[id]`

### Card Layout
แสดงเป็น horizontal card:
- รูปโลโก้ด้านซ้าย
- ข้อมูล (ชื่อ, code, type) ตรงกลาง
- ปุ่ม action ด้านขวา (แสดงเมื่อ hover)

### Features
- ค้นหาตาม code, name
- กรองตาม type
- Toggle status (on/off)
- อัพโหลด/แก้ไขรูปโลโก้
