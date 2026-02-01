# Users API Specification

## Overview
API สำหรับจัดการข้อมูลผู้ใช้งานในระบบ

## Base URL
```
/api/users
```

## Authentication
ทุก endpoint ต้องมี Bearer Token
```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. List Users
```
GET /api/users
```

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| page | integer | หน้าที่ต้องการ (default: 1) |
| per_page | integer | จำนวนต่อหน้า (default: 15) |
| search | string | ค้นหาจาก name, email |
| role | string | กรองตาม role |
| is_active | boolean | กรองตามสถานะ |

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Admin User",
        "email": "admin@nexttrip.com",
        "role": "admin",
        "is_active": true,
        "created_at": "2026-01-20T10:00:00Z",
        "updated_at": "2026-01-20T10:00:00Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 15,
      "total": 1
    }
  }
}
```

---

### 2. Get User
```
GET /api/users/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@nexttrip.com",
    "role": "admin",
    "is_active": true,
    "created_at": "2026-01-20T10:00:00Z",
    "updated_at": "2026-01-20T10:00:00Z"
  }
}
```

---

### 3. Create User
```
POST /api/users
```

**Request Body:**
```json
{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "staff",
  "is_active": true
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| name | required, string, max:255 |
| email | required, email, unique:users |
| password | required, min:8, confirmed |
| role | required, in:admin,manager,staff |
| is_active | boolean |

**Response:**
```json
{
  "success": true,
  "message": "สร้างผู้ใช้งานสำเร็จ",
  "data": {
    "id": 2,
    "name": "New User",
    "email": "newuser@example.com",
    "role": "staff",
    "is_active": true,
    "created_at": "2026-01-24T10:00:00Z"
  }
}
```

---

### 4. Update User
```
PUT /api/users/{id}
```

**Request Body:**
```json
{
  "name": "Updated Name",
  "email": "updated@example.com",
  "role": "manager",
  "is_active": true,
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Note:** password เป็น optional - ถ้าไม่ส่งจะไม่เปลี่ยน

**Response:**
```json
{
  "success": true,
  "message": "อัปเดตผู้ใช้งานสำเร็จ",
  "data": {
    "id": 2,
    "name": "Updated Name",
    "email": "updated@example.com",
    "role": "manager",
    "is_active": true
  }
}
```

---

### 5. Delete User
```
DELETE /api/users/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "ลบผู้ใช้งานสำเร็จ"
}
```

**Note:** ไม่สามารถลบตัวเองได้

---

## User Roles

| Role | Description |
|------|-------------|
| admin | ผู้ดูแลระบบ - เข้าถึงทุกฟังก์ชัน |
| manager | ผู้จัดการ - จัดการทัวร์และตัวแทน |
| staff | พนักงาน - ดูข้อมูลและบันทึกพื้นฐาน |

---

## Error Responses

### 404 Not Found
```json
{
  "success": false,
  "message": "ไม่พบผู้ใช้งาน"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "message": "ข้อมูลไม่ถูกต้อง",
  "errors": {
    "email": ["อีเมลนี้ถูกใช้งานแล้ว"]
  }
}
```

### 403 Cannot Delete Self
```json
{
  "success": false,
  "message": "ไม่สามารถลบบัญชีของตัวเองได้"
}
```
