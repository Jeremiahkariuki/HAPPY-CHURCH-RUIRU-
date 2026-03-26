<?php
declare(strict_types=1);

$baseHost = trim((string)(getenv('DB_HOST') ?: getenv('MYSQL_ADDON_URI') ?: getenv('MYSQL_ADDON_HOST') ?: "127.0.0.1"));
$port     = trim((string)(getenv('DB_PORT') ?: getenv('MYSQL_ADDON_PORT') ?: 3306));
$user     = trim((string)(getenv('DB_USER') ?: getenv('MYSQL_ADDON_USER') ?: "root"));
$pass     = getenv('DB_PASS') !== false ? trim((string)getenv('DB_PASS')) : (getenv('MYSQL_ADDON_PASSWORD') !== false ? trim((string)getenv('MYSQL_ADDON_PASSWORD')) : "");
$db       = trim((string)(getenv('DB_NAME') ?: getenv('MYSQL_ADDON_DB') ?: ""));

$host = $baseHost;

// Robust parser: If user pasted a full Aiven connection URI (mysql://user:pass@host:port/db) inside DB_HOST
if (strpos($baseHost, 'mysql://') === 0 || strpos($baseHost, 'mysql+ssl://') === 0) {
    $parsed = parse_url($baseHost);
    if ($parsed && isset($parsed['host'])) {
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? (string)$parsed['port'] : $port;
        $user = $parsed['user'] ?? $user;
        $pass = $parsed['pass'] ?? $pass;
        $db   = ltrim($parsed['path'] ?? "", '/') ?: $db;
    }
} else {
    // If they just pasted "host:port"
    if (strpos($baseHost, ':') !== false) {
        list($h, $p) = explode(':', $baseHost, 2);
        $host = trim($h);
        $port = trim($p);
    }
}

$isLocal = in_array($host, ["127.0.0.1", "localhost"]);
if (!$db) {
    $db = $isLocal ? "church_events_system" : "defaultdb";
}

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
    
    // 1. For local environments, connect to host first and create DB if it doesn't exist.
    // Cloud databases (like Aiven) pre-create the DB and block CREATE DATABASE commands.
    if ($isLocal) {
        $pdoTemp = new PDO("mysql:host=$host;port=$port", $user, $pass, $options);
        $pdoTemp->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdoTemp = null;
    }

    // Now connect to the specific database
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, $options);

    // --- Auto Schema Migrations for Legacy Databases ---
    try { $pdo->query("SELECT email FROM attendees LIMIT 1"); } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE attendees ADD COLUMN email varchar(100) DEFAULT NULL AFTER phone"); } catch(Exception $ex){}
    }
    try { $pdo->query("SELECT email FROM volunteers LIMIT 1"); } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE volunteers ADD COLUMN email varchar(100) DEFAULT NULL AFTER phone"); } catch(Exception $ex){}
    }
    try { $pdo->query("SELECT event_id FROM volunteers LIMIT 1"); } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE volunteers ADD COLUMN event_id int(11) DEFAULT NULL AFTER email"); } catch(Exception $ex){}
    }

} catch (PDOException $e) {
    // If DB fails, it means MySQL is likely OFF or credentials are wrong
    $isRender = isset($_SERVER['RENDER']) || getenv('RENDER');
    $db_connect_error = $isRender 
        ? "Cloud DB Error (Host: $host): " . $e->getMessage()
        : "Database connection failed. " . $e->getMessage();
    
    error_log("DB Connection Error: " . $e->getMessage());
    $pdo = null;
}