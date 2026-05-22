# Custom Booking API — สรุปสำหรับการพัฒนาระบบ

## 1. ภาพรวม (Overview)

Custom Booking API ของ Zego Travel ใช้สำหรับให้ Agency พัฒนาหน้าจองทัวร์แบบ Custom เองได้ โดยมี **3 ขั้นตอนหลัก (3 Steps)**:

| Step | Method | Endpoint | วัตถุประสงค์ |
|------|--------|----------|--------------|
| 1 | GET | `/v1.5/booking/product/:public_key/:product_code` | ดึงข้อมูลโปรแกรมทัวร์ + รายการ Period ทั้งหมด |
| 2 | GET | `/v1.5/booking/period/:public_key/:period_id` | ดึงข้อมูล Period ที่เลือก + ประเภทราคา + ประเภทห้อง |
| 3 | POST | `/v1.5/booking/booking-submit` | ส่งข้อมูลการจอง |

**Base URL:** `https://www.zegoapi.com`

**Public Key:** ดูได้ที่ `https://www.zegotravel.com/AgencyProfile`

---

## 2. Step 1 — Product GET

### Endpoint
```
GET https://www.zegoapi.com/v1.5/booking/product/{public_key}/{product_code}
```

### Response Structure
```json
{
  "status": true,
  "data": {
    "uuid": "2-xxxx",
    "publicKey": "public-key",
    "agentId": 9999999,
    "productId": 2204,
    "datapackage": {
      "agency": { },
      "bookingSetting": { },
      "product": { },
      "periods": []
    }
  },
  "code": "SUCCESS"
}
```

### Fields: `data`
| Field | Type | Description |
|-------|------|-------------|
| uuid | string | รหัส session (ใช้ได้ 1 ครั้ง) |
| publicKey | string | คีย์ของ Agency |
| agentId | number | รหัส Agency |
| productId | number | รหัสโปรแกรมทัวร์ |
| datapackage | object | ข้อมูลรายละเอียดทั้งหมด |

### Fields: `datapackage.agency`
| Field | Type | Description |
|-------|------|-------------|
| agencyId | number | รหัส Agency |
| agencyName | string | ชื่อ Agency |
| agencyEmail | string | อีเมล |
| agencyPhone | string | เบอร์โทร |
| agencyAddress | string | ที่อยู่ |
| agencyPostcode | string | รหัสไปรษณีย์ |
| agencyProvince | string | จังหวัด |
| agencyLogo | string | URL โลโก้ |
| publicKey | string | คีย์ของ Agency |

### Fields: `datapackage.bookingSetting`
| Field | Type | Description |
|-------|------|-------------|
| header | string | HTML ช่องทางติดต่อด่วน |
| footer | string | HTML เงื่อนไขการจอง |
| primaryColor | string | สีหลักหน้าจอง |
| secondaryColor | string | สีรองหน้าจอง |
| enableHosted | boolean | สถานะ Standard Form API |
| enableApi | boolean | สถานะ Custom Form API |
| url | string | URL Redirect เมื่อจองสำเร็จ |

### Fields: `datapackage.product`
| Field | Type | Description |
|-------|------|-------------|
| productId | number | รหัสอ้างอิงโปรแกรมทัวร์ |
| productCode | string | รหัสโปรแกรมทัวร์ |
| productName | string | ชื่อโปรแกรมทัวร์ |
| days | string | จำนวนวัน |
| nights | string | จำนวนคืน |
| daysAndNights | string | ระยะเวลารวม |
| countryCode | string | รหัสประเทศ |
| countryName | string | ชื่อประเทศ |
| airlineCode | string | รหัสสายการบิน |
| airlineName | string | ชื่อสายการบิน |
| fileWord | string | URL ไฟล์ Word |
| filePDF | string | URL ไฟล์ PDF |
| imageUrl | string | URL รูปภาพ |
| highlight | string | จุดเด่นของโปรแกรม |
| maxHotelStars | string | ระดับดาวสูงสุด |
| minHotelStars | string | ระดับดาวต่ำสุด |
| planeMeals | string | มีอาหารบนเครื่อง (Y/N) |
| totalMeals | string | จำนวนมื้ออาหารรวม |

### Fields: `datapackage.periods[]`
| Field | Type | Description |
|-------|------|-------------|
| periodId | number | รหัสวันเดินทาง (ใช้ใน Step 2) |
| periodCode | string | รหัสกรุ๊ป |
| productId | number | รหัสโปรแกรม |
| bus | string | กลุ่มบัส เช่น Bus A, Bus B |
| periodStartDate | string | วันเริ่มเดินทาง |
| periodEndDate | string | วันสิ้นสุด |
| airport | string | สนามบินต้นทาง |
| groupSize | number | ที่นั่งทั้งหมด |
| book | number | ที่จองแล้ว |
| seat | number | ที่นั่งคงเหลือ |
| periodStatus | string | สถานะการจอง |
| price | string | ราคาผู้ใหญ่ |
| priceChild | string | ราคาเด็กมีเตียง |
| priceChildNB | string | ราคาเด็กไม่มีเตียง |
| priceInfant | string | ราคาทารก |
| deposit | string | เงินมัดจำ |
| promotion | string | สถานะโปรโมชั่น |

---

## 3. Step 2 — Period GET

### Endpoint
```
GET https://www.zegoapi.com/v1.5/booking/period/{public_key}/{period_id}
```

> ใช้ `periodId` จาก `datapackage.periods[]` ใน Step 1

### Response Structure
```json
{
  "status": true,
  "data": {
    "uuid": "2-xxxx",
    "publicKey": "public-key",
    "agentId": 99999,
    "periodId": 16869,
    "productId": 2204,
    "datapackage": {
      "agency": { },
      "bookingSetting": { },
      "booking": { },
      "product": { },
      "selectedPeriod": {},
      "bookingDetails": [],
      "roomDetails": [],
      "promotion": {}
    }
  },
  "code": "SUCCESS"
}
```

### Fields: `datapackage.booking`
| Field | Type | Description |
|-------|------|-------------|
| customerName | string | ชื่อผู้จอง |
| customerPhone | string | เบอร์โทรผู้จอง |
| remark | string | หมายเหตุเพิ่มเติม |

### Fields: `datapackage.selectedPeriod`
| Field | Type | Description |
|-------|------|-------------|
| periodId | number | รหัสวันเดินทาง |
| periodCode | string | รหัสกรุ๊ป |
| productId | number | รหัสโปรแกรม |
| periodStartDate | string | วันเดินทางไป |
| periodEndDate | string | วันเดินทางกลับ |
| airport | string | สนามบินต้นทาง |
| groupSize | number | ที่นั่งทั้งหมด |
| book | number | ที่จองแล้ว |
| seat | number | ที่นั่งคงเหลือ |
| periodStatus | string | สถานะกรุ๊ป |
| price | string | ราคาผู้ใหญ่ |
| priceChild | string | ราคาเด็กมีเตียง |
| priceChildNB | string | ราคาเด็กไม่มีเตียง |
| priceInfant | string | ราคาทารก |
| priceSingleBed | string | ค่าพักเดี่ยว |
| priceTwinBed | string | ค่าพักคู่ Twin |
| priceDoubleBed | string | ค่าพักคู่ Double |
| priceTripleBed | string | ค่าพักสามท่าน |
| deposit | string | เงินมัดจำ |
| promotion | string | สถานะโปรโมชั่น |

### Fields: `datapackage.bookingDetails[]`
ประเภทผู้เดินทาง — ใช้ `code` และ `num` เมื่อส่ง Step 3

| Field | Type | Description |
|-------|------|-------------|
| id | number | รหัสรายการ |
| content | string | ชื่อประเภทผู้เดินทาง |
| num | number | จำนวนที่เลือก |
| price | string | ราคาต่อหน่วย |
| code | string | รหัสประเภท เช่น `MA` (ผู้ใหญ่) |

> **หมายเหตุ:** Code `MA` (ผู้ใหญ่) เป็น **required** และต้องมีค่า > 0

### Fields: `datapackage.roomDetails[]`
ประเภทห้องพัก — ใช้ `code` และ `num` เมื่อส่ง Step 3

| Field | Type | Description |
|-------|------|-------------|
| id | number | รหัสรายการห้อง |
| content | string | ชื่อประเภทห้องพัก |
| num | number | จำนวนที่เลือก |
| price | string | ค่าใช้จ่ายเพิ่มเติม |
| code | string | รหัสประเภทห้อง |

### Fields: `datapackage.promotion`
| Field | Type | Description |
|-------|------|-------------|
| hasPromotion | boolean | มีโปรโมชั่นหรือไม่ |

---

## 4. Step 3 — Booking Submit (POST)

### Endpoint
```
POST https://www.zegoapi.com/v1.5/booking/booking-submit
```

### Headers ที่ต้องแนบ (Required)
```json
{
  "x-public-key": "c610fa1*****",
  "x-uuid": "2-xses******"
}
```

> `uuid` มาจาก Response ของ Step 1 หรือ Step 2

### Request Body (ตัวอย่าง)
```json
{
  "booking": {
    "customerName": "ทดสอบ",
    "customerPhone": "0849999999",
    "remark": "ทดสอบความต้องการ"
  },
  "bookingDetails": [
    {
      "code": "MA",
      "num": 2
    }
  ],
  "roomDetails": [
    {
      "code": "TWIN",
      "num": 1
    }
  ]
}
```

> **กฎสำคัญ:** แก้เฉพาะค่า `num` ใน `bookingDetails` และ `roomDetails` โดยใช้ `code` ที่ได้จาก Step 2

### Response เมื่อสำเร็จ
```json
{
  "status": true,
  "data": {
    "uuid": "...",
    "publicKey": "...",
    "agentId": 99999,
    "periodId": 16856,
    "bookingId": 12345,
    "bookingNo": "BK-XXXXX",
    "customerName": "ทดสอบ",
    "customerPhone": "0849999999",
    "remark": "...",
    "bookingDetails": [],
    "roomDetails": [],
    "discountDetails": [],
    "commissionDetails": []
  },
  "code": "SUCCESS"
}
```

### Fields: Response `data`
| Field | Type | Description |
|-------|------|-------------|
| uuid | string | รหัส session |
| publicKey | string | คีย์ Agency |
| agentId | number | รหัส Agency |
| periodId | number | รหัสวันเดินทาง |
| bookingId | number | รหัสการจองที่ได้รับ |
| bookingNo | string | เลขที่การจอง |
| customerName | string | ชื่อลูกค้า |
| customerPhone | string | เบอร์ลูกค้า |
| remark | string | หมายเหตุ |
| bookingDetails | object | รายละเอียดการจอง (จำนวน+ราคา) |
| roomDetails | object | รายละเอียดห้อง |
| discountDetails | object | ส่วนลดต่าง ๆ (ถ้ามี) |
| commissionDetails | object | ค่าคอมมิชชั่น |

---

## 5. HTTP Status Codes & Error Codes

### Step 1 — Product GET
| HTTP | Code | สาเหตุ |
|------|------|--------|
| 200 | SUCCESS | product_code ถูกต้อง |
| 404 | PRODUCT_NOT_FOUND | product_code ไม่มีในระบบ |

### Step 2 — Period GET
| HTTP | Code | สาเหตุ |
|------|------|--------|
| 200 | SUCCESS | period_id ถูกต้อง สร้าง uuid ได้ |
| 404 | PERIOD_NOT_FOUND | period_id ไม่มีในระบบ |

### Step 3 — Booking Submit
| HTTP | Code | สาเหตุ / ตัวอย่าง |
|------|------|-------------------|
| 200 | SUCCESS | จองสำเร็จ |
| 400 | PUBLIC_KEY_REQUIRED | ไม่ส่ง header `x-public-key` |
| 400 | UUID_REQUIRED | ไม่ส่ง `x-uuid` |
| 400 | INVALID_BOOKING_SESSION | uuid ไม่มีในระบบ |
| 400 | AGENT_ID_REQUIRED | agentID ไม่ตรงกับ uuid |
| 400 | PERIOD_ID_REQUIRED | period ไม่ตรงกับ uuid |
| 400 | PERIOD_NOT_AVAILABLE | period ปิด / cancel / full |
| 400 | CUSTOMER_NAME_REQUIRED | ชื่อลูกค้าว่าง |
| 400 | INVALID_CUSTOMER_NAME | ชื่อมี special char เช่น `@`, `$`, `-`, `_`, emoji |
| 400 | CUSTOMER_PHONE_REQUIRED | ไม่มีเบอร์โทร |
| 400 | INVALID_CUSTOMER_PHONE | เบอร์ไม่ใช่ตัวเลข 10 ตัว |
| 400 | BOOKING_DETAILS_REQUIRED | ไม่มี bookingDetails หรือ array ว่าง |
| 400 | BOOKING_DETAIL_CODE_REQUIRED | code ของรายการเป็น null |
| 400 | BOOKING_DETAIL_CODE_NOT_ALLOWED | code ไม่ตรงกับระบบ |
| 400 | BOOKING_DETAIL_NUM_INVALID | จำนวนไม่ใช่ตัวเลข |
| 400 | BOOKING_DETAIL_NUM_MUST_BE_INTEGER | จำนวนไม่ใช่ integer เช่น 1.5 |
| 400 | BOOKING_DETAIL_NUM_NEGATIVE | จำนวนติดลบ |
| 400 | BOOKING_DETAIL_MA_REQUIRED | ไม่มี code `MA` (ผู้ใหญ่) |
| 400 | BOOKING_DETAIL_MA_NUM_REQUIRED | MA = 0 |
| 400 | TOTAL_PAX_REQUIRED | รวมผู้เดินทาง = 0 |
| 400 | PAX_LIMIT_EXCEEDED | เกิน 10 คน |
| 400 | PERIOD_FULL | จำนวนคนเกินที่นั่งที่เหลือ |
| 400 | ROOM_PAX_MISMATCH | จำนวนห้องไม่สมดุลกับจำนวนคน |
| 500 | INTERNAL_ERROR | Server error |

---

## 6. Validation Rules สำคัญ

```
customerName:
  - ต้องไม่ว่าง
  - ห้ามมี: @ $ - _ และ emoji

customerPhone:
  - ตัวเลขเท่านั้น
  - ต้องมี 10 ตัวพอดี

bookingDetails:
  - ต้องมีอย่างน้อย 1 รายการ
  - ต้องมี code "MA" (ผู้ใหญ่) และ num > 0
  - num ต้องเป็น integer บวกเท่านั้น
  - รวม pax สูงสุด 10 คน
  - code ต้องตรงกับที่ได้จาก Step 2 เท่านั้น

roomDetails:
  - จำนวนห้องต้องสมดุลกับจำนวนผู้เดินทาง
```

---

## 7. Flow สรุป

```
[Agency] 
  │
  ├─ STEP 1: GET /product/{public_key}/{product_code}
  │         ← ได้ uuid + รายการ periods[]
  │
  ├─ STEP 2: GET /period/{public_key}/{period_id}
  │         ← ได้ bookingDetails[] + roomDetails[] + uuid ใหม่
  │
  ├─ (Optional) Agency บันทึกข้อมูลเข้าระบบตัวเองก่อน/หลัง
  │
  └─ STEP 3: POST /booking-submit
             Headers: x-public-key, x-uuid
             Body: booking + bookingDetails[{code, num}] + roomDetails[{code, num}]
             ← ได้ bookingId + bookingNo
```

---

## 8. ข้อมูล JSON Structure (Zego API V1.5)

### Program Tour Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| ProductID | string/number | Y | Primary Key |
| ProductCode | string | Y | รหัสโปรแกรม เช่น `ZGHKG-2413CX` |
| ProductName | string | Y | ชื่อโปรแกรม |
| AirlineCode | string | Y | รหัสสายการบิน |
| AirlineName | string | Y | ชื่อสายการบิน |
| CountryCode | string | Y | รหัสประเทศ |
| CountryName | string | Y | ชื่อประเทศ |
| Days | string | Y | จำนวนวัน |
| Nights | string | Y | จำนวนคืน |
| PlaneMeals | string | Y | อาหารบนเครื่อง (Y/N) |
| TotalMeals | number | Y | จำนวนมื้ออาหาร |
| MaxHotelStars | number | Y | ดาวสูงสุด |
| MinHotelStars | number | Y | ดาวต่ำสุด |
| Highlight | string | Optional | จุดเด่น |
| Locations | string[] | Y | รายชื่อเมือง |
| URLImage | string | Y | URL รูปภาพ |
| FilePDF | string | Y | URL PDF |
| FileWord | string | Y | URL Word |
| CountryCodeISO2 | string | Y | ISO 2 ตัวอักษร |
| CountryCodeISO3 | string | Y | ISO 3 ตัวอักษร |

### Period Fields (ที่สำคัญ)
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| PeriodID | number | Y | Primary Key |
| PeriodCode | string | Y | รหัสกรุ๊ป |
| Bus | string | Y | กลุ่มบัส |
| PeriodStartDate | string | Y | วันออกเดินทาง |
| PeriodEndDate | string | Y | วันกลับ |
| Airport | string | Y | สนามบินขาออก |
| Groupsize | number | Y | ขนาดกรุ๊ป |
| Seat | string | Y | ที่นั่งว่าง |
| PeriodStatus | string | Y | Soldout / Book / Close Group / Waitlist |
| Price | number | Y | ราคาผู้ใหญ่พักคู่ |
| Price_Child | number | Y | ราคาเด็กมีเตียง |
| Price_ChildNB | number | Y | ราคาเด็กไม่มีเตียง |
| Price_Infant | number | Y | ราคาทารก |
| Price_Single_Bed | number | Y | ค่าพักเดี่ยว (0 = ไม่บวกเพิ่ม) |
| Price_Twin_Bed | number | Y | ค่าพัก Twin (0 = ไม่บวกเพิ่ม) |
| Price_Double_Bed | number | Y | ค่าพัก Double |
| Price_Triple_Bed | number | Y | ค่าพัก Triple |
| Deposit | number | Y | มัดจำ |
| PeriodConfirm | number | Y | 1 = คอนเฟิร์มเดินทาง |
| PeriodNew | number | Y | 1 = พีเรียดเปิดใหม่ |
| FuelSurcharge | number | Optional | ค่าภาษีน้ำมัน |
| ComSpecial | array | Optional | ค่าคอมพิเศษ |
