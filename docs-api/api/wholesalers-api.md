# Wholesalers API Specification

> CRUD API Endpoints สำหรับจัดการข้อมูล Wholesalers
> ใช้งานจาก Next.js Frontend (Admin Panel)

---

## 1) Authentication

### 1.1 Auth Flow

```
┌─────────────┐     POST /api/auth/login      ┌─────────────┐
│   Next.js   │ ──────────────────────────────▶│   Laravel   │
│  Frontend   │                                │     API     │
│             │◀────────────────────────────── │             │
└─────────────┘     { access_token, user }     └─────────────┘
       │
       │  ทุก request ต้องส่ง
       │  Authorization: Bearer {access_token}
       ▼
┌─────────────┐
│  Protected  │
│  Endpoints  │
└─────────────┘
```

### 1.2 Auth Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/auth/register` | ลงทะเบียนผู้ใช้ใหม่ | ❌ |
| POST | `/api/auth/login` | เข้าสู่ระบบ | ❌ |
| POST | `/api/auth/logout` | ออกจากระบบ | ✅ |
| GET | `/api/auth/me` | ดูข้อมูล user ปัจจุบัน | ✅ |
| POST | `/api/auth/refresh` | รีเฟรช token | ✅ |

### 1.3 Login Request

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@nexttrip.com",
  "password": "password123"
}
```

### 1.4 Login Response (Success)

```json
{
  "success": true,
  "data": {
    "access_token": "1|abc123xyz...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@nexttrip.com",
      "role": "admin"
    }
  }
}
```

### 1.5 Login Response (Error)

```json
{
  "success": false,
  "message": "Invalid credentials",
  "errors": {
    "email": ["อีเมลหรือรหัสผ่านไม่ถูกต้อง"]
  }
}
```

### 1.6 Using Token

ทุก request ที่ต้อง auth ต้องส่ง header:

```http
Authorization: Bearer 1|abc123xyz...
```

---

## 2) Wholesalers CRUD Endpoints

### 2.1 Endpoint Summary

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/wholesalers` | ดูรายการ wholesalers ทั้งหมด | ✅ |
| GET | `/api/wholesalers/{id}` | ดูรายละเอียด wholesaler | ✅ |
| POST | `/api/wholesalers` | สร้าง wholesaler ใหม่ | ✅ |
| PUT | `/api/wholesalers/{id}` | แก้ไข wholesaler | ✅ |
| DELETE | `/api/wholesalers/{id}` | ลบ wholesaler | ✅ |
| PATCH | `/api/wholesalers/{id}/toggle-active` | เปิด/ปิด active status | ✅ |

---

## 3) GET /api/wholesalers

ดูรายการ wholesalers ทั้งหมด พร้อม pagination และ filter

### 3.1 Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | หน้าที่ต้องการ |
| `per_page` | int | 15 | จำนวนต่อหน้า (max: 100) |
| `search` | string | - | ค้นหาจาก code, name, company_name_th |
| `is_active` | boolean | - | กรอง active/inactive |
| `sort_by` | string | created_at | เรียงตาม field |
| `sort_order` | string | desc | asc หรือ desc |

### 3.2 Request Example

```http
GET /api/wholesalers?page=1&per_page=10&search=zego&is_active=true
Authorization: Bearer {token}
```

### 3.3 Response (Success)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "ZEGO",
      "name": "Zego Travel",
      "logo_url": "https://cdn.zegotravel.com/logo.png",
      "website": "https://zegotravel.com",
      "is_active": true,
      "contact_name": "คุณสมชาย",
      "contact_email": "api@zegotravel.com",
      "contact_phone": "02-123-4567",
      "tax_id": "0105548123456",
      "company_name_th": "บริษัท ซีโก้ ทราเวล จำกัด",
      "created_at": "2026-01-24T10:00:00+07:00",
      "updated_at": "2026-01-24T10:00:00+07:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 48
  }
}
```

---

## 4) GET /api/wholesalers/{id}

ดูรายละเอียด wholesaler ตาม ID

### 4.1 Request

```http
GET /api/wholesalers/1
Authorization: Bearer {token}
```

### 4.2 Response (Success)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "ZEGO",
    "name": "Zego Travel",
    "logo_url": "https://cdn.zegotravel.com/logo.png",
    "website": "https://zegotravel.com",
    "is_active": true,
    "notes": "Wholesale หลัก ทัวร์จีน",
    
    "contact_name": "คุณสมชาย",
    "contact_email": "api@zegotravel.com",
    "contact_phone": "02-123-4567",
    
    "tax_id": "0105548123456",
    "company_name_th": "บริษัท ซีโก้ ทราเวล จำกัด",
    "company_name_en": "Zego Travel Co., Ltd.",
    "branch_code": "00000",
    "branch_name": "สำนักงานใหญ่",
    "address": "123 อาคาร ABC ชั้น 10 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพมหานคร 10110",
    "phone": "02-123-4567",
    "fax": "02-123-4568",
    
    "created_at": "2026-01-24T10:00:00+07:00",
    "updated_at": "2026-01-24T10:00:00+07:00"
  }
}
```

### 4.3 Response (Not Found)

```json
{
  "success": false,
  "message": "Wholesaler not found"
}
```

---

## 5) POST /api/wholesalers

สร้าง wholesaler ใหม่

### 5.1 Request

```http
POST /api/wholesalers
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "TOURKRUB",
  "name": "Tour Krub",
  "logo_url": "https://tourkrub.com/logo.png",
  "website": "https://tourkrub.com",
  "is_active": true,
  "notes": "Wholesale ทัวร์ญี่ปุ่น",
  
  "contact_name": "คุณสมหญิง",
  "contact_email": "contact@tourkrub.com",
  "contact_phone": "02-999-8888",
  
  "tax_id": "0105561789012",
  "company_name_th": "บริษัท ทัวร์ครับ จำกัด",
  "company_name_en": "Tour Krub Co., Ltd.",
  "branch_code": "00000",
  "branch_name": "สำนักงานใหญ่",
  "address": "456 อาคาร XYZ ถนนพระราม 9 เขตห้วยขวาง กรุงเทพฯ 10310",
  "phone": "02-999-8888",
  "fax": "02-999-8889"
}
```

### 5.2 Validation Rules

| Field | Rules | Message |
|-------|-------|---------|
| `code` | required, string, max:50, unique, alpha_dash | รหัสต้องไม่ซ้ำและประกอบด้วยตัวอักษร ตัวเลข - _ เท่านั้น |
| `name` | required, string, max:255 | กรุณาระบุชื่อบริษัท |
| `logo_url` | nullable, url, max:500 | รูปแบบ URL ไม่ถูกต้อง |
| `website` | nullable, url, max:255 | รูปแบบ URL ไม่ถูกต้อง |
| `is_active` | boolean | - |
| `contact_email` | nullable, email | รูปแบบอีเมลไม่ถูกต้อง |
| `tax_id` | nullable, string, size:13, regex:/^[0-9]+$/ | เลขประจำตัวผู้เสียภาษีต้องเป็นตัวเลข 13 หลัก |
| `branch_code` | nullable, string, max:10 | - |

### 5.3 Response (Success - 201 Created)

```json
{
  "success": true,
  "message": "Wholesaler created successfully",
  "data": {
    "id": 2,
    "code": "TOURKRUB",
    "name": "Tour Krub",
    "...": "..."
  }
}
```

### 5.4 Response (Validation Error - 422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "code": ["รหัส TOURKRUB ถูกใช้งานแล้ว"],
    "tax_id": ["เลขประจำตัวผู้เสียภาษีต้องเป็นตัวเลข 13 หลัก"]
  }
}
```

---

## 6) PUT /api/wholesalers/{id}

แก้ไขข้อมูล wholesaler

### 6.1 Request

```http
PUT /api/wholesalers/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Zego Travel (Updated)",
  "contact_phone": "02-123-9999",
  "is_active": true
}
```

### 6.2 Validation Rules

เหมือน POST แต่ `code` จะ unique ignore ตัวเอง:
```
code: required, unique:wholesalers,code,{id}
```

### 6.3 Response (Success)

```json
{
  "success": true,
  "message": "Wholesaler updated successfully",
  "data": {
    "id": 1,
    "code": "ZEGO",
    "name": "Zego Travel (Updated)",
    "...": "..."
  }
}
```

---

## 7) DELETE /api/wholesalers/{id}

ลบ wholesaler

### 7.1 Request

```http
DELETE /api/wholesalers/1
Authorization: Bearer {token}
```

### 7.2 Response (Success)

```json
{
  "success": true,
  "message": "Wholesaler deleted successfully"
}
```

### 7.3 Response (Cannot Delete - มี tours ผูกอยู่)

```json
{
  "success": false,
  "message": "Cannot delete wholesaler. It has 15 tours associated."
}
```

---

## 8) PATCH /api/wholesalers/{id}/toggle-active

เปิด/ปิด active status (shortcut)

### 8.1 Request

```http
PATCH /api/wholesalers/1/toggle-active
Authorization: Bearer {token}
```

### 8.2 Response

```json
{
  "success": true,
  "message": "Wholesaler status updated",
  "data": {
    "id": 1,
    "is_active": false
  }
}
```

---

## 9) Standard Response Format

### 9.1 Success Response

```json
{
  "success": true,
  "message": "Optional success message",
  "data": { ... }
}
```

### 9.2 Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

### 9.3 HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 204 | No Content - Deleted successfully |
| 400 | Bad Request - Invalid request |
| 401 | Unauthorized - Token missing/invalid |
| 403 | Forbidden - No permission |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation failed |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

---

## 10) Rate Limiting

| Endpoint Type | Limit |
|---------------|-------|
| Auth endpoints | 5 requests/minute |
| API endpoints | 60 requests/minute |

Response เมื่อเกิน limit:

```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "retry_after": 45
}
```

---

## 11) CORS Configuration

สำหรับ Next.js Frontend:

```php
// config/cors.php
'allowed_origins' => [
    'http://localhost:3000',      // Next.js dev
    'https://admin.nexttrip.com', // Production
],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
```

---

## 12) Next.js Integration Example

### 12.1 API Client Setup

```typescript
// lib/api.ts
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL;

export async function fetchAPI(endpoint: string, options: RequestInit = {}) {
  const token = localStorage.getItem('access_token');
  
  const res = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token && { Authorization: `Bearer ${token}` }),
      ...options.headers,
    },
  });
  
  if (!res.ok) {
    throw new Error(await res.text());
  }
  
  return res.json();
}
```

### 12.2 Usage Example

```typescript
// Get all wholesalers
const { data, meta } = await fetchAPI('/api/wholesalers?page=1');

// Create wholesaler
const newWholesaler = await fetchAPI('/api/wholesalers', {
  method: 'POST',
  body: JSON.stringify({
    code: 'NEWWHOLESALE',
    name: 'New Wholesale',
  }),
});
```

---

## 13) Files to Create (เมื่อพร้อม implement)

```
laravel-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   └── WholesalerController.php
│   │   ├── Requests/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginRequest.php
│   │   │   │   └── RegisterRequest.php
│   │   │   └── Wholesaler/
│   │   │       ├── StoreWholesalerRequest.php
│   │   │       └── UpdateWholesalerRequest.php
│   │   └── Resources/
│   │       └── WholesalerResource.php
├── routes/
│   └── api.php
```

---

## 14) Commands (เมื่อพร้อม implement)

```bash
# Install Sanctum for API auth
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

# Create controllers
php artisan make:controller Api/AuthController
php artisan make:controller Api/WholesalerController --resource

# Create form requests
php artisan make:request Auth/LoginRequest
php artisan make:request Wholesaler/StoreWholesalerRequest
php artisan make:request Wholesaler/UpdateWholesalerRequest

# Create API resource
php artisan make:resource WholesalerResource
```
