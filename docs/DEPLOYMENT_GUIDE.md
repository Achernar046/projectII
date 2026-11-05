# คู่มือการนำระบบขึ้น Hosting และใช้งานจริง (Deployment Guide)
**ระบบบริหารจัดการธุรกิจ Northwind BMS**

---

## 1. ข้อกำหนดของเซิร์ฟเวอร์ (Server Requirements)
- **PHP:** เวอร์ชั่น 8.0 ขึ้นไป (แนะนำ PHP 8.3)
- **MySQL / MariaDB:** เวอร์ชั่น 5.7+ หรือ 10.3+
- **PHP Extensions:** `pdo_mysql`, `mbstring`, `json`, `hash`
- **Apache Web Server:** เปิดใช้งานโมดูล `mod_rewrite` สำหรับ Clean RESTful URLs

---

## 2. วิธีการนำขึ้น Hosting (cPanel / Hostinger / SiteGround)

### ขั้นตอนที่ 1: สร้างฐานข้อมูลและนำเข้าข้อมูล
1. เข้าสู่ระบบควบคุม Hosting (เช่น cPanel, DirectAdmin หรือ phpMyAdmin)
2. ไปที่เมนู **MySQL Databases** สร้างฐานข้อมูลใหม่ เช่น `northwind_bms`
3. สร้างผู้ใช้ฐานข้อมูล (Database User) และกำหนดสิทธิ์แบบ **All Privileges**
4. เปิด **phpMyAdmin** เลือกฐานข้อมูลที่สร้างขึ้น
5. คลิกแท็บ **Import (นำเข้า)** เลือกไฟล์ `database/db_northwind_complete.sql` แล้วกด **Go (ดำเนินการ)**

### ขั้นตอนที่ 2: อัปโหลดไฟล์ขึ้น Hosting
1. เข้าไปที่ **File Manager** ของโฮสติ้ง หรือเชื่อมต่อผ่าน **FTP (FileZilla)**
2. อัปโหลดไฟล์แอปที่จำเป็นไปยังไดเรกทอรี `public_html/` (หรือโฟลเดอร์ Subdomain ของท่าน) โดยไม่ต้องอัปโหลด `.git/`, ไฟล์ SQL, หรือไฟล์ตั้งค่าลับตัวอย่าง
   - ไฟล์โครงสร้างที่ต้องมี:
     ```text
     public_html/
     ├── .htaccess
     ├── index.html
     ├── assets/
     └── api/
         ├── config/
         │   ├── .htaccess
         │   ├── database.local.php
         │   └── jwt.local.php
         └── ...
     ```

### ขั้นตอนที่ 3: ตั้งค่าการเชื่อมต่อฐานข้อมูล
คัดลอก `api/config/database.local.example.php` เป็น `database.local.php` และ `api/config/jwt.local.example.php` เป็น `jwt.local.php` จากนั้นใส่ค่าจริงในไฟล์ local ทั้งสองไฟล์ ไฟล์เหล่านี้ถูกระบุใน `.gitignore` แล้ว ห้าม commit ขึ้น Git

---

## 3. ขั้นตอนเฉพาะสำหรับ InfinityFree

1. สร้างเว็บไซต์และฐานข้อมูล MySQL จาก Control Panel ของ InfinityFree แล้วนำเข้า `database/db_northwind_complete.sql` ผ่าน phpMyAdmin
2. คัดลอกค่า **MySQL Hostname, Database Name, Username และ Password** ตามที่ Control Panel แสดงไปใส่ใน `api/config/database.local.php` โดยใช้ hostname ที่ให้มา (อย่าใช้ `localhost`)
3. สร้าง secret ใหม่สำหรับ JWT ด้วย PHP เช่น `bin2hex(random_bytes(64))` แล้วใส่ผลลัพธ์ใน `api/config/jwt.local.php`
4. เปลี่ยนรหัสผ่านบัญชี seed ทุกบัญชี หรือเอาบัญชีทดสอบที่ไม่ใช้ทิ้ง ระบบ seed มีรหัสตัวอย่าง `admin123` และ `1234` และรองรับคอลัมน์รหัสผ่านแบบ plain text สำหรับข้อมูลเก่า
   - สร้าง bcrypt hash บนเครื่องที่มี PHP: `password_hash('รหัสผ่านใหม่', PASSWORD_DEFAULT)`
   - บันทึก hash ลง `password_hash` และตั้ง `c_user_pwd` เป็น `NULL` ใน `tb_users` ผ่าน phpMyAdmin
5. อัปโหลดไฟล์แอปไปที่ `htdocs/` โดยเก็บ `.htaccess`, `api/`, `assets/`, `index.html` และไฟล์ `database.local.php` / `jwt.local.php` ที่ตั้งค่าแล้ว
6. ไม่ต้องอัปโหลด `.git/`, ไฟล์ SQL ใน `database/`, `README.md`, `Dockerfile`, `render.yaml` หรือ `docs/` ระดับราก (โฟลเดอร์ `api/docs/` เป็นเอกสาร API และจะเก็บไว้ก็ได้)
7. ตรวจว่า `api/config/.htaccess` ถูกอัปโหลดด้วย (และถ้า `database/` ถูกอัปโหลด ให้แน่ใจว่ามี `database/.htaccess`) จากนั้นเปิดหน้าเว็บและลอง Login, Dashboard, รายการพนักงาน, ลูกค้า และผู้จัดจำหน่าย

InfinityFree free hosting ไม่รองรับการตั้ง environment variables ใน PHP ดังนั้นแอปจะอ่านค่าจากไฟล์ `*.local.php` ที่สร้างไว้แทน ไฟล์ config ถูกปิดการเข้าถึงโดยตรงด้วย `.htaccess`; อย่าลบกฎนี้

## 4. วิธีการ Deploy บน Cloud PaaS (Railway / Render / VPS)

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

## 5. บัญชีเข้าสู่ระบบสำหรับทดสอบ (Credentials)
- **URL ระบบ:** `https://<your-public-url>/`
- **URL เอกสาร API:** `https://<your-public-url>/api/docs/index.html`
- **ชื่อผู้ใช้ (Username):** `admin`
- **รหัสผ่าน (Password):** `admin123`
*(หรือใช้ชื่อผู้ใช้ `test` รหัสผ่าน `1234`)*
