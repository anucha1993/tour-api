# Wholesalers Table Specification

> ข้อมูลพื้นฐานของ Wholesale (ผู้ขายส่งทัวร์) + ข้อมูลสำหรับออกใบกำกับภาษี

---

## 1) วัตถุประสงค์

- เก็บข้อมูลพื้นฐานของ Wholesale แต่ละเจ้า
- เก็บข้อมูลสำหรับออกใบกำกับภาษี
- ใช้อ้างอิงในการรับข้อมูลทัวร์จาก Wholesale

---

## 2) Table Structure

### 2.1 ข้อมูลพื้นฐาน

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | NO | auto | Primary Key |
| `code` | varchar(50) | NO | - | รหัส Wholesale เช่น `ZEGO`, `TOURKRUB` (unique) |
| `name` | varchar(255) | NO | - | ชื่อบริษัท |
| `logo_url` | varchar(500) | YES | NULL | URL โลโก้ |
| `website` | varchar(255) | YES | NULL | เว็บไซต์ |
| `is_active` | boolean | NO | true | สถานะเปิด/ปิดใช้งาน |
| `notes` | text | YES | NULL | หมายเหตุภายใน |

### 2.2 ข้อมูลติดต่อ

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `contact_name` | varchar(255) | YES | NULL | ชื่อผู้ติดต่อ |
| `contact_email` | varchar(255) | YES | NULL | Email ติดต่อ |
| `contact_phone` | varchar(50) | YES | NULL | เบอร์โทรติดต่อ |

### 2.3 ข้อมูลใบกำกับภาษี (Tax Invoice)

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `tax_id` | varchar(20) | YES | NULL | เลขประจำตัวผู้เสียภาษี (13 หลัก) |
| `company_name_th` | varchar(255) | YES | NULL | ชื่อบริษัท (ภาษาไทย) สำหรับใบกำกับภาษี |
| `company_name_en` | varchar(255) | YES | NULL | ชื่อบริษัท (English) |
| `branch_code` | varchar(10) | YES | '00000' | รหัสสาขา (00000 = สำนักงานใหญ่) |
| `branch_name` | varchar(100) | YES | NULL | ชื่อสาขา |
| `address` | text | YES | NULL | ที่อยู่เต็ม (รวมถนน ตำบล อำเภอ จังหวัด รหัสไปรษณีย์) |
| `phone` | varchar(50) | YES | NULL | เบอร์โทรบริษัท |
| `fax` | varchar(50) | YES | NULL | เบอร์แฟกซ์ |

### 2.4 Timestamps

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `created_at` | timestamp | YES | NULL | วันที่สร้าง |
| `updated_at` | timestamp | YES | NULL | วันที่แก้ไขล่าสุด |

---

## 3) Indexes

| Index Name | Columns | Type | Description |
|------------|---------|------|-------------|
| PRIMARY | `id` | Primary | Primary Key |
| `wholesalers_code_unique` | `code` | Unique | รหัส Wholesale ห้ามซ้ำ |
| `wholesalers_tax_id_index` | `tax_id` | Index | ค้นหาด้วยเลขประจำตัวผู้เสียภาษี |
| `wholesalers_is_active_index` | `is_active` | Index | Filter active/inactive |

---

## 4) Relationships

```
wholesalers
    │
    ├── has many → tours
    ├── has many → partner_api_configs (อนาคต - Mapping Module)
    └── has many → sync_batches (อนาคต - Sync Logs)
```

---

## 5) Example Data

```json
{
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
```

---

## 6) Validation Rules

| Field | Rules |
|-------|-------|
| `code` | required, string, max:50, unique, alpha_dash |
| `name` | required, string, max:255 |
| `tax_id` | nullable, string, size:13, digits |
| `contact_email` | nullable, email |
| `branch_code` | nullable, string, max:10, default:'00000' |
| `address` | nullable, string |

---

## 7) Notes

- `tax_id` ควรเป็น 13 หลักตามรูปแบบของกรมสรรพากร
- `branch_code` = "00000" หมายถึงสำนักงานใหญ่
- ข้อมูลใบกำกับภาษีไม่จำเป็นต้องมีทั้งหมดตั้งแต่แรก สามารถเพิ่มทีหลังได้
- API credentials จะแยกไปอยู่ใน Mapping Module ในอนาคต

---

## 8) Migration Command (เมื่อพร้อม)

```bash
php artisan make:migration create_wholesalers_table
php artisan make:model Wholesaler
```
