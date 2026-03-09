<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";

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

$stmt = $pdo->prepare("SELECT title,event_date,start_time,end_time,location,category,status,description,created_at FROM events $whereSql ORDER BY event_date DESC, id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Excel-friendly CSV download
$filename = "events_export_" . date("Y-m-d_H-i") . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen("php://output", "w");
fputcsv($out, array_keys($rows[0] ?? ["title","event_date","start_time","end_time","location","category","status","description","created_at"]));

foreach ($rows as $r) {
  fputcsv($out, $r);
}
fclose($out);
exit;
