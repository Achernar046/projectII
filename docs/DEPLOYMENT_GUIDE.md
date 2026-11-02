# คู่มือการนำระบบขึ้น Hosting และใช้งานจริง (Deployment Guide)
**ระบบบริหารจัดการธุรกิจ Northwind BMS**

---

## 1. ข้อกำหนดของเซิร์ฟเวอร์ (Server Requirements)
- **PHP:** เวอร์ชั่น 7.4, 8.0, 8.1 หรือ 8.2+
- **MySQL / MariaDB:** เวอร์ชั่น 5.7+ หรือ 10.3+
- **PHP Extensions:** `pdo_mysql`, `mbstring`, `json`, `hash`
- **Apache Web Server:** เปิดใช้งานโมดูล `mod_rewrite` สำหรับ Clean RESTful URLs

---

## 2. วิธีการนำขึ้น Hosting (cPanel / Hostinger / InfinityFree / SiteGround)

### ขั้นตอนที่ 1: สร้างฐานข้อมูลและนำเข้าข้อมูล
1. เข้าสู่ระบบควบคุม Hosting (เช่น cPanel, DirectAdmin หรือ phpMyAdmin)
2. ไปที่เมนู **MySQL Databases** สร้างฐานข้อมูลใหม่ เช่น `northwind_bms`
3. สร้างผู้ใช้ฐานข้อมูล (Database User) และกำหนดสิทธิ์แบบ **All Privileges**
4. เปิด **phpMyAdmin** เลือกฐานข้อมูลที่สร้างขึ้น
5. คลิกแท็บ **Import (นำเข้า)** เลือกไฟล์ `database/db_northwind_complete.sql` แล้วกด **Go (ดำเนินการ)**

### ขั้นตอนที่ 2: อัปโหลดไฟล์ขึ้น Hosting
1. เข้าไปที่ **File Manager** ของโฮสติ้ง หรือเชื่อมต่อผ่าน **FTP (FileZilla)**
2. อัปโหลดไฟล์ทั้งหมดในโฟลเดอร์ `projectII/` ไปยังไดเรกทอรี `public_html/` (หรือโฟลเดอร์ Subdomain ของท่าน)
   - ไฟล์โครงสร้างที่ต้องมี:
     ```text
     public_html/
     ├── api/
     ├── assets/
     ├── docs/
     ├── index.html
     ├── .htaccess
     └── router.php
     ```

### ขั้นตอนที่ 3: ตั้งค่าการเชื่อมต่อฐานข้อมูล
เปิดไฟล์ `api/config/database.php` และแก้ไขค่าให้ตรงกับ Hosting ของท่าน:
```php
return [
    'host'     => getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost',
    'port'     => (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306),
    'dbname'   => getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'ชื่อฐานข้อมูลบนโฮสต์',
    'username' => getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'ชื่อผู้ใช้ฐานข้อมูล',
    'password' => getenv('MYSQLPASSWORD') ?: 'รหัสผ่านฐานข้อมูล',
    'charset'  => 'utf8mb4',
    ...
];
```

---

## 3. วิธีการ Deploy บน Cloud PaaS (Railway / Render / VPS)

### ผ่าน Railway:
1. เชื่อมต่อ Git Repository กับ Railway
2. เพิ่ม **MySQL Database Service** ใน Railway
3. กำหนด Environment Variables:
   - `JWT_SECRET`: คีย์ลับสำหรับลงนาม Token
4. Railway จะส่งตัวแปร `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` ให้อัตโนมัติ ซึ่งโค้ดของระบบเขียนรองรับไว้อยู่แล้ว 100%
5. นำเข้าไฟล์ `database/db_northwind_complete.sql` เข้า MySQL บน Railway ผ่านคำสั่ง:
   ```bash
   mysql -h <MYSQLHOST> -u <MYSQLUSER> -p<MYSQLPASSWORD> <MYSQLDATABASE> < database/db_northwind_complete.sql
   ```

---

## 4. บัญชีเข้าสู่ระบบสำหรับทดสอบ (Credentials)
- **URL ระบบ:** `https://<your-public-url>/`
- **URL เอกสาร API:** `https://<your-public-url>/api/docs/index.html`
- **ชื่อผู้ใช้ (Username):** `admin`
- **รหัสผ่าน (Password):** `admin123`
*(หรือใช้ชื่อผู้ใช้ `test` รหัสผ่าน `1234`)*
