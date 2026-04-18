<?php
// ============================================================
//  Telepharmacy Dashboard — Configuration
//  โรงพยาบาลเชียงกลาง
// ============================================================

define('APP_TITLE',    'Telepharmacy Dashboard');
define('HOSPITAL_NAME','โรงพยาบาลเชียงกลาง');
define('APP_SUBTITLE', 'Health Rider Medication Tracking System');
define('DATA_FILE',    __DIR__ . '/data/telepharmacy_data.json');

// ─── ข้อมูลผู้ใช้งาน (แก้ไข password ก่อน deploy จริง) ───────
define('USERS', [
    'admin'    => password_hash('admin1234',  PASSWORD_BCRYPT),
    'pharmacy' => password_hash('pharm2024',  PASSWORD_BCRYPT),
]);
