<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
// Only admins can see connection details for security
if (($_SESSION["user"]["role"] ?? "") !== "admin") {
    die("Access denied. Admin only.");
}

// We don't include db.php yet because we want to see the environment variables BEFORE they are parsed
$rawHost = getenv('DB_HOST');
$rawPort = getenv('DB_PORT');
$rawUser = getenv('DB_USER');
$rawName = getenv('DB_NAME');

require_once __DIR__ . '/db.php';

echo "<h1>Database Connection Diagnostic</h1>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Parameter</th><th>Value (from Environment)</th></tr>";
echo "<tr><td>DB_HOST (Raw)</td><td><code>" . htmlspecialchars((string)$rawHost) . "</code></td></tr>";
echo "<tr><td>DB_PORT (Raw)</td><td><code>" . htmlspecialchars((string)$rawPort) . "</code></td></tr>";
echo "<tr><td>DB_USER (Raw)</td><td><code>" . htmlspecialchars((string)$rawUser) . "</code></td></tr>";
echo "<tr><td>DB_NAME (Raw)</td><td><code>" . htmlspecialchars((string)$rawName) . "</code></td></tr>";
echo "</table>";

echo "<h2>System Parsed Values</h2>";
echo "<ul>";
echo "<li><strong>Parsed Host:</strong> <code>" . htmlspecialchars((string)$host) . "</code></li>";
echo "<li><strong>Parsed Port:</strong> <code>" . htmlspecialchars((string)$port) . "</code></li>";
echo "<li><strong>Parsed User:</strong> <code>" . htmlspecialchars((string)$user) . "</code></li>";
echo "<li><strong>Parsed Database:</strong> <code>" . htmlspecialchars((string)$db) . "</code></li>";
echo "</ul>";

if ($pdo) {
    echo "<p style='color:green; font-weight:bold;'>✅ SUCCESS: Connected to database!</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>❌ FAILED: " . htmlspecialchars($db_connect_error ?? "Unknown error") . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>Back to Dashboard</a></p>";
