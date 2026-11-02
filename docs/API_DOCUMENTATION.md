# Northwind BMS — RESTful API Documentation
**ระบบบริหารจัดการธุรกิจ (Northwind Business Management System)**
- เทคโนโลยี: Pure PHP (OOP) + MySQL (PDO Prepared Statements) + JWT Authentication (RFC 7519)
- มาตรฐานความปลอดภัย: ป้องกัน SQL Injection 100% ด้วย Prepared Statements และ Parameter Binding ทุกคำสั่ง
- เอกสาร Interactive (Swagger UI): เข้าถึงได้ที่ `/api/docs/index.html`
- Postman Collection: นำเข้าไฟล์ `docs/Northwind_BMS.postman_collection.json`

---

## 1. ข้อมูลทั่วไปและการยืนยันตัวตน (Authentication)

### 1.1 Base URL
- Localhost (MAMP): `http://localhost/projectII/api`
- Built-in Server: `http://localhost:8000/api`
- Production Hosting: `https://<your-domain>/api`

### 1.2 Headers ที่ใช้ในระบบ
| Header Key | ชนิดข้อมูล | คำอธิบาย |
|---|---|---|
| `Content-Type` | `application/json` | กำหนดรูปแบบข้อมูลที่ส่งไปยัง API |
| `Authorization` | `Bearer <JWT_TOKEN>` | โทเคนยืนยันตัวตนสำหรับ Endpoint ที่ต้องการสิทธิ์ (Protected Routes) |

### 1.3 ผู้ใช้งานเริ่มต้นสำหรับการทดสอบ (Default Accounts)
| ชื่อผู้ใช้ (Username) | อีเมล (Email) | รหัสผ่าน (Password) | สิทธิ์ (Role) |
|---|---|---|---|
| `admin` | `admin@northwind.co.th` | `admin123` | ผู้ดูแลระบบ (Admin) |
| `test` | `test@northwind.co.th` | `1234` | ผู้ใช้งานทั่วไป (Staff) |

---

## 2. รายละเอียด Endpoint ทั้งหมด (Endpoints Catalog)

### 🔐 หมวดที่ 1: Authentication (เข้าสู่ระบบและยืนยันตัวตน)

#### 1.1 `POST /auth/login` — เข้าสู่ระบบเพื่อรับ JWT Token
- **Headers:** `Content-Type: application/json`
- **Request Body (JSON):**
  ```json
  {
    "username": "admin",
    "password": "admin123"
  }
  ```
- **Responses:**
  - **`200 OK` (สำเร็จ):**
    ```json
    {
      "success": true,
      "message": "เข้าสู่ระบบสำเร็จ",
      "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "user": {
          "user_id": 1,
          "username": "admin",
          "email": "admin@northwind.co.th",
          "full_name": "ผู้ดูแลระบบ (Admin)",
          "role": "admin"
        }
      }
    }
    ```
  - **`401 Unauthorized` (รหัสผ่านผิด):**
    ```json
    {
      "success": false,
      "message": "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง"
    }
    ```
  - **`422 Unprocessable Entity` (ข้อมูลไม่ครบ):**
    ```json
    {
      "success": false,
      "message": "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน",
      "errors": {
        "password": ["ฟิลด์ password จำเป็นต้องระบุข้อมูล"]
      }
    }
    ```

#### 1.2 `GET /auth/me` — ตรวจสอบข้อมูลโปรไฟล์ผู้ใช้ปัจจุบัน
- **Headers:** `Authorization: Bearer <TOKEN>`
- **Responses:**
  - **`200 OK`:**
    ```json
    {
      "success": true,
      "message": "ดึงข้อมูลโปรไฟล์ผู้ใช้สำเร็จ",
      "data": {
        "user": {
          "user_id": 1,
          "username": "admin",
          "role": "admin"
        }
      }
    }
    ```
  - **`401 Unauthorized`:** โทเคนหมดอายุหรือไม่ถูกต้อง

#### 1.3 `POST /auth/logout` — ออกจากระบบ
- **Responses:** `200 OK`

---

### 📊 หมวดที่ 2: Dashboard (สถิติและชาร์ต)

#### 2.1 `GET /dashboard/kpis` — ดึงข้อมูลสรุปตัวชี้วัด (KPI Cards)
- **Responses:**
  - **`200 OK`:**
    ```json
    {
      "success": true,
      "message": "ดึงข้อมูล KPI แดชบอร์ดสำเร็จ",
      "data": {
        "total_revenue": 245120.50,
        "total_orders": 196,
        "total_products": 83,
        "total_customers": 91,
        "total_categories": 9,
        "avg_order_value": 1250.61
      }
    }
    ```

#### 2.2 `GET /dashboard/charts` — ดึงข้อมูลสำหรับเรนเดอร์กราฟ
- **Responses:**
  - **`200 OK`:**
    ```json
    {
      "success": true,
      "message": "ดึงข้อมูลชาร์ตและสถิติสำเร็จ",
      "data": {
        "top_products": [
          { "i_ProductID": 1, "c_ProductName": "Chai", "c_CategoryName": "Beverages", "total_sold": 324, "total_sales": 5832.00 }
        ],
        "category_sales": [
          { "i_CategoryID": 1, "c_CategoryName": "Beverages", "product_count": 12, "total_revenue": 45000.00 }
        ],
        "top_customers": [
          { "i_customerid": 20, "c_customername": "Ernst Handel", "c_country": "Austria", "order_count": 10 }
        ],
        "monthly_trend": [
          { "month_num": 1, "month_name": "ม.ค.", "revenue": 18500.00 }
        ]
      }
    }
    ```

---

### 📦 หมวดที่ 3: Products (จัดการสินค้า)

#### 3.1 `GET /products` — ดึงรายการสินค้า
- **Query Parameters:**
  - `page` (int, default: 1): หน้าปัจจุบัน
  - `limit` (int, default: 10): จำนวนรายการต่อหน้า
  - `search` (string): คำค้นหาชื่อสินค้า/หน่วย
  - `category_id` (int): กรองตามรหัสหมวดหมู่
  - `supplier_id` (int): กรองตามรหัสผู้จัดจำหน่าย
  - `sort` (string, default: i_ProductID): ฟิลด์เรียงลำดับ
  - `order` (string: ASC | DESC, default: DESC)
- **Responses:**
  - **`200 OK`:**
    ```json
    {
      "success": true,
      "message": "ดึงรายการสินค้าสำเร็จ",
      "data": [
        {
          "i_ProductID": 1,
          "c_ProductName": "Chai",
          "i_SupplierID": 1,
          "i_CategoryID": 1,
          "c_Unit": "10 boxes x 20 bags",
          "i_Price": 18.00,
          "c_CategoryName": "Beverages",
          "c_SupplierName": "Exotic Liquids"
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 10,
        "total_records": 83,
        "total_pages": 9
      }
    }
    ```

#### 3.2 `GET /products/{id}` — ดูรายละเอียดสินค้า
- **Path Parameter:** `id` (int, required)

#### 3.3 `POST /products` — เพิ่มสินค้าใหม่ (Protected)
- **Headers:** `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
- **Request Body:**
  ```json
  {
    "c_ProductName": "ชาเขียวมะลิพรีเมียม",
    "i_SupplierID": 1,
    "i_CategoryID": 1,
    "c_Unit": "24 ขวด x 500 ml",
    "i_Price": 25.00
  }
  ```
- **Responses:**
  - `200 OK`: สร้างสินค้าสำเร็จและส่งข้อมูลที่สร้างคืนกลับมา
  - `422 Unprocessable Entity`: Form Validation ไม่ผ่าน

#### 3.4 `PUT /products/{id}` — แก้ไขข้อมูลสินค้า (Protected)
- **Path Parameter:** `id` (int, required)
- **Request Body:** เหมือนกับ `POST /products`

#### 3.5 `DELETE /products/{id}` — ลบสินค้า (Protected)
- **Path Parameter:** `id` (int, required)
- **Responses:**
  - `200 OK`: ลบสำเร็จ
  - `409 Conflict`: สินค้ามีประวัติในออร์เดอร์ ไม่สามารถลบได้

---

### 🗂 หมวดที่ 4: Categories (จัดการหมวดหมู่สินค้า)
- `GET /categories`: ดึงหมวดหมู่ทั้งหมด พร้อมจำนวนสินค้าในหมวดหมู่
- `GET /categories/{id}`: ดูรายละเอียดหมวดหมู่และรายการสินค้าในหมวดหมู่นี้
- `POST /categories`: เพิ่มหมวดหมู่ใหม่ (`c_CategoryName`, `c_Description`)
- `PUT /categories/{id}`: แก้ไขหมวดหมู่
- `DELETE /categories/{id}`: ลบหมวดหมู่ (ระบบจะปฏิเสธหากมีสินค้าผูกอยู่)

---

### 🛒 หมวดที่ 5: Orders (จัดการคำสั่งซื้อ)
- `GET /orders`: รายการคำสั่งซื้อ พร้อมชื่อลูกค้า พนักงาน ขนส่ง และยอดรวม (รองรับกรอง `shipper_id`, `customer_id`, `search`)
- `GET /orders/{id}`: รายละเอียดคำสั่งซื้อและรายการสินค้าทั้งหมด (`items`) ในออร์เดอร์
- `POST /orders`: สร้างคำสั่งซื้อใหม่ (ใช้ Database Transaction บันทึกหัวออร์เดอร์และรายการสินค้าพร้อมกัน)
- `PUT /orders/{id}`: แก้ไขข้อมูลคำสั่งซื้อ
- `DELETE /orders/{id}`: ลบคำสั่งซื้อและรายการสินค้าที่เกี่ยวข้องแบบ Cascade Transaction
- `POST /orders/{id}/items`: เพิ่มรายการสินค้าเข้าไปในออร์เดอร์เดิม
- `DELETE /orders/{id}/items/{detailId}`: ลบรายการสินค้าออกจากออร์เดอร์

---

### 👥 หมวดที่ 6: Customers (จัดการลูกค้า)
- `GET /customers`: รายการลูกค้า (ค้นหาตามชื่อ/ประเทศ/เมือง, แบ่งหน้า)
- `GET /customers/{id}`: รายละเอียดลูกค้าและประวัติการสั่งซื้อ 5 รายการล่าสุด
- `POST /customers`: เพิ่มลูกค้าใหม่ (`c_customername`, `c_contactname`, `c_country`, ...)
- `PUT /customers/{id}`: แก้ไขข้อมูลลูกค้า
- `DELETE /customers/{id}`: ลบลูกค้า (ป้องกันการลบหากมีประวัติการสั่งซื้อ)

---

### 🏭 หมวดที่ 7: Suppliers (จัดการผู้จัดจำหน่าย)
- `GET /suppliers`: รายการผู้จัดจำหน่ายพร้อมจำนวนสินค้าที่ส่ง
- `GET /suppliers/{id}`: รายละเอียดผู้จัดจำหน่ายและรายการสินค้าที่จัดส่ง
- `POST /suppliers`: เพิ่มผู้จัดจำหน่าย (`c_SupplierName`, `c_ContactName`, `c_Phone`, ...)
- `PUT /suppliers/{id}`: แก้ไขข้อมูลผู้จัดจำหน่าย
- `DELETE /suppliers/{id}`: ลบผู้จัดจำหน่าย

---

### 👤 หมวดที่ 8: Employees (จัดการพนักงาน)
- `GET /employees`: รายการพนักงานพร้อมสถิติจำนวนคำสั่งซื้อที่ดูแล
- `GET /employees/{id}`: รายละเอียดพนักงานและประวัติออร์เดอร์ล่าสุด
- `POST /employees`: เพิ่มพนักงาน (`c_FirstName`, `c_LastName`, `c_BirthDate`, ...)
- `PUT /employees/{id}`: แก้ไขข้อมูลพนักงาน
- `DELETE /employees/{id}`: ลบพนักงาน

---

### 🚚 หมวดที่ 9: Shippers (จัดการบริษัทขนส่ง)
- `GET /shippers`: รายชื่อบริษัทขนส่งและจำนวนครั้งที่จัดส่ง
- `GET /shippers/{id}`: รายละเอียดบริษัทขนส่ง
- `POST /shippers`: เพิ่มบริษัทขนส่ง (`c_ShipperName`, `c_Phone`)
- `PUT /shippers/{id}`: แก้ไขข้อมูลบริษัทขนส่ง
- `DELETE /shippers/{id}`: ลบบริษัทขนส่ง

---

### 📈 หมวดที่ 10: Reports (รายงานและการส่งออก)
- `GET /reports/sales`: รายงานวิเคราะห์ยอดขายรวม แยกตามหมวดหมู่, บริษัทขนส่ง, และพนักงานขาย
- `GET /reports/export?type=products|orders|categories`: ส่งออกไฟล์ CSV พร้อมเข้ารหัส UTF-8 BOM รองรับการเปิดด้วย Microsoft Excel โดยตัวอักษรภาษาไทยไม่เพี้ยน
