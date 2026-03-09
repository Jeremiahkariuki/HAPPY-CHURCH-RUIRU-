<?php
require_once "db.php";

$stats = $pdo->query("SELECT attendance_status, COUNT(*) as count FROM attendees GROUP BY attendance_status")->fetchAll(PDO::FETCH_ASSOC);
echo "Attendee Status Distribution:\n";
foreach ($stats as $row) {
    echo "- " . ($row['attendance_status'] ?: 'NULL') . ": " . $row['count'] . "\n";
}

$attsCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees")->fetchColumn();
$attendedCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Attended'")->fetchColumn();
$cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Cancelled'")->fetchColumn();
$attRate = ($attsCount > 0) ? round(($attendedCount / max(1, $attsCount - $cancelledCount)) * 100, 1) : 0.0;

echo "\nCalculated Rate: " . $attRate . "%\n";
echo "Total Attendees: " . $attsCount . "\n";
echo "Count with 'Attended' status: " . $attendedCount . "\n";
?>
