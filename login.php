<?php
session_start();
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if (isset(USERS[$u]) && password_verify($p, USERS[$u])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $u;
        header('Location: dashboard.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
if (isset($_SESSION['logged_in'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_TITLE ?> — เข้าสู่ระบบ</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  * { font-family: 'Sarabun', sans-serif; }

  html {
    background: url('bg.jpg') center center / cover no-repeat fixed;
  }
  body {
    background: transparent;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
  }

  /* dark blue overlay on top of photo */
  body::before {
    content: '';
    position: fixed; inset: 0;
    background: linear-gradient(145deg,
      rgba(15,45,107,0.88) 0%,
      rgba(30,64,175,0.84) 50%,
      rgba(15,30,80,0.90) 100%);
    pointer-events: none;
    z-index: 0;
  }

  /* subtle light bloom — bottom-right */
  body::after {
    content: '';
    position: fixed;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(96,165,250,0.18), transparent 70%);
    bottom: -140px; right: -120px;
    pointer-events: none;
    z-index: 0;
  }

  @keyframes drift {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(40px, 30px) scale(1.08); }
  }

  /* grid overlay */
  .bg-grid {
    background-image:
      linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
    background-size: 36px 36px;
  }

  .card {
    background: rgba(255,255,255,0.97);
    backdrop-filter: blur(20px);
    box-shadow: 0 32px 80px rgba(15,30,80,0.35), 0 2px 8px rgba(15,30,80,0.12);
  }

  .card-header {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #3b82f6 100%);
  }

  .input-field {
    border: 1.5px solid #e2e8f0;
    background: #f8faff;
    color: #1e293b;
    transition: all 0.25s;
  }
  .input-field:focus {
    outline: none;
    border-color: #3b82f6;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.12);
  }
  .input-field::placeholder { color: #94a3b8; }

  .input-icon { color: #94a3b8; }

  .btn-login {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    box-shadow: 0 4px 18px rgba(37,99,235,0.38);
    transition: all 0.25s;
    letter-spacing: 0.02em;
  }
  .btn-login:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(37,99,235,0.45);
  }
  .btn-login:active { transform: translateY(0); box-shadow: 0 2px 10px rgba(37,99,235,0.3); }

  .logo-pulse {
    animation: logo-pulse 2.8s ease-in-out infinite;
  }
  @keyframes logo-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.45); }
    50%      { box-shadow: 0 0 0 14px rgba(255,255,255,0); }
  }

  .label { color: #475569; }

  .fade-in { animation: fadeIn 0.55s cubic-bezier(.22,.68,0,1.2) forwards; position: relative; z-index: 1; }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(28px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
  }

  .divider {
    background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
    height: 1px;
  }

  .badge {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
  }
</style>
</head>
<body class="bg-grid flex items-center justify-center min-h-screen p-4">

<div class="w-full max-w-sm fade-in">

  <!-- card -->
  <div class="card rounded-3xl overflow-hidden">

    <!-- header band -->
    <div class="card-header px-8 pt-10 pb-8 flex flex-col items-center">
      <div class="logo-pulse rounded-2xl bg-white/20 border border-white/30 flex items-center justify-center mb-4 p-2" style="width:80px;height:80px;">
        <img src="Logo_CKHospital.png" alt="โรงพยาบาลเชียงกลาง" style="width:64px;height:64px;object-fit:contain;">
      </div>
      <h1 class="text-xl font-bold text-white tracking-wide text-center leading-snug"><?= APP_TITLE ?></h1>
      <p class="text-blue-100 text-sm font-medium mt-1 text-center"><?= HOSPITAL_NAME ?></p>
      <span class="badge text-xs font-semibold px-3 py-1 rounded-full mt-3"><?= APP_SUBTITLE ?></span>
    </div>

    <!-- form body -->
    <div class="px-8 py-7">
      <p class="text-slate-500 text-sm text-center mb-6">กรุณาเข้าสู่ระบบเพื่อดำเนินการต่อ</p>

      <form method="POST" id="loginForm" accept-charset="UTF-8">
        <div class="space-y-4">

          <!-- username -->
          <div>
            <label class="label block text-sm font-semibold mb-1.5">ชื่อผู้ใช้งาน</label>
            <div class="relative">
              <span class="input-icon absolute left-3.5 top-1/2 -translate-y-1/2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
              </span>
              <input type="text" name="username" placeholder="กรอกชื่อผู้ใช้"
                class="input-field w-full pl-10 pr-4 py-3 rounded-xl text-sm"
                required autocomplete="username">
            </div>
          </div>

          <!-- password -->
          <div>
            <label class="label block text-sm font-semibold mb-1.5">รหัสผ่าน</label>
            <div class="relative">
              <span class="input-icon absolute left-3.5 top-1/2 -translate-y-1/2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
              </span>
              <input type="password" name="password" id="pwd" placeholder="กรอกรหัสผ่าน"
                class="input-field w-full pl-10 pr-11 py-3 rounded-xl text-sm"
                required autocomplete="current-password">
              <button type="button" onclick="togglePwd()"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition-colors">
                <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- submit -->
          <button type="submit"
            class="btn-login w-full py-3.5 rounded-xl text-white font-bold text-base mt-1 flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H2.25"/>
            </svg>
            เข้าสู่ระบบ
          </button>

        </div>
      </form>

      <div class="divider mt-6 mb-4"></div>

      <p class="text-center text-slate-400 text-xs">
        &copy; <?= date('Y') ?> <?= HOSPITAL_NAME ?> &mdash; ระบบ Telepharmacy
      </p>
    </div>
  </div>

  <!-- version tag below card -->
  <p class="text-center text-blue-200/60 text-xs mt-4 tracking-wide">v2.0 &nbsp;·&nbsp; Secure Login</p>
</div>

<script>
function togglePwd() {
  const pwd = document.getElementById('pwd');
  pwd.type = pwd.type === 'password' ? 'text' : 'password';
}

<?php if ($error): ?>
Swal.fire({
  icon: 'error',
  title: 'เข้าสู่ระบบไม่สำเร็จ',
  text: '<?= addslashes($error) ?>',
  confirmButtonText: 'ลองใหม่',
  confirmButtonColor: '#2563eb',
  background: '#1e293b',
  color: '#f1f5f9',
  customClass: { popup: 'rounded-2xl' }
});
<?php endif; ?>
</script>
</body>
</html>
