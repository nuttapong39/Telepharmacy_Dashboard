<?php
// ============================================================
//  Telepharmacy Dashboard — Main
//  โรงพยาบาลเชียงกลาง
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// ─── Load & compute stats ────────────────────────────────────
$raw     = file_get_contents(DATA_FILE);
$data    = json_decode($raw, true);
$records = $data['records'];
$monthly = $data['monthly'];

$total       = count($records);
$not_miss    = count(array_filter($records, fn($r) => $r['telepharmacy'] === 'ไม่ขาดยา'));
$miss_1day   = count(array_filter($records, fn($r) => $r['telepharmacy'] === 'ขาดยา 1 วัน'));
$no_followup = count(array_filter($records, fn($r) => $r['telepharmacy'] === 'ติดตามไม่ได้'));
$med_errors  = count(array_filter($records, fn($r) => $r['medication_error'] === 'พบ'));
$success_count = $not_miss + $miss_1day;
$success_rate  = $total > 0 ? round($success_count / $total * 100, 1) : 0;
$med_err_rate  = $total > 0 ? round($med_errors / $total * 100, 1) : 0;

// ─── Month labels TH ─────────────────────────────────────────
$monthTH = [
    '01'=>'ม.ค.','02'=>'ก.พ.','03'=>'มี.ค.','04'=>'เม.ย.',
    '05'=>'พ.ค.','06'=>'มิ.ย.','07'=>'ก.ค.','08'=>'ส.ค.',
    '09'=>'ก.ย.','10'=>'ต.ค.','11'=>'พ.ย.','12'=>'ธ.ค.'
];
function monthLabel($str, $mTH) {
    [$y,$m] = explode('-', $str);
    return $mTH[$m].' '.$y;
}

// Build chart arrays
$chartLabels  = [];
$chartTotal   = [];
$chartSuccess = [];
$chartNoFU    = [];
$chartMedErr  = [];
foreach ($monthly as $row) {
    $chartLabels[]  = monthLabel($row['month'], $monthTH);
    $chartTotal[]   = $row['total'];
    $chartSuccess[] = $row['not_miss'] + $row['miss_1day'];
    $chartNoFU[]    = $row['no_followup'];
    $chartMedErr[]  = $row['med_error'];
}

$lastSync = date('d/m/Y H:i');
$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= APP_TITLE ?> — <?= HOSPITAL_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<style>
* { font-family: 'Sarabun', sans-serif; box-sizing: border-box; }
:root {
  --primary: #2563eb;
  --primary-dark: #1d4ed8;
  --success: #22c55e;
  --danger: #ef4444;
  --warning: #f97316;
  --bg: #f0f7ff;
  --card: #ffffff;
  --border: #dbeafe;
  --text: #0f172a;
  --muted: #64748b;
}

/* ── Dark Mode ── */
html.dark {
  --bg: #0c1929;
  --card: #132038;
  --border: #1e3a5f;
  --text: #e2e8f0;
  --muted: #94a3b8;
}
html.dark body { background: var(--bg); color: var(--text); }
html.dark #topbar {
  background: linear-gradient(135deg, #132038 0%, #0c1929 100%);
  border-color: #1e3a5f;
}
html.dark #topbar h1 { color: #93c5fd; }
html.dark #topbar p { color: #64748b; }
html.dark .kpi-card,
html.dark .chart-card,
html.dark .tbl-card,
html.dark .notes-card { background: var(--card); border-color: var(--border); }
html.dark thead th { background: #0c1929; color: #60a5fa; border-color: var(--border); }
html.dark tbody tr { border-color: #1e3a5f; }
html.dark tbody tr:hover { background: #1a2f4a; }
html.dark tbody td { color: var(--text); }
html.dark .filter-bar select { background: #132038; color: var(--text); border-color: #1e3a5f; }
html.dark .tbl-search { background: #132038; color: var(--text); border-color: #1e3a5f; }
html.dark .tbl-search::placeholder { color: #475569; }
html.dark .pg-btn { background: #132038; color: var(--text); border-color: #1e3a5f; }
html.dark .pg-btn:hover { background: #1e3a5f; }
html.dark .pg-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
html.dark .stat-bar-outer { background: #1e3a5f; }
html.dark .stat-row { border-color: #1e3a5f; }
html.dark .tbl-header { border-color: var(--border); }
html.dark .chart-title { color: #93c5fd; }
html.dark .notes-textarea { background: #132038; color: var(--text); border-color: #1e3a5f; }
html.dark .notes-month-btn { background: #132038; color: var(--text); border-color: #1e3a5f; }
html.dark .notes-month-btn:hover { background: #1e3a5f; color: #93c5fd; }
html.dark .notes-tip { background: #0c2040; border-color: #1e3a5f; }
html.dark .notes-tip p { color: #93c5fd; }
html.dark .kpi-label { color: var(--muted); }
html.dark .kpi-sub { color: var(--muted); }
html.dark .pagination { border-color: var(--border); }
html.dark .tbl-count { color: var(--text); }

/* ── Sidebar — Blue Gradient ── */
#sidebar {
  width: 220px;
  min-height: 100vh;
  background: linear-gradient(160deg, #0f2d6b 0%, #1e40af 55%, #2563eb 100%);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 50;
  transition: transform 0.3s cubic-bezier(.4,0,.2,1);
  box-shadow: 4px 0 28px rgba(37,99,235,0.22);
}
#sidebar .logo-area {
  padding: 24px 20px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.12);
}
#sidebar .logo-icon {
  width: 44px; height: 44px;
  background: rgba(255,255,255,0.18);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255,255,255,0.2);
}
#sidebar nav a {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 18px;
  color: rgba(255,255,255,0.65);
  border-radius: 10px;
  margin: 2px 10px;
  font-size: 14px; font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s;
}
#sidebar nav a:hover {
  background: rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.95);
}
#sidebar nav a.active {
  background: rgba(255,255,255,0.22);
  color: #fff;
  box-shadow: 0 2px 12px rgba(0,0,0,0.15);
  border: 1px solid rgba(255,255,255,0.18);
}
#sidebar .nav-icon { width: 18px; height: 18px; flex-shrink: 0; }
.user-bar {
  padding: 14px 18px;
  border-top: 1px solid rgba(255,255,255,0.12);
  margin-top: auto;
}

/* ── Mobile overlay ── */
#sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 49;
  backdrop-filter: blur(2px);
}
#sidebar-overlay.show { display: block; }

/* ── Hamburger ── */
.btn-hamburger {
  display: none;
  background: none; border: none; cursor: pointer;
  padding: 6px 8px; border-radius: 9px;
  color: #1e3a8a; align-items: center; justify-content: center;
  transition: background 0.15s;
}
.btn-hamburger:hover { background: #dbeafe; }
html.dark .btn-hamburger { color: #93c5fd; }
html.dark .btn-hamburger:hover { background: #1e3a5f; }

/* ── Main ── */
#main { margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }

/* ── Topbar ── */
#topbar {
  background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);
  border-bottom: 2px solid #bfdbfe;
  padding: 14px 28px;
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 40;
}
#topbar h1 { font-size: 22px; font-weight: 800; color: #1e3a8a; }
#topbar p  { font-size: 11px; color: var(--muted); font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase; }

/* ── Filter bar ── */
.filter-bar {
  display: flex; align-items: center; gap: 8px;
  margin-left: auto; flex-wrap: wrap;
}
.filter-bar label { font-size: 12px; font-weight: 600; color: var(--muted); }
.filter-bar select {
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 13px;
  font-family: 'Sarabun', sans-serif;
  color: var(--text);
  background: #f0f7ff;
  cursor: pointer;
}

/* ── Buttons ── */
.btn-theme {
  background: linear-gradient(135deg, #1e3a8a, #2563eb);
  color: white; font-weight: 700; font-size: 12px;
  padding: 7px 13px; border-radius: 9px; border: none;
  cursor: pointer; display: flex; align-items: center; gap: 5px;
  transition: all 0.2s;
}
.btn-theme:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-export {
  background: linear-gradient(135deg,#2563eb,#1d4ed8);
  color: white; font-weight: 700; font-size: 12px;
  padding: 7px 14px; border-radius: 9px; border: none;
  cursor: pointer; display: flex; align-items: center; gap: 6px;
  transition: all 0.2s;
}
.btn-export:hover { opacity: 0.9; transform: translateY(-1px); }

/* ── Content ── */
#content { padding: 24px 28px; flex: 1; }

/* ── Tabs ── */
.tab-section { display: none; }
.tab-section.active { display: block; }

/* ── KPI Cards ── */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 24px; }
.kpi-card {
  background: var(--card);
  border-radius: 16px;
  padding: 20px;
  border: 1px solid var(--border);
  display: flex; align-items: flex-start; gap: 14px;
  box-shadow: 0 1px 4px rgba(37,99,235,0.07);
  transition: box-shadow 0.2s, transform 0.2s;
}
.kpi-card:hover { box-shadow: 0 6px 20px rgba(37,99,235,0.13); transform: translateY(-1px); }
.kpi-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.kpi-label { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-value { font-size: 26px; font-weight: 800; color: var(--text); line-height: 1.1; margin-top: 4px; }
.kpi-sub   { font-size: 11px; color: var(--muted); margin-top: 3px; }

/* ── Chart cards ── */
.chart-card {
  background: var(--card);
  border-radius: 16px;
  border: 1px solid var(--border);
  padding: 22px 24px;
  box-shadow: 0 1px 4px rgba(37,99,235,0.07);
}
.chart-title { font-size: 14px; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.06em; }
.chart-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* ── Donut center ── */
.donut-wrap { position: relative; max-width: 260px; margin: 0 auto; }
.donut-center {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%,-50%);
  text-align: center; pointer-events: none;
}
.donut-pct   { font-size: 30px; font-weight: 800; color: var(--danger); }
.donut-lbl   { font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }

/* ── Stat rows ── */
.stat-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.stat-row:last-child { border-bottom: none; }
.stat-bar-outer { flex: 1; height: 10px; background: #dbeafe; border-radius: 99px; overflow: hidden; }
.stat-bar-inner { height: 100%; border-radius: 99px; transition: width 0.8s ease; }
.stat-row-label { font-size: 13px; font-weight: 600; color: var(--text); min-width: 120px; }
.stat-row-count { font-size: 13px; font-weight: 700; color: var(--muted); min-width: 36px; text-align: right; }

/* ── Table ── */
.tbl-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 4px rgba(37,99,235,0.07); }
.tbl-header { padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 12px; }
.tbl-count { font-size: 18px; font-weight: 700; color: var(--text); }
.tbl-search { padding: 7px 12px; border: 1px solid var(--border); border-radius: 9px; font-family: 'Sarabun',sans-serif; font-size: 13px; color: var(--text); background: var(--card); width: 220px; }
.tbl-search:focus { outline: none; border-color: var(--primary); }
table { width: 100%; border-collapse: collapse; }
thead th { background: #eff6ff; font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em; padding: 11px 16px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
tbody tr { border-bottom: 1px solid #f0f7ff; transition: background 0.15s; }
tbody tr:hover { background: #eff6ff; }
tbody td { padding: 11px 16px; font-size: 13px; color: var(--text); }
.badge {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 99px;
  font-size: 11px; font-weight: 700; letter-spacing: 0.03em;
}
.badge-success { background: #dcfce7; color: #15803d; }
.badge-fail    { background: #fef2f2; color: #dc2626; }
.badge-warn    { background: #fef9c3; color: #a16207; }
.badge-rider   { background: #dbeafe; color: #1d4ed8; }
.badge-error   { background: #fef2f2; color: #dc2626; }
.badge-ok      { background: #f0fdf4; color: #166534; }
.badge-nofu    { background: #f5f3ff; color: #6d28d9; }
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; animation: blink 1.5s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.pagination { display: flex; align-items: center; gap: 6px; padding: 14px 16px; border-top: 1px solid var(--border); flex-wrap: wrap; }
.pg-btn { padding: 5px 11px; border: 1px solid var(--border); border-radius: 7px; font-size: 12px; cursor: pointer; background: var(--card); color: var(--text); transition: all 0.15s; }
.pg-btn:hover { background: #dbeafe; }
.pg-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

/* ── Overview badge row ── */
.trend-up   { color: #22c55e; }
.trend-down { color: #ef4444; }

/* ── Monthly Notes ── */
.notes-grid { display: grid; grid-template-columns: 260px 1fr; gap: 20px; }
.notes-card {
  background: var(--card); border-radius: 16px;
  border: 1px solid var(--border); padding: 22px 24px;
  box-shadow: 0 1px 4px rgba(37,99,235,0.07);
}
.notes-month-list { display: flex; flex-direction: column; gap: 6px; max-height: 480px; overflow-y: auto; }
.notes-month-btn {
  padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border);
  background: var(--card); color: var(--text); font-family: 'Sarabun',sans-serif;
  font-size: 13px; font-weight: 500; cursor: pointer; text-align: left;
  transition: all 0.2s;
  display: flex; align-items: center; justify-content: space-between;
  width: 100%;
}
.notes-month-btn:hover { background: #dbeafe; border-color: #93c5fd; color: #1e3a8a; }
.notes-month-btn.active { background: linear-gradient(135deg,#1e40af,#2563eb); color: #fff; border-color: transparent; box-shadow: 0 2px 10px rgba(37,99,235,0.25); }
.has-note { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
.notes-month-btn.active .has-note { background: rgba(255,255,255,0.75); }
.notes-textarea {
  width: 100%; height: 220px; resize: vertical;
  border: 1px solid var(--border); border-radius: 12px;
  padding: 14px; font-family: 'Sarabun',sans-serif; font-size: 14px;
  color: var(--text); background: var(--card);
  transition: border-color 0.2s, box-shadow 0.2s;
  line-height: 1.7;
}
.notes-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.btn-save-note {
  background: linear-gradient(135deg,#1e40af,#2563eb);
  color: white; font-weight: 700; font-size: 13px;
  padding: 9px 20px; border-radius: 10px; border: none;
  cursor: pointer; transition: all 0.2s;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-save-note:hover { opacity: 0.9; transform: translateY(-1px); }
.note-saved-badge {
  display: none; align-items: center; gap: 5px;
  background: #dcfce7; color: #15803d;
  padding: 5px 12px; border-radius: 99px; font-size: 12px; font-weight: 600;
}
.note-saved-badge.show { display: inline-flex; }
.notes-tip { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 10px 14px; margin-top: 12px; }

/* ── Responsive / Mobile-First ── */
@media (max-width: 1100px) {
  .kpi-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
  .chart-grid-2 { grid-template-columns: 1fr; }
  .notes-grid { grid-template-columns: 1fr; }
  .notes-month-list { flex-direction: row; flex-wrap: wrap; max-height: none; }
  .notes-month-btn { width: auto; padding: 7px 14px; }
}
@media (max-width: 680px) {
  #sidebar { transform: translateX(-220px); }
  #sidebar.open { transform: translateX(0); }
  #main { margin-left: 0; }
  .btn-hamburger { display: flex; }
  #content { padding: 14px 12px; }
  #topbar { padding: 10px 14px; gap: 8px; }
  #topbar h1 { font-size: 17px; }
  .filter-bar { width: 100%; margin-left: 0; }
  .filter-bar label { display: none; }
  .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
  .kpi-card { padding: 14px; gap: 10px; }
  .kpi-value { font-size: 20px; }
  .kpi-icon { width: 38px; height: 38px; border-radius: 10px; }
  .kpi-icon svg { width: 18px; height: 18px; }
  .chart-card { padding: 14px 14px; }
  .btn-sync span, .btn-export span { display: none; }
  thead th { padding: 8px 10px; font-size: 10px; }
  tbody td { padding: 8px 10px; font-size: 12px; }
  .tbl-search { width: 100%; }
  .tbl-header { flex-direction: column; align-items: flex-start; }
  .notes-grid { grid-template-columns: 1fr; }
}
@media (max-width: 420px) {
  .kpi-grid { grid-template-columns: 1fr; }
  .btn-sync, .btn-export, .btn-theme { font-size: 11px; padding: 6px 9px; }
}
</style>
</head>
<body>

<!-- ════════════════════════════════ MOBILE OVERLAY ════════════════════════════════ -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ════════════════════════════════ SIDEBAR ════════════════════════════════ -->
<aside id="sidebar">
  <div class="logo-area">
    <div class="flex items-center gap-3">
      <div class="logo-icon">
        <img src="Logo_CKHospital.png" alt="โรงพยาบาลเชียงกลาง" style="width:32px;height:32px;object-fit:contain;">
      </div>
      <div>
        <p class="text-white font-bold text-sm leading-tight">Telepharmacy - รพ.เชียงกลาง</p>
        <p class="text-blue-200 text-xs">Dashboard</p>
      </div>
    </div>
  </div>

  <nav class="mt-4 flex flex-col gap-1">
    <a onclick="switchTab('overview'); closeSidebar();" id="nav-overview" class="active">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
      </svg>
      ภาพรวมระบบ
    </a>
    <a onclick="switchTab('analysis'); closeSidebar();" id="nav-analysis">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
      วิเคราะห์ผล
    </a>
    <a onclick="switchTab('table'); closeSidebar();" id="nav-table">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
      </svg>
      ตารางข้อมูล
    </a>
    <a onclick="switchTab('notes'); closeSidebar();" id="nav-notes">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
      </svg>
      บันทึกรายเดือน
    </a>

    <div style="margin:10px 12px 4px;border-top:1px solid rgba(255,255,255,0.1);"></div>

    <a href="upload.php" style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.25);" id="nav-upload">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
      </svg>
      อัปโหลดข้อมูล
    </a>
  </nav>

  <div class="user-bar">
    <div class="flex items-center gap-2 mb-3">
      <div class="w-7 h-7 rounded-full bg-blue-400 bg-opacity-30 border border-white border-opacity-25 flex items-center justify-center text-xs text-white font-bold">
        <?= strtoupper(substr($username,0,1)) ?>
      </div>
      <div>
        <p class="text-white text-xs font-semibold"><?= $username ?></p>
        <p class="text-white/40 text-[10px]">ผู้ดูแลระบบ</p>
      </div>
    </div>
    <a href="logout.php" onclick="return confirmLogout()"
       class="flex items-center gap-2 text-white/40 hover:text-red-300 text-xs transition w-full">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      ออกจากระบบ
    </a>
  </div>
</aside>

<!-- ════════════════════════════════ MAIN ════════════════════════════════ -->
<div id="main">

  <!-- TOPBAR -->
  <header id="topbar">
    <button class="btn-hamburger" onclick="toggleSidebar()" aria-label="เมนู">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    <div>
      <h1><?= APP_TITLE ?></h1>
      <p><?= HOSPITAL_NAME ?> · Health Rider Tracking</p>
    </div>

    <div class="filter-bar">
      <label>FROM</label>
      <select id="filterFrom" onchange="applyFilter()">
        <?php foreach ($monthly as $i => $row):
          [$y,$m] = explode('-', $row['month']);
          $label = $monthTH[$m].' '.$y;
        ?>
        <option value="<?= $i ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>

      <label>TO</label>
      <select id="filterTo" onchange="applyFilter()">
        <?php foreach ($monthly as $i => $row):
          [$y,$m] = explode('-', $row['month']);
          $label = $monthTH[$m].' '.$y;
        ?>
        <option value="<?= $i ?>" <?= $i===count($monthly)-1?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn-theme" onclick="toggleTheme()" title="สลับ Dark/Light Mode">
        <span id="theme-icon">🌙</span>
        <span id="theme-label">Dark</span>
      </button>

      <button class="btn-export" onclick="exportData()">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        </svg>
        <span>ส่งออกข้อมูล</span>
      </button>
    </div>
  </header>

  <!-- CONTENT -->
  <div id="content">

    <!-- ══════════════ TAB 1: ภาพรวมระบบ ══════════════ -->
    <div id="tab-overview" class="tab-section active">

      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe);">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
          </div>
          <div>
            <div class="kpi-label">Total Patient (Health Rider)</div>
            <div class="kpi-value" id="kpi-total"><?= $total ?></div>
            <div class="kpi-sub">ผู้ป่วยทั้งหมด</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon" style="background:linear-gradient(135deg,#dcfce7,#bbf7d0);">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <div class="kpi-label">Tele Success Rate</div>
            <div class="kpi-value" id="kpi-tele"><span id="kpi-success-n"><?= $success_count ?></span> <span class="text-base text-green-600 font-bold">(<?= $success_rate ?>%)</span></div>
            <div class="kpi-sub">ไม่ขาดยา / ขาดยา 1 วัน</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe);">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <div>
            <div class="kpi-label">Rider Success Rate</div>
            <div class="kpi-value" id="kpi-rider"><?= $total ?> <span class="text-base text-blue-600 font-bold">(100.0%)</span></div>
            <div class="kpi-sub">Health Rider สำเร็จทั้งหมด</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon" style="background:linear-gradient(135deg,#fef2f2,#fecaca);">
            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
          </div>
          <div>
            <div class="kpi-label">Medication Errors</div>
            <div class="kpi-value text-red-500" id="kpi-errors"><?= $med_errors ?> <span class="text-base font-bold">(<?= $med_err_rate ?>%)</span></div>
            <div class="kpi-sub">ข้อผิดพลาดทางยา</div>
          </div>
        </div>
      </div>

      <!-- Monthly Chart -->
      <div class="chart-card">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <span class="chart-title">Monthly Volume &amp; Success Trend</span>
          <div class="flex items-center gap-4 text-xs font-semibold text-gray-500">
            <span class="flex items-center gap-1.5">
              <span class="inline-block w-3 h-3 rounded-sm bg-blue-600"></span> Total Volume
            </span>
            <span class="flex items-center gap-1.5">
              <span class="inline-block w-6 h-0 border-t-2 border-dashed border-red-500"></span> Success Trend
            </span>
          </div>
        </div>
        <div style="height:300px; position:relative;">
          <canvas id="monthlyChart"></canvas>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB 2: วิเคราะห์ผล ══════════════ -->
    <div id="tab-analysis" class="tab-section">
      <div class="chart-grid-2">

        <!-- Donut: Telepharmacy -->
        <div class="chart-card">
          <p class="chart-title mb-4">สัดส่วนผลลัพธ์ Telepharmacy</p>
          <div class="donut-wrap" style="max-width:240px;">
            <canvas id="donutChart" style="max-height:240px;"></canvas>
            <div class="donut-center">
              <div class="donut-pct" id="donut-pct"><?= $success_rate ?>%</div>
              <div class="donut-lbl">Success Rate</div>
            </div>
          </div>
          <div class="flex justify-center gap-6 mt-4 text-xs font-semibold">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-600 inline-block"></span> Success</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span> Failed/No FU</span>
          </div>
        </div>

        <!-- Bar: Medication adherence -->
        <div class="chart-card">
          <p class="chart-title mb-4">สถิติการขาดยาของผู้ป่วย</p>
          <div class="mt-2 space-y-1" id="adherenceStats">
            <?php
            $cats = [
              ['ไม่ขาดยา',      $not_miss,    '#3b82f6', 'badge-success'],
              ['ขาดยา 1 วัน',   $miss_1day,   '#f97316', 'badge-warn'],
              ['ติดตามไม่ได้',  $no_followup, '#a78bfa', 'badge-nofu'],
            ];
            foreach ($cats as [$lbl, $n, $color, $badge]):
              $pct = $total > 0 ? round($n/$total*100) : 0;
            ?>
            <div class="stat-row">
              <div class="stat-row-label"><?= $lbl ?></div>
              <div class="stat-bar-outer">
                <div class="stat-bar-inner" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
              </div>
              <div class="stat-row-count"><?= $n ?></div>
            </div>
            <?php endforeach; ?>
          </div>

          <hr class="my-4 border-blue-100">
          <p class="chart-title mb-3" style="font-size:12px;">Medication Error</p>
          <?php
          $errCats = [
            ['ไม่พบ',          count(array_filter($records, fn($r)=>$r['medication_error']==='ไม่พบ')),       '#22c55e'],
            ['พบ',             $med_errors, '#ef4444'],
            ['ติดตามไม่ได้',  count(array_filter($records, fn($r)=>$r['medication_error']==='ติดตามไม่ได้')), '#a78bfa'],
          ];
          foreach ($errCats as [$lbl, $n, $color]):
            $pct = $total > 0 ? round($n/$total*100) : 0;
          ?>
          <div class="stat-row">
            <div class="stat-row-label"><?= $lbl ?></div>
            <div class="stat-bar-outer">
              <div class="stat-bar-inner" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
            </div>
            <div class="stat-row-count"><?= $n ?></div>
          </div>
          <?php endforeach; ?>

          <hr class="my-4 border-blue-100">
          <p class="chart-title mb-3" style="font-size:12px;">Monthly Breakdown</p>
          <div style="height:180px; position:relative;">
            <canvas id="stackedBar"></canvas>
          </div>
        </div>

      </div>
    </div>

    <!-- ══════════════ TAB 3: ตารางข้อมูล ══════════════ -->
    <div id="tab-table" class="tab-section">
      <div class="tbl-card">
        <div class="tbl-header">
          <div>
            <span class="tbl-count" id="tbl-count-label">ข้อมูล <?= $total ?> รายการ</span>
            <div class="flex items-center gap-1.5 mt-1">
              <div class="live-dot"></div>
              <span class="text-xs text-green-600 font-semibold">LIVE CLOUD DATA</span>
            </div>
          </div>
          <div class="flex items-center gap-3 flex-wrap">
            <select id="tblFilter" onchange="filterTable()" class="tbl-search" style="width:160px;">
              <option value="">ทุกผลลัพธ์</option>
              <option value="ไม่ขาดยา">ไม่ขาดยา</option>
              <option value="ขาดยา 1 วัน">ขาดยา 1 วัน</option>
              <option value="ติดตามไม่ได้">ติดตามไม่ได้</option>
            </select>
            <input type="text" id="tblSearch" oninput="filterTable()" placeholder="ค้นหา HN..." class="tbl-search">
          </div>
        </div>

        <div style="overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Service Date</th>
                <th>HN</th>
                <th>Health Rider</th>
                <th>Telepharmacy</th>
                <th>Outcome Detail</th>
                <th>Medication Error</th>
              </tr>
            </thead>
            <tbody id="tblBody"></tbody>
          </table>
        </div>

        <div class="pagination" id="pagination"></div>
      </div>
    </div>

    <!-- ══════════════ TAB 4: บันทึกรายเดือน ══════════════ -->
    <div id="tab-notes" class="tab-section">
      <input type="hidden" id="notes-month-key">
      <div class="notes-grid">

        <!-- Month selector -->
        <div class="notes-card">
          <p class="chart-title mb-4">เลือกเดือน</p>
          <div class="notes-month-list" id="notes-month-list"></div>
        </div>

        <!-- Note editor -->
        <div class="notes-card">
          <div class="flex items-center justify-between mb-1 flex-wrap gap-3">
            <p class="chart-title">บันทึกประจำเดือน</p>
            <div class="flex items-center gap-3">
              <span class="note-saved-badge" id="note-saved-badge">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                บันทึกแล้ว
              </span>
              <button class="btn-save-note" onclick="saveNote()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                บันทึก
              </button>
            </div>
          </div>
          <p class="text-xs mb-3" style="color:var(--muted);">บันทึกข้อความ / หมายเหตุ / ข้อสังเกตของเภสัชกรสำหรับเดือนที่เลือก</p>
          <textarea
            id="notes-textarea"
            class="notes-textarea"
            placeholder="พิมพ์บันทึกรายเดือนที่นี่...&#10;เช่น: ผู้ป่วยรายใหม่, ข้อสังเกต, ปัญหาที่พบ, แผนการติดตาม..."
          ></textarea>
          <div class="notes-tip">
            <p class="text-xs font-semibold" style="color:#1d4ed8;">💡 หมายเหตุ</p>
            <p class="text-xs mt-1" style="color:#3b82f6;">บันทึกจะถูกเก็บไว้บนเครื่องนี้ (localStorage) กด <strong>บันทึก</strong> เพื่อบันทึกข้อมูล จุดสีเขียวแสดงว่าเดือนนั้นมีบันทึกอยู่แล้ว</p>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ════════════════════════════════ SCRIPTS ════════════════════════════════ -->
<script>
// ── Raw data injected from PHP ───────────────────────────────
const RAW_RECORDS = <?= json_encode($records, JSON_UNESCAPED_UNICODE) ?>;
const MONTHLY     = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const CHART_LABELS = <?= json_encode($chartLabels) ?>;
const CHART_TOTAL  = <?= json_encode($chartTotal) ?>;
const CHART_SUC    = <?= json_encode($chartSuccess) ?>;
const CHART_NOFU   = <?= json_encode($chartNoFU) ?>;

let filteredRecords = [...RAW_RECORDS];
let currentPage = 1;
const PAGE_SIZE = 20;

// ── Charts ──────────────────────────────────────────────────

// Monthly bar+line
const ctx1 = document.getElementById('monthlyChart').getContext('2d');
let monthlyChart = new Chart(ctx1, {
  type: 'bar',
  data: {
    labels: CHART_LABELS,
    datasets: [
      {
        label: 'Total Volume',
        data: CHART_TOTAL,
        backgroundColor: 'rgba(37,99,235,0.82)',
        borderRadius: 6,
        order: 2,
      },
      {
        label: 'Success Trend',
        data: CHART_SUC,
        type: 'line',
        borderColor: '#ef4444',
        borderDash: [5,4],
        pointBackgroundColor: '#fff',
        pointBorderColor: '#ef4444',
        pointBorderWidth: 2,
        pointRadius: 5,
        tension: 0.35,
        fill: false,
        order: 1,
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { family:'Sarabun', size:11 } } },
      y: { beginAtZero: true, grid: { color:'rgba(0,0,0,0.05)' }, ticks: { font: { family:'Sarabun', size:11 } } }
    }
  }
});

// Donut
const ctx2 = document.getElementById('donutChart').getContext('2d');
const successN = <?= $success_count ?>;
const failN    = <?= $no_followup ?>;
let donutChart = new Chart(ctx2, {
  type: 'doughnut',
  data: {
    labels: ['Success','Failed / No FU'],
    datasets: [{
      data: [successN, failN],
      backgroundColor: ['#2563eb','#ef4444'],
      borderWidth: 3,
      borderColor: '#fff',
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    cutout: '68%',
    plugins: { legend: { display: false }, tooltip: { callbacks: {
      label: ctx => ` ${ctx.label}: ${ctx.raw} (${((ctx.raw/(successN+failN))*100).toFixed(1)}%)`
    }}}
  }
});

// Stacked bar
const ctx3 = document.getElementById('stackedBar').getContext('2d');
let stackedBar = new Chart(ctx3, {
  type: 'bar',
  data: {
    labels: CHART_LABELS,
    datasets: [
      { label: 'ไม่ขาดยา',     data: MONTHLY.map(r=>r.not_miss),    backgroundColor:'#3b82f6', borderRadius:4 },
      { label: 'ติดตามไม่ได้', data: MONTHLY.map(r=>r.no_followup), backgroundColor:'#a78bfa', borderRadius:4 },
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position:'bottom', labels:{ font:{ family:'Sarabun',size:10 }, boxWidth:10, padding:10 }}},
    scales: {
      x: { stacked:true, grid:{display:false}, ticks:{font:{family:'Sarabun',size:9}} },
      y: { stacked:true, beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{font:{family:'Sarabun',size:9}} }
    }
  }
});

// ── Dark Mode ────────────────────────────────────────────────
function initTheme() {
  const saved = localStorage.getItem('telepharmacy_theme');
  if (saved === 'dark') {
    document.documentElement.classList.add('dark');
    document.getElementById('theme-icon').textContent = '☀️';
    document.getElementById('theme-label').textContent = 'Light';
  }
}

function toggleTheme() {
  const html = document.documentElement;
  const isDark = html.classList.toggle('dark');
  localStorage.setItem('telepharmacy_theme', isDark ? 'dark' : 'light');
  document.getElementById('theme-icon').textContent = isDark ? '☀️' : '🌙';
  document.getElementById('theme-label').textContent = isDark ? 'Light' : 'Dark';
}

// ── Mobile Sidebar ────────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('show');
}

// ── Tab switching ────────────────────────────────────────────
function switchTab(name) {
  ['overview','analysis','table','notes'].forEach(t => {
    document.getElementById('tab-'+t).classList.remove('active');
    document.getElementById('nav-'+t).classList.remove('active');
  });
  document.getElementById('tab-'+name).classList.add('active');
  document.getElementById('nav-'+name).classList.add('active');
  if (name === 'table') renderTable();
  if (name === 'notes') initNotes();
}

// ── Date filter ──────────────────────────────────────────────
function applyFilter() {
  const from = parseInt(document.getElementById('filterFrom').value);
  const to   = parseInt(document.getElementById('filterTo').value);
  if (from > to) {
    Swal.fire({ icon:'warning', title:'ช่วงเวลาไม่ถูกต้อง',
      text:'กรุณาเลือก FROM ก่อน TO', confirmButtonColor:'#2563eb',
      background: document.documentElement.classList.contains('dark') ? '#132038' : '#fff',
      customClass:{popup:'rounded-2xl'} });
    return;
  }
  const slicedMonths = MONTHLY.slice(from, to+1);
  const sliceLabels  = CHART_LABELS.slice(from, to+1);
  const sliceTotal   = CHART_TOTAL.slice(from, to+1);
  const sliceSuc     = CHART_SUC.slice(from, to+1);

  monthlyChart.data.labels   = sliceLabels;
  monthlyChart.data.datasets[0].data = sliceTotal;
  monthlyChart.data.datasets[1].data = sliceSuc;
  monthlyChart.update();

  stackedBar.data.labels = sliceLabels;
  stackedBar.data.datasets[0].data = slicedMonths.map(r=>r.not_miss);
  stackedBar.data.datasets[1].data = slicedMonths.map(r=>r.no_followup);
  stackedBar.update();

  const from_date = MONTHLY[from].month + '-01';
  const to_date   = MONTHLY[to].month   + '-31';
  filteredRecords = RAW_RECORDS.filter(r => r.service_date >= from_date && r.service_date <= to_date);
  updateKPIs();
  renderTable();
}

function updateKPIs() {
  const t     = filteredRecords.length;
  const nm    = filteredRecords.filter(r=>r.telepharmacy==='ไม่ขาดยา').length;
  const m1    = filteredRecords.filter(r=>r.telepharmacy==='ขาดยา 1 วัน').length;
  const nfu   = filteredRecords.filter(r=>r.telepharmacy==='ติดตามไม่ได้').length;
  const merr  = filteredRecords.filter(r=>r.medication_error==='พบ').length;
  const succ  = nm + m1;
  const rate  = t>0 ? (succ/t*100).toFixed(1) : 0;
  const errR  = t>0 ? (merr/t*100).toFixed(1) : 0;

  document.getElementById('kpi-total').textContent = t;
  document.getElementById('kpi-tele').innerHTML =
    `<span id="kpi-success-n">${succ}</span> <span class="text-base text-green-600 font-bold">(${rate}%)</span>`;
  document.getElementById('kpi-rider').innerHTML =
    `${t} <span class="text-base text-blue-600 font-bold">(100.0%)</span>`;
  document.getElementById('kpi-errors').innerHTML =
    `${merr} <span class="text-base font-bold">(${errR}%)</span>`;
  document.getElementById('donut-pct').textContent = rate + '%';

  donutChart.data.datasets[0].data = [succ, nfu];
  donutChart.update();
}

// ── Table rendering ──────────────────────────────────────────
function getBadge(val) {
  if (val==='ไม่ขาดยา')    return `<span class="badge badge-success">ไม่ขาดยา</span>`;
  if (val==='ขาดยา 1 วัน') return `<span class="badge badge-warn">ขาดยา 1 วัน</span>`;
  if (val==='ติดตามไม่ได้') return `<span class="badge badge-nofu">ติดตามไม่ได้</span>`;
  return `<span class="badge" style="background:#f1f5f9;color:#64748b;">${val}</span>`;
}
function getErrBadge(val) {
  if (val==='ไม่พบ')        return `<span class="badge badge-ok">ไม่พบ</span>`;
  if (val==='พบ')           return `<span class="badge badge-error">พบ</span>`;
  return `<span class="badge badge-nofu">${val}</span>`;
}
function formatDate(d) {
  const [y,m,day] = d.split('-');
  const months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  return `${parseInt(day)}-${months[parseInt(m)]}-${y}`;
}

function filterTable() {
  const q   = document.getElementById('tblSearch').value.toLowerCase();
  const flt = document.getElementById('tblFilter').value;
  const from = parseInt(document.getElementById('filterFrom').value);
  const to   = parseInt(document.getElementById('filterTo').value);
  const from_date = MONTHLY[from].month + '-01';
  const to_date   = MONTHLY[to].month   + '-31';
  filteredRecords = RAW_RECORDS.filter(r => {
    const inDate  = r.service_date >= from_date && r.service_date <= to_date;
    const inFlt   = !flt || r.telepharmacy === flt;
    const inSearch= !q   || r.hn.toLowerCase().includes(q);
    return inDate && inFlt && inSearch;
  });
  currentPage = 1;
  renderTable();
}

function renderTable() {
  const total = filteredRecords.length;
  document.getElementById('tbl-count-label').textContent = `ข้อมูล ${total} รายการ`;
  const pages  = Math.ceil(total / PAGE_SIZE) || 1;
  const start  = (currentPage-1)*PAGE_SIZE;
  const rows   = filteredRecords.slice(start, start+PAGE_SIZE);

  const tbody = document.getElementById('tblBody');
  tbody.innerHTML = rows.map((r,i) => `
    <tr>
      <td class="text-gray-400 text-xs">${start+i+1}</td>
      <td>${formatDate(r.service_date)}</td>
      <td><strong>${r.hn}</strong></td>
      <td><span class="badge badge-rider">สำเร็จ</span></td>
      <td>${getBadge(r.telepharmacy)}</td>
      <td>${getBadge(r.telepharmacy)}</td>
      <td>${getErrBadge(r.medication_error)}</td>
    </tr>
  `).join('');

  const pg = document.getElementById('pagination');
  let html = `<span class="text-xs mr-2" style="color:var(--muted);">หน้า ${currentPage}/${pages} (${total} รายการ)</span>`;
  const maxShow = 7;
  const startPg = Math.max(1, currentPage-3);
  const endPg   = Math.min(pages, startPg+maxShow-1);
  if (currentPage>1) html += `<button class="pg-btn" onclick="goPage(${currentPage-1})">‹</button>`;
  for (let p=startPg; p<=endPg; p++) {
    html += `<button class="pg-btn ${p===currentPage?'active':''}" onclick="goPage(${p})">${p}</button>`;
  }
  if (currentPage<pages) html += `<button class="pg-btn" onclick="goPage(${currentPage+1})">›</button>`;
  pg.innerHTML = html;
}

function goPage(p) { currentPage=p; renderTable(); }

// ── Monthly Notes ─────────────────────────────────────────────
const NOTES_KEY = 'telepharmacy_notes';
let notesInitialized = false;

function loadNotes() {
  try { return JSON.parse(localStorage.getItem(NOTES_KEY) || '{}'); }
  catch { return {}; }
}

function initNotes() {
  if (notesInitialized) return;
  notesInitialized = true;
  renderNotesList();
  if (MONTHLY.length > 0) {
    const lastMonth = MONTHLY[MONTHLY.length - 1].month;
    selectNoteMonth(lastMonth);
  }
}

function renderNotesList() {
  const notes = loadNotes();
  const thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  const list = document.getElementById('notes-month-list');
  list.innerHTML = MONTHLY.map((row, i) => {
    const [y, m] = row.month.split('-');
    const label = `${thMonths[parseInt(m)]} ${y}`;
    const dot = notes[row.month] ? '<span class="has-note"></span>' : '';
    const isLast = i === MONTHLY.length - 1;
    return `<button class="notes-month-btn${isLast?' active':''}"
      onclick="selectNoteMonth('${row.month}', this)"
      id="nb-${row.month}">
      <span>${label}</span>${dot}
    </button>`;
  }).join('');
}

function selectNoteMonth(month, btn) {
  document.querySelectorAll('.notes-month-btn').forEach(b => b.classList.remove('active'));
  const target = btn || document.getElementById('nb-' + month);
  if (target) target.classList.add('active');
  document.getElementById('notes-month-key').value = month;
  const notes = loadNotes();
  document.getElementById('notes-textarea').value = notes[month] || '';
  document.getElementById('note-saved-badge').classList.remove('show');
}

function saveNote() {
  const month = document.getElementById('notes-month-key').value;
  if (!month) return;
  const text = document.getElementById('notes-textarea').value;
  const notes = loadNotes();
  if (text.trim()) {
    notes[month] = text;
  } else {
    delete notes[month];
  }
  localStorage.setItem(NOTES_KEY, JSON.stringify(notes));

  const btn = document.getElementById('nb-' + month);
  if (btn) {
    const existing = btn.querySelector('.has-note');
    if (text.trim() && !existing) {
      btn.insertAdjacentHTML('beforeend', '<span class="has-note"></span>');
    } else if (!text.trim() && existing) {
      existing.remove();
    }
  }

  const badge = document.getElementById('note-saved-badge');
  badge.classList.add('show');
  setTimeout(() => badge.classList.remove('show'), 2200);
}

// ── Sync & Export ────────────────────────────────────────────
function swalBg() {
  return document.documentElement.classList.contains('dark') ? '#132038' : '#fff';
}


function exportData() {
  Swal.fire({
    title: 'ส่งออกข้อมูล',
    html: `
      <p class="text-sm text-gray-500 mb-3">เลือกรูปแบบไฟล์ที่ต้องการ</p>
      <div class="flex flex-col gap-2">
        <button onclick="doExport('csv'); Swal.close();"
          class="w-full py-2.5 bg-green-600 text-white rounded-xl font-semibold text-sm hover:bg-green-700">
          📊 ส่งออก CSV
        </button>
        <button onclick="doExport('excel'); Swal.close();"
          class="w-full py-2.5 text-white rounded-xl font-semibold text-sm"
          style="background:#217346;">
          📗 ส่งออก Excel (.xlsx)
        </button>
        <button onclick="doExport('print'); Swal.close();"
          class="w-full py-2.5 bg-blue-600 text-white rounded-xl font-semibold text-sm hover:bg-blue-700">
          🖨️ พิมพ์รายงาน / บันทึก PDF
        </button>
      </div>`,
    showConfirmButton: false, showCloseButton: true,
    background: swalBg(), customClass:{popup:'rounded-2xl'}
  });
}

function doExport(type) {
  const headers = ['ลำดับ','วันที่รับบริการ','HN','ผลการดำเนินการ telepharmacy','Medication error'];
  const rows = filteredRecords.map((r,i) => [i+1, r.service_date, r.hn, r.telepharmacy, r.medication_error]);

  if (type === 'csv') {
    const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob(['\uFEFF'+csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    a.download = 'telepharmacy_data.csv'; a.click();

  } else if (type === 'excel') {
    const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
    ws['!cols'] = [6,14,10,28,18].map(w => ({wch:w}));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'ข้อมูล Telepharmacy');
    XLSX.writeFile(wb, 'telepharmacy_data.xlsx');

  } else if (type === 'print') {
    const tableRows = rows.map(r =>
      `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`
    ).join('');
    const now = new Date().toLocaleDateString('th-TH', {year:'numeric',month:'long',day:'numeric'});
    const html = `<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายงาน Telepharmacy</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{font-family:'Sarabun',sans-serif;box-sizing:border-box;}
  body{margin:20px;color:#0f172a;font-size:13px;}
  .report-header{display:flex;align-items:center;gap:14px;margin-bottom:6px;}
  .report-header img{width:56px;height:56px;object-fit:contain;}
  h1{font-size:17px;font-weight:700;margin:0;}
  .meta{font-size:11px;color:#64748b;margin-bottom:14px;}
  table{width:100%;border-collapse:collapse;font-size:12px;}
  thead th{background:#1e40af;color:#fff;padding:7px 10px;text-align:left;font-weight:700;}
  tbody tr:nth-child(even){background:#eff6ff;}
  tbody td{padding:6px 10px;border-bottom:1px solid #e2e8f0;}
  tfoot td{padding:6px 10px;font-weight:600;color:#1e40af;border-top:2px solid #1e40af;}
  @media print{
    body{margin:10mm 12mm;}
    @page{size:A4;margin:10mm;}
  }
</style>
</head>
<body>
<div class="report-header">
  <img src="Logo_CKHospital.png" alt="logo">
  <div>
    <h1>รายงาน Telepharmacy — Health Rider</h1>
    <p style="margin:2px 0 0;font-size:12px;color:#475569;">โรงพยาบาลเชียงกลาง</p>
  </div>
</div>
<div class="meta">พิมพ์เมื่อ: ${now} &nbsp;·&nbsp; จำนวน ${rows.length} รายการ</div>
<table>
<thead><tr>${headers.map(h=>`<th>${h}</th>`).join('')}</tr></thead>
<tbody>${tableRows}</tbody>
<tfoot><tr><td colspan="5">รวมทั้งหมด ${rows.length} รายการ</td></tr></tfoot>
</table>
</body>
</html>`;
    const w = window.open('', '_blank', 'width=960,height=720');
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 600);
  }
}

function confirmLogout() {
  Swal.fire({
    title: 'ออกจากระบบ?',
    text: 'คุณต้องการออกจากระบบใช่หรือไม่',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'ออกจากระบบ',
    cancelButtonText: 'ยกเลิก',
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    background: swalBg(),
    customClass: { popup:'rounded-2xl' }
  }).then(r => { if (r.isConfirmed) window.location.href='logout.php'; });
  return false;
}

// ── Init ──────────────────────────────────────────────────────
initTheme();
applyFilter();
</script>
</body>
</html>
