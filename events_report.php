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
$basePath = rtrim(str_replace("\\", "/", dirname((string)($_SERVER["SCRIPT_NAME"] ?? ""))), "/");
$dashboardHref = ($basePath === "" ? "" : $basePath) . "/dashboard.php";

$q = trim((string)($_GET["q"] ?? ""));
$statusF = trim((string)($_GET["status"] ?? ""));
$from = trim((string)($_GET["from"] ?? ""));
$to = trim((string)($_GET["to"] ?? ""));

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(title LIKE ? OR location LIKE ? OR category LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusF !== "") { $where[] = "status = ?"; $params[] = $statusF; }
if ($from !== "") { $where[] = "event_date >= ?"; $params[] = $from; }
if ($to !== "") { $where[] = "event_date <= ?"; $params[] = $to; }

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$stmt = $pdo->prepare("SELECT title,event_date,start_time,end_time,location,category,status FROM events $whereSql ORDER BY created_at DESC, id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Events Report - <?= e($appName) ?></title>
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
    td:last-child { border-right: 1px solid rgba(255,255,255,.06); border-radius: 0 12px 12px 0; }
    .status-badge { font-weight: 800; font-size: 0.8rem; }
    @media print {
      .no-print { display: none; }
      body { background: #fff; color: #000; padding: 0; }
      td, th { border: 1px solid #ddd; background: transparent; color: #000; }
      .filters-box { border: 1px solid #ddd; background: transparent; }
      .filter-tag { border: 1px solid #ddd; background: transparent; color: #000; }
    }
  </style>
</head>
<body>
  <div class="report-container">
    <div class="no-print" style="display:flex; gap:12px; margin-bottom:30px; justify-content: space-between; align-items: center;">
      <a class="btn btn-ghost" href="<?= e($dashboardHref) ?>" onclick="window.top.location.href='<?= e($dashboardHref) ?>'; return false;" style="display:flex; align-items:center; gap:8px;">
        ← Back to Dashboard
      </a>
      <button class="btn" onclick="window.print()" style="padding: 12px 24px; background: linear-gradient(135deg, var(--brand), var(--brand2)); color: #07101f; font-weight: 950; border: none;">
        🖨️ Print Report
      </button>
    </div>

    <div class="report-header">
      <h1>Church Events Report</h1>
      <div class="meta">
        <span>📅 Generated: <strong><?= date("Y-m-d H:i") ?></strong></span>
        <span>👤 System: <strong><?= e($appName) ?></strong></span>
      </div>

      <div class="filters-box">
        <div style="font-weight: 800; font-size: 0.8rem; color: #a9b7d0; text-transform: uppercase; margin-bottom: 8px;">Active Filters</div>
        <?php
          $active = false;
          if ($q !== "") { echo "<span class='filter-tag'>Search: $q</span>"; $active = true; }
          if ($statusF !== "") { echo "<span class='filter-tag'>Status: $statusF</span>"; $active = true; }
          if ($from !== "") { echo "<span class='filter-tag'>From: $from</span>"; $active = true; }
          if ($to !== "") { echo "<span class='filter-tag'>To: $to</span>"; $active = true; }
          if (!$active) echo "<span style='color:var(--muted); font-style:italic;'>Showing all records</span>";
        ?>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Title</th><th>Date</th><th>Start</th><th>End</th><th>Location</th><th>Category</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r["title"]) ?></td>
            <td><?= e($r["event_date"]) ?></td>
            <td><?= e($r["start_time"] ? substr($r["start_time"], 0, 5) : "-") ?></td>
            <td><?= e($r["end_time"] ? substr($r["end_time"], 0, 5) : "-") ?></td>
            <td><?= e($r["location"]) ?></td>
            <td><?= e($r["category"]) ?></td>
            <td>
              <?php
                $color = ["Scheduled"=>"var(--brand)", "Ongoing"=>"var(--brand2)", "Completed"=>"var(--muted)", "Cancelled"=>"var(--danger)"][$r["status"]] ?? "var(--text)";
              ?>
              <span class="status-badge" style="color:<?= $color ?>;">● <?= e($r["status"]) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" style="text-align:center; padding:60px; color:var(--muted);">No records found matching these criteria.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
