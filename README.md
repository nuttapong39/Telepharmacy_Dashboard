# 📊 Telepharmacy Dashboard — โรงพยาบาลเชียงกลาง

## โครงสร้างไฟล์

```
telepharmacy/
├── login.php              ← หน้า Login
├── dashboard.php          ← Dashboard หลัก (3 แท็บ)
├── logout.php             ← ออกจากระบบ
├── upload.php             ← อัปโหลด Excel ใหม่ (Admin)
├── convert_excel.py       ← แปลง Excel → JSON
├── config.php             ← ตั้งค่า / รหัสผ่าน
├── data/
│   └── telepharmacy_data.json   ← ข้อมูล Dashboard
└── README.md
```

---

## 🚀 วิธี Deploy บนเว็บไซต์

### Requirements
| รายการ | Version |
|--------|---------|
| PHP    | ≥ 8.0  |
| Python3 + pandas | สำหรับ `upload.php` |
| Web Server | Apache / Nginx |

### ขั้นตอน
1. อัปโหลดโฟลเดอร์ `telepharmacy/` ไปยัง Document Root ของเซิร์ฟเวอร์
   ```
   /var/www/html/telepharmacy/        (Apache)
   /usr/share/nginx/html/telepharmacy/ (Nginx)
   ```
2. ตั้งสิทธิ์โฟลเดอร์ `data/` ให้ PHP สามารถเขียนได้:
   ```bash
   chmod 775 /var/www/html/telepharmacy/data/
   chown www-data:www-data /var/www/html/telepharmacy/data/
   ```
3. ติดตั้ง Python dependencies (สำหรับ upload):
   ```bash
   pip3 install pandas openpyxl --break-system-packages
   ```
4. เปิด URL: `https://yourdomain.com/telepharmacy/login.php`

### ตั้งค่า Default Credentials (`config.php`)
```php
// เปลี่ยน password ก่อน deploy จริงเสมอ!
define('USERS', [
    'admin'    => password_hash('YOUR_ADMIN_PASS', PASSWORD_BCRYPT),
    'pharmacy' => password_hash('YOUR_PHARM_PASS', PASSWORD_BCRYPT),
]);
```

---

## 🔄 วิธีอัปเดตข้อมูล Excel

### แนวทางที่ 1 — ผ่านหน้า Upload (แนะนำ)
1. เข้า `https://yourdomain.com/telepharmacy/upload.php`
2. เลือกไฟล์ `.xlsx` ที่อัปเดตแล้ว
3. กด **"อัปโหลดและแปลงข้อมูล"**
4. ระบบจะ re-generate `data/telepharmacy_data.json` อัตโนมัติ

> ข้อกำหนด Excel: คอลัมน์ต้องมี `ลำดับ | วันที่รับบริการ | HN | ผลการดำเนินการ telepharmacy | Medication error`

### แนวทางที่ 2 — Command Line (สำหรับ IT)
```bash
# วางไฟล์ Excel แล้วรัน
python3 /var/www/html/telepharmacy/convert_excel.py \
  /path/to/telepharmacy_new.xlsx \
  /var/www/html/telepharmacy/data/telepharmacy_data.json
```

### แนวทางที่ 3 — Cron Job (อัปเดตอัตโนมัติ)
หาก Excel วางที่ path คงที่ สามารถตั้ง cron ให้แปลงใหม่ทุกคืน:
```cron
# ทุกวัน 23:00 น.
0 23 * * * python3 /var/www/html/telepharmacy/convert_excel.py \
  /data/shared/telepharmacy.xlsx \
  /var/www/html/telepharmacy/data/telepharmacy_data.json
```

### แนวทางที่ 4 — Google Sheets + n8n (สำหรับทีมที่ใช้ n8n อยู่แล้ว)
เหมาะถ้าทีมบันทึกข้อมูลใน Google Sheets:
1. ใน n8n: สร้าง Workflow `Google Sheets → HTTP Request (POST JSON)` → trigger ทุกวัน
2. สร้าง PHP endpoint รับ JSON แล้วบันทึกลง `data/telepharmacy_data.json`

---

## 🔐 Security Tips
- เปลี่ยน password ใน `config.php` ก่อน deploy
- เพิ่ม `.htaccess` ป้องกันการเข้าถึง `data/` โดยตรง:
  ```apache
  <Directory /var/www/html/telepharmacy/data>
      Deny from all
  </Directory>
  ```
- ถ้าใช้ Nginx:
  ```nginx
  location /telepharmacy/data/ { deny all; }
  ```
- พิจารณาใช้ HTTPS (ผ่าน Let's Encrypt หรือ Cloudflare)

---

## 🛠 Troubleshooting

| ปัญหา | วิธีแก้ |
|-------|---------|
| `upload.php` ไม่ทำงาน | ตรวจสอบว่ามี `python3` และ `pandas` บนเซิร์ฟเวอร์ |
| Dashboard แสดงข้อมูลว่าง | ตรวจสอบ `data/telepharmacy_data.json` และสิทธิ์ไฟล์ |
| กราฟไม่แสดง | ตรวจสอบ internet (ใช้ CDN) หรือ host Chart.js เอง |
| Login loop | ลบ session และตรวจ `session_start()` ใน PHP |
