<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";

$q = trim((string)($_GET["q"] ?? ""));
$statusF = trim((string)($_GET["status"] ?? ""));
$eventF = trim((string)($_GET["event_id"] ?? ""));
$from = trim((string)($_GET["from"] ?? ""));
$to = trim((string)($_GET["to"] ?? ""));

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(a.full_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusF !== "") { $where[] = "a.attendance_status = ?"; $params[] = $statusF; }
if ($eventF !== "") { $where[] = "a.event_id = ?"; $params[] = (int)$eventF; }
if ($from !== "") { $where[] = "a.created_at >= ?"; $params[] = $from . " 00:00:00"; }
if ($to !== "") { $where[] = "a.created_at <= ?"; $params[] = $to . " 23:59:59"; }

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$stmt = $pdo->prepare("
  SELECT a.full_name, a.phone, a.email, e.title AS event, a.attendance_status, a.created_at
  FROM attendees a
  LEFT JOIN events e ON e.id = a.event_id
  $whereSql
  ORDER BY a.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "attendees_export_" . date("Y-m-d_H-i") . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // BOM for Excel

$out = fopen("php://output", "w");
fputcsv($out, ["Full Name","Phone","Email","Event","Attendance Status","Registered At"]);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;
