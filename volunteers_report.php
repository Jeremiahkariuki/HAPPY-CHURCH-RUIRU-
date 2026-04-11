<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$appName = "HAPPY CHURCH RUIRU";

$q        = trim((string)($_GET["q"] ?? ""));
$ministryF = trim((string)($_GET["ministry"] ?? ""));
$availF    = trim((string)($_GET["availability"] ?? ""));

$where  = [];
$params = [];

if ($q !== "") {
  $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($ministryF !== "") { $where[] = "ministry LIKE ?"; $params[] = "%$ministryF%"; }
if ($availF    !== "") { $where[] = "availability = ?"; $params[] = $availF; }

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$stmt = $pdo->prepare("
  SELECT v.full_name, v.phone, v.email, v.ministry, v.availability, e.title AS event_title, e.event_date
  FROM volunteers v
  LEFT JOIN events e ON e.id = v.event_id
  $whereSql ORDER BY v.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Volunteers Report – <?= e($appName) ?></title>
  <link rel="stylesheet" href="style.css">
  <style>
    body { background: #0b1220; color: #eaf2ff; margin: 0; padding: 40px; font-family: ui-sans-serif, system-ui, sans-serif; }
    .report-container { max-width: 1200px; margin: 0 auto; }
    .report-header { margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,.1); padding-bottom: 20px; }
    h1 { font-weight: 950; font-size: 2.5rem; letter-spacing: -1px; margin: 0; }
    .meta { color: #a9b7d0; font-size: 0.9rem; margin-top: 10px; display: flex; gap: 20px; flex-wrap: wrap; }
    .filters-box { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); padding: 15px; border-radius: 14px; margin-top: 20px; }
    .filter-tag { display: inline-block; background: rgba(124,92,255,.15); color: #7c5cff; padding: 4px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 800; margin-right: 8px; margin-top: 4px; }
    table { width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: 20px; }
    th { text-align: left; color: #a9b7d0; font-weight: 700; padding: 12px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
    td { background: rgba(255,255,255,.04); border-top: 1px solid rgba(255,255,255,.06); border-bottom: 1px solid rgba(255,255,255,.06); padding: 15px 12px; font-size: 0.95rem; }
    td:first-child { border-left: 1px solid rgba(255,255,255,.06); border-radius: 12px 0 0 12px; font-weight: 700; }
    td:last-child  { border-right: 1px solid rgba(255,255,255,.06); border-radius: 0 12px 12px 0; }
    .back-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 22px; border-radius: 12px;
      background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
      color: #eaf2ff; font-weight: 800; font-size: 0.95rem;
      text-decoration: none; cursor: pointer;
      transition: background 0.2s;
    }
    .back-btn:hover { background: rgba(124,92,255,.18); border-color: rgba(124,92,255,.35); }
    .print-btn {
      padding: 12px 24px;
      background: linear-gradient(135deg, #7c5cff, #2ee9a6);
      color: #07101f; font-weight: 950; border: none;
      border-radius: 12px; cursor: pointer; font-size: 0.95rem;
    }
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; color: #000; padding: 0; }
      td, th { border: 1px solid #ddd !important; background: transparent !important; color: #000 !important; }
      .filters-box { border: 1px solid #ddd; background: transparent; }
      .filter-tag  { border: 1px solid #ddd; background: transparent; color: #000; }
    }
  </style>
</head>
<body>
<div class="report-container">

  <!-- Top navigation bar -->
  <div class="no-print" style="display:flex; gap:12px; margin-bottom:30px; justify-content:space-between; align-items:center;">
    <button class="back-btn" onclick="window.location.href='/church_events_system/dashboard.php';">
      ← Back to Dashboard
    </button>
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
  </div>

  <div class="report-header">
    <h1>Church Volunteers Report</h1>
    <div class="meta">
      <span>📅 Generated: <strong><?= date("Y-m-d H:i") ?></strong></span>
      <span>👤 System: <strong><?= e($appName) ?></strong></span>
    </div>

    <div class="filters-box">
      <div style="font-weight:800; font-size:0.8rem; color:#a9b7d0; text-transform:uppercase; margin-bottom:8px;">Active Filters</div>
      <?php
        $active = false;
        if ($q        !== "") { echo "<span class='filter-tag'>Search: ".e($q)."</span>"; $active = true; }
        if ($ministryF !== "") { echo "<span class='filter-tag'>Ministry: ".e($ministryF)."</span>"; $active = true; }
        if ($availF   !== "") { echo "<span class='filter-tag'>Availability: ".e($availF)."</span>"; $active = true; }
        if (!$active) echo "<span style='color:#a9b7d0; font-style:italic;'>Showing all records</span>";
      ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Name</th><th>Ministry</th><th>Event</th><th>Contacts</th><th>Availability</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r["full_name"]) ?></td>
          <td><span class="pill" style="font-size:0.7rem; margin:0; padding:4px 8px;"><?= e($r["ministry"]) ?></span></td>
          <td>
            <strong style="color:#7c5cff;"><?= e($r["event_title"] ?: "General") ?></strong>
            <span style="font-size:0.75rem; opacity:0.8;"><?= $r["event_date"] ? "(".e(format_date($r["event_date"])).")" : "" ?></span>
          </td>
          <td class="small">
            <?= e($r["phone"] ?: "-") ?><br>
            <span style="opacity:0.8; font-size:0.8rem;"><?= e($r["email"] ?: "-") ?></span>
          </td>
          <td>
            <span style="color:#2ee9a6; font-weight:800; font-size:0.85rem;">📂 <?= e($r["availability"]) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5" style="text-align:center; padding:60px; color:#a9b7d0;">No records found matching these criteria.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

</div>
</body>
</html>
