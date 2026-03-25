<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";

$q = trim((string)($_GET["q"] ?? ""));
$ministryF = trim((string)($_GET["ministry"] ?? ""));
$availF = trim((string)($_GET["availability"] ?? ""));

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($ministryF !== "") { $where[] = "ministry LIKE ?"; $params[] = "%$ministryF%"; }
if ($availF !== "") { $where[] = "availability = ?"; $params[] = $availF; }

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$stmt = $pdo->prepare("
  SELECT v.full_name, v.phone, v.email, v.ministry, v.availability, e.title AS event_title, e.event_date, v.notes, v.created_at 
  FROM volunteers v
  LEFT JOIN events e ON e.id = v.event_id
  $whereSql ORDER BY v.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "volunteers_export_" . date("Y-m-d_H-i") . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // BOM for Excel

$out = fopen("php://output", "w");
fputcsv($out, ["Full Name","Phone","Email","Ministry","Availability","Event Title","Event Date","Notes","Created At"]);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;
