# Countries API

> วันที่อัพเดท: 26 มกราคม 2569

## Base URL
```
/api/countries
```

## Authentication
ต้องมี Bearer Token ใน header ทุก request

```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. List Countries
```http
GET /api/countries
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| search | string | ค้นหาจาก name_en, name_th, iso2, iso3 |
| region | string | กรองตาม region (asia, europe, etc.) |
| is_active | boolean | กรองตาม status (true/false) |
| page | integer | หน้าที่ต้องการ |
| per_page | integer | จำนวนต่อหน้า (default: 50) |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "iso2": "TH",
      "iso3": "THA",
      "name_en": "Thailand",
      "name_th": "ประเทศไทย",
      "slug": "thailand",
      "region": "asia",
      "flag_emoji": "🇹🇭",
      "is_active": true,
      "created_at": "2026-01-25T00:00:00.000000Z",
      "updated_at": "2026-01-25T00:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 50,
    "total": 202
  }
}
```

---

### 2. Get Country
```http
GET /api/countries/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "iso2": "TH",
    "iso3": "THA",
    "name_en": "Thailand",
    "name_th": "ประเทศไทย",
    "slug": "thailand",
    "region": "asia",
    "flag_emoji": "🇹🇭",
    "is_active": true
  }
}
```

---

### 3. Create Country
```http
POST /api/countries
```

**Body (JSON):**
```json
{
  "iso2": "TH",
  "iso3": "THA",
  "name_en": "Thailand",
  "name_th": "ประเทศไทย",
  "slug": "thailand",
  "region": "asia",
  "flag_emoji": "🇹🇭",
  "is_active": true
}
```

**Validation:**
| Field | Rules |
|-------|-------|
| iso2 | required, 2 characters, unique |
| iso3 | required, 3 characters, unique |
| name_en | required, max 100 |
| name_th | optional, max 100 |
| slug | required, max 100, unique |
| region | optional, must be valid region |
| flag_emoji | optional, max 10 |
| is_active | optional, boolean |

---

### 4. Update Country
```http
PUT /api/countries/{id}
```

**Body:** Same as Create

---

### 5. Delete Country
```http
DELETE /api/countries/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Country deleted successfully"
}
```

---

### 6. Toggle Status
```http
PATCH /api/countries/{id}/toggle-status
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "is_active": false
  },
  "message": "Status updated successfully"
}
```

---

### 7. Get Regions
```http
GET /api/countries/regions
```

**Response:**
```json
{
  "success": true,
  "data": {
    "asia": "เอเชีย",
    "europe": "ยุโรป",
    "north_america": "อเมริกาเหนือ",
    "south_america": "อเมริกาใต้",
    "africa": "แอฟริกา",
    "oceania": "โอเชียเนีย",
    "middle_east": "ตะวันออกกลาง",
    "caribbean": "แคริบเบียน"
  }
}
```

---

## Region Constants

| Value | Label (TH) |
|-------|------------|
| asia | เอเชีย |
| europe | ยุโรป |
| north_america | อเมริกาเหนือ |
| south_america | อเมริกาใต้ |
| africa | แอฟริกา |
| oceania | โอเชียเนีย |
| middle_east | ตะวันออกกลาง |
| caribbean | แคริบเบียน |

---

## UI Implementation

### Flag Display
ใช้ flagcdn.com สำหรับแสดงธงชาติ:
```tsx
const getFlagUrl = (iso2: string): string => {
  return `https://flagcdn.com/w80/${iso2.toLowerCase()}.png`;
};
```

### Pages
- List: `/dashboard/countries`
- Create: `/dashboard/countries/create`
- Edit: `/dashboard/countries/[id]`
