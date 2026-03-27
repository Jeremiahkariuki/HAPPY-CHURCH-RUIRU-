<?php
declare(strict_types=1);

/* ENVIRONMENT DETECTION: Decide whether to use XAMPP Local or Clever Cloud */
$is_live = (isset($_SERVER['RENDER']) || getenv('RENDER') !== false || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false));

$default_host = $is_live 
    ? "mysql://uvyjfieb0nz3gjns:ghlaCM5lBu9AdIqiljwv@blwa0wvl7pnkpndupnsv-mysql.services.clever-cloud.com:3306/blwa0wvl7pnkpndupnsv" 
    : "127.0.0.1";

$baseHost = trim((string)(getenv('DB_HOST') ?: getenv('MYSQL_ADDON_URI') ?: getenv('MYSQL_ADDON_HOST') ?: $default_host));
$port     = trim((string)(getenv('DB_PORT') ?: getenv('MYSQL_ADDON_PORT') ?: 3306));
$user     = trim((string)(getenv('DB_USER') ?: getenv('MYSQL_ADDON_USER') ?: ($is_live ? "uvyjfieb0nz3gjns" : "root")));
$pass     = (getenv('DB_PASS') !== false) ? trim((string)getenv('DB_PASS')) : ((getenv('MYSQL_ADDON_PASSWORD') !== false) ? trim((string)getenv('MYSQL_ADDON_PASSWORD')) : ($is_live ? "ghlaCM5lBu9AdIqiljwv" : ""));
$db       = trim((string)(getenv('DB_NAME') ?: getenv('MYSQL_ADDON_DB') ?: ($is_live ? "blwa0wvl7pnkpndupnsv" : "church_events_system")));

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

    // --- SELF-HEALING SCHEMA SYSTEM ---
    // If users table is missing, build the entire system automatically
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (Exception $e) {
        $schema = "
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `password_hash` varchar(255) NOT NULL,
          `email` varchar(100) DEFAULT NULL,
          `role` varchar(20) NOT NULL DEFAULT 'user',
          `status` varchar(20) NOT NULL DEFAULT 'Pending',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`),
          UNIQUE KEY `email` (`email`)
        );
        CREATE TABLE IF NOT EXISTS `events` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(100) NOT NULL,
          `event_date` date NOT NULL,
          `start_time` time DEFAULT NULL,
          `end_time` time DEFAULT NULL,
          `location` varchar(100) DEFAULT NULL,
          `category` varchar(50) DEFAULT NULL,
          `status` varchar(20) DEFAULT 'Upcoming',
          `description` text DEFAULT NULL,
          `image_path` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        );
        CREATE TABLE IF NOT EXISTS `attendees` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `full_name` varchar(100) NOT NULL,
          `phone` varchar(20) DEFAULT NULL,
          `email` varchar(100) DEFAULT NULL,
          `event_id` int(11) NOT NULL,
          `attendance_status` varchar(20) DEFAULT 'Registered',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          CONSTRAINT `fk_att_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS `volunteers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `full_name` varchar(100) NOT NULL,
          `phone` varchar(20) DEFAULT NULL,
          `email` varchar(100) DEFAULT NULL,
          `ministry` varchar(100) DEFAULT NULL,
          `availability` varchar(100) DEFAULT NULL,
          `event_id` int(11) DEFAULT NULL,
          `notes` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          CONSTRAINT `fk_vol_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS `donations` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `full_name` varchar(100) NOT NULL,
          `amount` decimal(10,2) NOT NULL,
          `payment_method` varchar(50) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        );
        CREATE TABLE IF NOT EXISTS `gallery` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `image_path` varchar(255) NOT NULL,
          `caption` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        );
        ";
        $pdo->exec($schema);
        
        $adminHash = password_hash('123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, role, status) VALUES ('admin', ?, 'admin', 'Approved')");
        $stmt->execute([$adminHash]);

        // Seed default gallery images from local uploads
        $galleryImages = [
            ['path' => 'uploads/gallery/08bd72b07820e59b.jpg', 'cap' => 'Community Gathering'],
            ['path' => 'uploads/gallery/209efaa485ccced6.jpg', 'cap' => 'Sunday Service'],
            ['path' => 'uploads/gallery/259de29104c49ed7.jpg', 'cap' => 'Youth Ministry'],
            ['path' => 'uploads/gallery/9a9dc3d53c628f7b.jpg', 'cap' => 'Worship Team'],
            ['path' => 'uploads/gallery/9d4b09ee6ecb70eb.jpg', 'cap' => 'Church Building'],
            ['path' => 'uploads/gallery/a43f7a004e89bc17.jpg', 'cap' => 'Outreach Program'],
        ];
        foreach ($galleryImages as $img) {
            $pdo->prepare("INSERT IGNORE INTO gallery (image_path, caption) VALUES (?, ?)")->execute([$img['path'], $img['cap']]);
        }
    }

    // --- AUTO MIGRATIONS (Ensure existing tables have new columns) ---
    $migrations = [
        "ALTER TABLE attendees ADD COLUMN IF NOT EXISTS email varchar(100) DEFAULT NULL AFTER phone",
        "ALTER TABLE volunteers ADD COLUMN IF NOT EXISTS email varchar(100) DEFAULT NULL AFTER phone",
        "ALTER TABLE volunteers ADD COLUMN IF NOT EXISTS event_id int(11) DEFAULT NULL AFTER email",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS image_path varchar(255) DEFAULT NULL AFTER description",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS status varchar(20) NOT NULL DEFAULT 'Pending' AFTER role",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_code varchar(10) DEFAULT NULL AFTER status"
    ];
    foreach ($migrations as $m) {
        try { $pdo->exec($m); } catch (Exception $e) {}
    }

    // --- SEED GALLERY IF EMPTY ---
    try {
        $galleryCount = (int)($pdo->query("SELECT COUNT(*) FROM gallery")->fetchColumn() ?: 0);
        if ($galleryCount === 0) {
            $galleryImages = [
                ['path' => 'uploads/gallery/08bd72b07820e59b.jpg', 'cap' => 'Community Gathering'],
                ['path' => 'uploads/gallery/209efaa485ccced6.jpg', 'cap' => 'Sunday Service'],
                ['path' => 'uploads/gallery/259de29104c49ed7.jpg', 'cap' => 'Youth Ministry'],
                ['path' => 'uploads/gallery/9a9dc3d53c628f7b.jpg', 'cap' => 'Worship Team'],
                ['path' => 'uploads/gallery/9d4b09ee6ecb70eb.jpg', 'cap' => 'Church Building'],
                ['path' => 'uploads/gallery/a43f7a004e89bc17.jpg', 'cap' => 'Outreach Program'],
            ];
            foreach ($galleryImages as $img) {
                $pdo->prepare("INSERT IGNORE INTO gallery (image_path, caption) VALUES (?, ?)")->execute([$img['path'], $img['cap']]);
            }
        }
    } catch (Exception $e) {}

} catch (PDOException $e) {
    // If DB fails, it means MySQL is likely OFF or credentials are wrong
    $isRender = isset($_SERVER['RENDER']) || getenv('RENDER');
    $db_connect_error = $isRender 
        ? "Cloud DB Error (Host: $host): " . $e->getMessage()
        : "Database connection failed. " . $e->getMessage();
    
    error_log("DB Connection Error: " . $e->getMessage());
    $pdo = null;
}