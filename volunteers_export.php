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

$stmt = $pdo->prepare("SELECT full_name,phone,email,ministry,availability,notes,created_at FROM volunteers $whereSql ORDER BY id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "volunteers_export_" . date("Y-m-d_H-i") . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo "\xEF\xBB\xBF"; // BOM for Excel

$out = fopen("php://output", "w");
fputcsv($out, array_keys($rows[0] ?? ["full_name","phone","email","ministry","availability","notes","created_at"]));
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;
