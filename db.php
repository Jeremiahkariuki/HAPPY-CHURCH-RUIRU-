<?php

// Environment-aware configuration
$rawHost = getenv('DB_HOST') ?: "localhost";
$db      = getenv('DB_NAME') ?: "church_events_system";
$user    = getenv('DB_USER') ?: "root";
$pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$port    = "3306";

// Handle full MySQL URI if provided (common on Render/Heroku)
if (strpos($rawHost, 'mysql://') === 0) {
    $url = parse_url($rawHost);
    $host = $url['host'] ?? 'localhost';
    $port = isset($url['port']) ? (string)$url['port'] : "3306";
    $user = $url['user'] ?? $user;
    $pass = $url['pass'] ?? $pass;
    $db   = isset($url['path']) ? ltrim($url['path'], '/') : $db;
} else {
    $host = $rawHost;
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5, // 5 second timeout to prevent hangs
    ]);
} catch(PDOException $e) {
    $pdo = null;
    $db_connect_error = $e->getMessage();
}
