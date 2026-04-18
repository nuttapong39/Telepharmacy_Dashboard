<?php
// ============================================================
//  Upload & Convert Excel → JSON  (ต้องติดตั้ง python3 + pandas)
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel'])) {
    $file = $_FILES['excel'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx','xls'])) {
        $msg = 'รองรับเฉพาะไฟล์ .xlsx หรือ .xls เท่านั้น';
        $msgType = 'error';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $msg = 'ไฟล์ต้องไม่เกิน 10 MB';
        $msgType = 'error';
    } else {
        $tmpPath = __DIR__ . '/data/upload_tmp.' . $ext;
        move_uploaded_file($file['tmp_name'], $tmpPath);

        // Run Python converter
        $pyScript = __DIR__ . '/convert_excel.py';
        $cmd = escapeshellcmd("python3 $pyScript " . escapeshellarg($tmpPath) . ' ' . escapeshellarg(DATA_FILE));
        $output = shell_exec($cmd . ' 2>&1');

        if (strpos($output, 'OK') !== false) {
            $msg = 'อัปโหลดและแปลงข้อมูลสำเร็จแล้ว! ' . trim($output);
            $msgType = 'success';
            @unlink($tmpPath);
        } else {
            $msg = 'เกิดข้อผิดพลาด: ' . htmlspecialchars($output);
            $msgType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>อัปโหลดข้อมูล — Telepharmacy Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
* { font-family:'Sarabun',sans-serif; box-sizing:border-box; }
body {
  background: linear-gradient(160deg, #0f2d6b 0%, #1e40af 55%, #2563eb 100%);
  min-height: 100vh;
}
.upload-card {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 24px 64px rgba(15,30,80,0.28);
}
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
<div class="w-full max-w-lg upload-card p-8">

  <!-- header -->
  <div class="flex items-center gap-3 mb-6">
    <a href="dashboard.php"
      class="flex items-center justify-center w-9 h-9 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-600 transition">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
    </a>
    <img src="Logo_CKHospital.png" alt="logo" style="width:36px;height:36px;object-fit:contain;">
    <div>
      <h1 class="text-lg font-bold text-gray-800 leading-tight">อัปโหลดข้อมูล</h1>
      <p class="text-xs text-gray-400">Telepharmacy Dashboard</p>
    </div>
  </div>

  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-800">
    <strong>รูปแบบไฟล์ที่รองรับ:</strong> .xlsx, .xls<br>
    คอลัมน์: <code class="text-xs bg-blue-100 px-1 rounded">ลำดับ | วันที่รับบริการ | HN | ผลการดำเนินการ telepharmacy | Medication error</code>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <label class="block mb-2 font-semibold text-gray-700 text-sm">เลือกไฟล์ Excel (.xlsx / .xls)</label>
    <input type="file" name="excel" accept=".xlsx,.xls" required
      class="block w-full border border-gray-200 rounded-xl px-4 py-3 text-sm mb-5 cursor-pointer
             file:mr-4 file:py-1.5 file:px-4 file:rounded-lg file:border-0
             file:bg-blue-600 file:text-white file:font-semibold hover:file:bg-blue-700">

    <button type="submit"
      class="w-full py-3 font-bold rounded-xl text-white text-base transition"
      style="background:linear-gradient(135deg,#1e40af,#2563eb);box-shadow:0 4px 16px rgba(37,99,235,0.35);">
      อัปโหลดและแปลงข้อมูล
    </button>
  </form>

  <?php if ($msg): ?>
  <script>
  Swal.fire({
    icon: '<?= $msgType ?>',
    title: '<?= $msgType==="success" ? "สำเร็จ" : "เกิดข้อผิดพลาด" ?>',
    text: '<?= addslashes($msg) ?>',
    confirmButtonColor: '#2563eb',
    background: '#fff',
    customClass: { popup: 'rounded-2xl' }
  });
  </script>
  <?php endif; ?>
</div>
</body>
</html>
