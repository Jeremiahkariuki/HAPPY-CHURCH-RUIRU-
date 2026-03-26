<?php
declare(strict_types=1);

$baseHost = getenv('DB_HOST') ?: "127.0.0.1";
$port = 3306;
if (strpos($baseHost, ':') !== false) {
    list($host, $port) = explode(':', $baseHost, 2);
} else {
    $host = $baseHost;
}

$isLocal = in_array($host, ["127.0.0.1", "localhost"]);
$db   = getenv('DB_NAME') ?: ($isLocal ? "church_events_system" : "defaultdb");
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";

// 1. Try to connect to host first and create DB if it doesn't exist
try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5, // 5 seconds timeout to prevent deployment hangs
    ];

    // If host is Cloud (not localhost), add SSL support for Aiven/Clever Cloud
    if (!$isLocal) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false; // Trust the server cert
    }
    
    // 1. Try to connect to host first and create DB if it doesn't exist
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, $options);
    
    // Only attempt to create DB if we are on localhost/127.0.0.1 or if DB_NAME is not explicitly set
    if ($isLocal || !getenv('DB_NAME')) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    // Now connect to the specific database
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, $options);

} catch (PDOException $e) {
    // If DB fails, it means MySQL is likely OFF or credentials are wrong
    $isRender = isset($_SERVER['RENDER']) || getenv('RENDER');
    $error = $isRender 
        ? "<strong>Cloud Database Not Connected.</strong> Please ensure you have added your Aiven credentials (DB_HOST, DB_USER, etc.) to the <strong>Render Environment</strong> tab."
        : "<strong>Database connection failed.</strong> Please start MySQL in your <strong>XAMPP Control Panel</strong>.";
    
    error_log("DB Connection Error: " . $e->getMessage());
    $pdo = null;
}