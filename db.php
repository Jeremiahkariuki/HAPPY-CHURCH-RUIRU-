<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: "127.0.0.1";
$db   = getenv('DB_NAME') ?: "church_events_system";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";

// 1. Try to connect to host first and create DB if it doesn't exist
try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // If host is Cloud (not localhost), add SSL support for Aiven/Clever Cloud
    if (!in_array($host, ["127.0.0.1", "localhost"])) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false; // Trust the server cert
    }
    
    // 1. Try to connect to host first and create DB if it doesn't exist
    $pdo = new PDO("mysql:host=$host", $user, $pass, $options);
    
    // Only attempt to create DB if we are on localhost/127.0.0.1 or if DB_NAME is not explicitly set
    if (in_array($host, ["127.0.0.1", "localhost"]) || !getenv('DB_NAME')) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    // Now connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);

} catch (PDOException $e) {
    // If DB fails, it means MySQL is likely OFF or credentials are wrong
    $error = "Database connection failed. If you are running locally, <strong>please start MySQL in your XAMPP Control Panel</strong>. If on Render, verify your environment variables.";
    error_log("DB Connection Error: " . $e->getMessage()); // Log the error for debugging
    $pdo = null; // Ensure $pdo is null if connection fails
}