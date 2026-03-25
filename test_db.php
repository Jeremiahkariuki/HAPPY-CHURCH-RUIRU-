<?php
declare(strict_types=1);

echo "<h1>Database Connection Diagnostic</h1>";

$host = getenv('DB_HOST') ?: "127.0.0.1";
$db   = getenv('DB_NAME') ?: "church_events_system";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";

echo "<ul>";
echo "<li><strong>Host:</strong> $host</li>";
echo "<li><strong>Database Name:</strong> $db</li>";
echo "<li><strong>User:</strong> $user</li>";
echo "<li><strong>Password:</strong> " . ($pass === "" ? "(Empty)" : "(Set)") . "</li>";
echo "</ul>";

try {
    echo "<h3>Step 1: Connecting to MySQL Server...</h3>";
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>SUCCESS: Connected to MySQL Server!</p>";

    echo "<h3>Step 2: Checking for Database '$db'...</h3>";
    $stmt = $pdo->query("SHOW DATABASES LIKE '$db'");
    if ($stmt->fetch()) {
        echo "<p style='color:green'>SUCCESS: Database '$db' exists.</p>";
    } else {
        echo "<p style='color:orange'>WARNING: Database '$db' does not exist. Attempting to create...</p>";
        $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color:green'>SUCCESS: Database '$db' created!</p>";
    }

    echo "<h3>Step 3: Connecting to specific Database...</h3>";
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "<p style='color:green'>SUCCESS: Full connection established!</p>";

    echo "<h3>Step 4: Checking 'users' table...</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        echo "<p style='color:green'>SUCCESS: 'users' table exists.</p>";
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "<p>Found <strong>$count</strong> users in table.</p>";
    } else {
        echo "<p style='color:red'>ERROR: 'users' table is MISSING. Please run 'db_setup.php' manually.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red'><strong>FATAL ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><em>Tip: Make sure MySQL is running in your XAMPP Control Panel.</em></p>";
}
?>
