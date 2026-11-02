# Northwind BMS — Business Management System (Project II)

ระบบบริหารจัดการธุรกิจ Northwind BMS พัฒนาด้วยสถาปัตยกรรม RESTful API แยก Front-End และ Back-End อย่างเป็นระบบ ตามข้อกำหนดวิชาการพัฒนาเว็บแอปพลิเคชัน

---

## 🌟 จุดเด่นและเทคโนโลยีหลัก (Tech Stack)

- **Front-End:** Bootstrap 5.3.3 + Bootstrap Icons + Chart.js (Responsive UI รองรับทั้ง Desktop, Tablet, Mobile)
- **Back-End:** Pure PHP (OOP) RESTful API **ไม่ใช้ Framework หรือ ORM สำเร็จรูป**
- **Database:** MySQL เชื่อมต่อผ่าน PDO แบบ OOP
- **Security:** ทุก SQL Query ใช้ **Prepared Statement และ Parameter Binding 100%** เพื่อป้องกัน SQL Injection
- **Authentication:** **Pure PHP JWT (RFC 7519)** เข้ารหัส HMAC-SHA256 พร้อม Bearer Token Middleware
- **Documentation:** **Interactive Swagger UI (OpenAPI 3.0)** + Postman Collection v2.1 + Markdown ละเอียด

---

## 🖥️ หน้าจอระบบครบทั้ง 12 หน้าจอ (Screens)

1. **เข้าสู่ระบบ (Login / JWT Auth):** ยืนยันตัวตน ตรวจสอบรหัสผ่าน ออก JWT Token พร้อมระบบ Auto Logout
2. **Dashboard:** แสดง KPI 4 ตัว, กราฟยอดขายรายเดือน (Chart.js Bar Chart), กราฟสัดส่วนหมวดหมู่ (Doughnut Chart), ตาราง Top 5 สินค้าขายดี และลูกค้าอันดับต้น
3. **รายการสินค้า (Products):** ตารางแสดงรายการ ค้นหาแบบเรียลไทม์ กรองตามหมวดหมู่และซัพพลายเออร์ พร้อม Pagination
4. **เพิ่ม/แก้ไขสินค้า (Product Form):** ฟอร์มพร้อม Bootstrap 5 Form Validation ป้องกันข้อมูลผิดพลาด
5. **จัดการหมวดหมู่สินค้า (Categories):** CRUD หมวดหมู่สินค้า + แสดงจำนวนสินค้าในแต่ละหมวดหมู่
6. **รายการคำสั่งซื้อ (Orders):** รายการคำสั่งซื้อ กรองตามบริษัทขนส่ง/ลูกค้า แสดงยอดรวมสุทธิ
7. **รายละเอียดคำสั่งซื้อ (Order Details):** ข้อมูลลูกค้า ที่อยู่ ขนส่ง และตารางรายการสินค้า (Order Items) พร้อมปุ่มเพิ่ม/ลบรายการในออร์เดอร์
8. **จัดการลูกค้า (Customers):** CRUD ข้อมูลลูกค้า ค้นหาตามชื่อ/ประเทศ/เมือง และป้องกันการลบหากมีประวัติสั่งซื้อ
9. **จัดการผู้จัดจำหน่าย (Suppliers):** CRUD รายชื่อซัพพลายเออร์ เบอร์โทร ที่อยู่ และสินค้าที่จัดส่ง
10. **จัดการพนักงาน (Employees):** CRUD ข้อมูลพนักงาน วันเกิด รูปโปรไฟล์ บันทึก และสถิติออร์เดอร์ที่ดูแล
11. **จัดการบริษัทขนส่ง (Shippers):** CRUD รายชื่อบริษัทขนส่ง เบอร์โทรศัพท์ และสถิติเที่ยวจัดส่ง
12. **รายงานและการวิเคราะห์ (Reports):** สรุปยอดขายแยกตามหมวดหมู่/ขนส่ง/พนักงาน พร้อมปุ่ม **Export เป็นไฟล์ CSV (รองรับภาษาไทยใน Microsoft Excel 100%)**

---

## 🚀 การติดตั้งและรันระบบบนเครื่อง (Local Installation)

### 1. ฐานข้อมูล (Database Setup)
1. เปิด **phpMyAdmin** หรือ MySQL Client
2. สร้างฐานข้อมูลชื่อ `db_northwind`
3. นำเข้าไฟล์ SQL ฉบับสมบูรณ์: [`database/db_northwind_complete.sql`](database/db_northwind_complete.sql)

### 2. รันระบบผ่าน MAMP / Apache
วางโฟลเดอร์นี้ไว้ที่ `c:/MAMP/htdocs/projectII` แล้วเปิด:
- หน้าเว็บระบบ: `http://localhost:8888/projectII/` (หรือพอร์ต Apache ของเครื่อง)
- หน้าเอกสาร API (Swagger UI): `http://localhost:8888/projectII/api/docs/index.html`

### 3. หรือรันผ่าน PHP Built-in Server
```bash
php -S localhost:8000 router.php
```
แล้วเข้าใช้งานผ่าน `http://localhost:8000`

---

## 🔐 ข้อมูลเข้าสู่ระบบเริ่มต้น (Default Credentials)

| บัญชี | Username / Email | Password | สิทธิ์ (Role) |
|---|---|---|---|
| ผู้ดูแลระบบ | `admin` (หรือ `admin@northwind.co.th`) | `admin123` | Admin |
| ผู้ใช้งานทั่วไป | `test` | `1234` | Staff |

---

## 📖 เอกสาร API และการนำขึ้น Hosting (Docs)

- **Interactive Swagger UI:** เข้าที่ `/api/docs/index.html`
- **Postman Collection:** [`docs/Northwind_BMS.postman_collection.json`](docs/Northwind_BMS.postman_collection.json)
- **เอกสาร API ละเอียดทุก Endpoint:** [`docs/API_DOCUMENTATION.md`](docs/API_DOCUMENTATION.md)
- **คู่มือการนำขึ้น Hosting / Cloud:** [`docs/DEPLOYMENT_GUIDE.md`](docs/DEPLOYMENT_GUIDE.md)
