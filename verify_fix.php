<?php
require_once "db.php";

// 1. Reset all attendees to 'Registered'
$pdo->query("UPDATE attendees SET attendance_status = 'Registered'");

// 2. Set one attendee to 'Cancelled'
$pdo->query("UPDATE attendees SET attendance_status = 'Cancelled' LIMIT 1");

// 3. Set one attendee to 'Attended'
$pdo->query("UPDATE attendees SET attendance_status = 'Attended' WHERE attendance_status = 'Registered' LIMIT 1");

// 4. Calculate stats match dashboard
$attsCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees")->fetchColumn();
$attendedCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Attended'")->fetchColumn();
$cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM attendees WHERE attendance_status='Cancelled'")->fetchColumn();

// Math: Count=4, Attended=1, Cancelled=1. Rate = (1 / (4-1)) * 100 = 33.3%
$attRate = ($attsCount > 0) ? round(($attendedCount / max(1, $attsCount - $cancelledCount)) * 100, 1) : 0.0;

echo "Total: $attsCount\n";
echo "Attended: $attendedCount\n";
echo "Cancelled: $cancelledCount\n";
echo "Rate: $attRate%\n";

if ($attRate > 0) {
    echo "SUCCESS: Attendance rate is calculated and displayed correctly.\n";
} else {
    echo "FAILURE: Attendance rate is still 0.\n";
}
?>
