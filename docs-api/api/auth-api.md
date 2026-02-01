# Authentication API Documentation

> ระบบ Authentication สำหรับ NextTrip API
> ใช้ Laravel Sanctum สำหรับ Token-based Authentication

---

## 1) Overview

### 1.1 Base URL
```
Development: http://127.0.0.1:8000/api
Production:  https://api.nexttrip.com/api
```

### 1.2 Authentication Flow

```
┌─────────────────┐                      ┌─────────────────┐
│   Next.js       │                      │   Laravel API   │
│   Frontend      │                      │                 │
└────────┬────────┘                      └────────┬────────┘
         │                                        │
         │  1. POST /api/auth/login               │
         │  { email, password }                   │
         ├───────────────────────────────────────▶│
         │                                        │
         │  2. Response                           │
         │  { access_token, user }                │
         │◀───────────────────────────────────────┤
         │                                        │
         │  3. เก็บ token ใน localStorage/cookie  │
         │                                        │
         │  4. ทุก request ส่ง header             │
         │  Authorization: Bearer {token}         │
         ├───────────────────────────────────────▶│
         │                                        │
         │  5. Response data                      │
         │◀───────────────────────────────────────┤
         │                                        │
```

---

## 2) Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/auth/register` | ลงทะเบียนผู้ใช้ใหม่ | ❌ |
| POST | `/api/auth/login` | เข้าสู่ระบบ | ❌ |
| GET | `/api/auth/me` | ดูข้อมูล user ปัจจุบัน | ✅ |
| POST | `/api/auth/logout` | ออกจากระบบ (อุปกรณ์ปัจจุบัน) | ✅ |
| POST | `/api/auth/logout-all` | ออกจากระบบทุกอุปกรณ์ | ✅ |
| POST | `/api/auth/refresh` | รีเฟรช token | ✅ |

---

## 3) POST /api/auth/register

ลงทะเบียนผู้ใช้ใหม่

### Request

```http
POST /api/auth/register
Content-Type: application/json
Accept: application/json
```

```json
{
  "name": "Admin User",
  "email": "admin@nexttrip.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### Validation Rules

| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `email` | required, email, unique:users |
| `password` | required, min:8, confirmed |

### Response (Success - 201)

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "access_token": "1|abc123xyz...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@nexttrip.com"
    }
  }
}
```

### Response (Validation Error - 422)

```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

### cURL Example

```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Admin User",
    "email": "admin@nexttrip.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

---

## 4) POST /api/auth/login

เข้าสู่ระบบและรับ access token

### Request

```http
POST /api/auth/login
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "admin@nexttrip.com",
  "password": "password123"
}
```

### Response (Success - 200)

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "access_token": "2|ElLV61O9HBzxZvGYNSwGnNcXprCNHskNbieMRLFw1ef5e248",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@nexttrip.com"
    }
  }
}
```

### Response (Invalid Credentials - 401)

```json
{
  "success": false,
  "message": "Invalid credentials",
  "errors": {
    "email": ["อีเมลหรือรหัสผ่านไม่ถูกต้อง"]
  }
}
```

### cURL Example

```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@nexttrip.com",
    "password": "password123"
  }'
```

---

## 5) GET /api/auth/me

ดูข้อมูล user ที่ login อยู่

### Request

```http
GET /api/auth/me
Accept: application/json
Authorization: Bearer {access_token}
```

### Response (Success - 200)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@nexttrip.com",
    "email_verified_at": null,
    "created_at": "2026-01-24T14:04:28.000000Z"
  }
}
```

### Response (Unauthorized - 401)

```json
{
  "message": "Unauthenticated."
}
```

### cURL Example

```bash
curl -X GET http://127.0.0.1:8000/api/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 2|ElLV61O9HBzxZvGYNSwGnNcXprCNHskNbieMRLFw1ef5e248"
```

---

## 6) POST /api/auth/logout

ออกจากระบบ (revoke token ปัจจุบัน)

### Request

```http
POST /api/auth/logout
Accept: application/json
Authorization: Bearer {access_token}
```

### Response (Success - 200)

```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### cURL Example

```bash
curl -X POST http://127.0.0.1:8000/api/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {access_token}"
```

---

## 7) POST /api/auth/logout-all

ออกจากระบบทุกอุปกรณ์ (revoke ทุก token)

### Request

```http
POST /api/auth/logout-all
Accept: application/json
Authorization: Bearer {access_token}
```

### Response (Success - 200)

```json
{
  "success": true,
  "message": "Logged out from all devices successfully"
}
```

### cURL Example

```bash
curl -X POST http://127.0.0.1:8000/api/auth/logout-all \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {access_token}"
```

---

## 8) POST /api/auth/refresh

รีเฟรช token (สร้าง token ใหม่ และ revoke token เดิม)

### Request

```http
POST /api/auth/refresh
Accept: application/json
Authorization: Bearer {access_token}
```

### Response (Success - 200)

```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "3|newTokenXyz...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### cURL Example

```bash
curl -X POST http://127.0.0.1:8000/api/auth/refresh \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {access_token}"
```

---

## 9) Using Token in Requests

ทุก protected endpoint ต้องส่ง Authorization header:

```http
Authorization: Bearer {access_token}
```

### Example with Protected Endpoint

```bash
# ดู wholesalers (protected route)
curl -X GET http://127.0.0.1:8000/api/wholesalers \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 2|ElLV61O9HBzxZvGYNSwGnNcXprCNHskNbieMRLFw1ef5e248"
```

---

## 10) Error Responses

### 10.1 Unauthenticated (401)

เกิดเมื่อไม่มี token หรือ token หมดอายุ/ถูก revoke

```json
{
  "message": "Unauthenticated."
}
```

### 10.2 Validation Error (422)

เกิดเมื่อข้อมูลที่ส่งมาไม่ถูกต้อง

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### 10.3 Server Error (500)

```json
{
  "message": "Server Error"
}
```

---

## 11) Next.js Integration

### 11.1 API Client Setup

```typescript
// lib/api.ts
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export async function apiRequest(
  endpoint: string,
  options: RequestInit = {}
): Promise<any> {
  const token = localStorage.getItem('access_token');

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(token && { Authorization: `Bearer ${token}` }),
    ...options.headers,
  };

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  if (response.status === 401) {
    // Token expired, redirect to login
    localStorage.removeItem('access_token');
    window.location.href = '/login';
    throw new Error('Unauthorized');
  }

  return response.json();
}
```

### 11.2 Auth Functions

```typescript
// lib/auth.ts
import { apiRequest } from './api';

export async function login(email: string, password: string) {
  const response = await apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });

  if (response.success) {
    localStorage.setItem('access_token', response.data.access_token);
    return response.data.user;
  }

  throw new Error(response.message);
}

export async function logout() {
  try {
    await apiRequest('/auth/logout', { method: 'POST' });
  } finally {
    localStorage.removeItem('access_token');
    window.location.href = '/login';
  }
}

export async function getMe() {
  const response = await apiRequest('/auth/me');
  return response.data;
}
```

### 11.3 Usage in Component

```tsx
// app/login/page.tsx
'use client';

import { useState } from 'react';
import { login } from '@/lib/auth';
import { useRouter } from 'next/navigation';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await login(email, password);
      router.push('/dashboard');
    } catch (err: any) {
      setError(err.message);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Email"
      />
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="Password"
      />
      {error && <p className="error">{error}</p>}
      <button type="submit">Login</button>
    </form>
  );
}
```

---

## 12) Security Notes

### 12.1 Token Storage

| Storage | Pros | Cons |
|---------|------|------|
| localStorage | ง่าย, ใช้งานได้ทันที | เสี่ยง XSS |
| httpOnly Cookie | ปลอดภัยจาก XSS | ต้อง config CORS |
| Memory (React State) | ปลอดภัยที่สุด | หายเมื่อ refresh |

**แนะนำ:** ใช้ httpOnly Cookie สำหรับ production

### 12.2 Token Expiration

- Token จะหมดอายุตาม config ใน `config/sanctum.php`
- Default: ไม่หมดอายุ
- แนะนำ: ตั้ง expiration 24 ชม. - 7 วัน

### 12.3 CORS Configuration

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_origins' => [
    'http://localhost:3000',      // Next.js dev
    'https://admin.nexttrip.com', // Production
],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

---

## 13) Test Credentials

สำหรับทดสอบระบบ:

```
Email: admin@nexttrip.com
Password: password123
```

---

## 14) Files Created

| File | Description |
|------|-------------|
| `app/Models/User.php` | เพิ่ม HasApiTokens trait |
| `app/Http/Controllers/Api/AuthController.php` | Auth endpoints |
| `routes/api.php` | API routes |
| `config/sanctum.php` | Sanctum configuration |
| `database/migrations/*_create_personal_access_tokens_table.php` | Tokens table |
