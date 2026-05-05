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
        PDO::ATTR_TIMEOUT => 5,
    ]);
    
    // Auto-cleanup past events (safe — won't error if table doesn't exist yet)
    try {
        $pdo->exec("DELETE FROM events WHERE event_date < CURRENT_DATE");
    } catch (PDOException $cleanupErr) {
        // Table may not exist yet on first run — ignore safely
    }
} catch(PDOException $e) {
    try {
        // Advanced Auto-Fallback to Embedded SQLite for Cloud / Zero-Config environments
        $sqlitePath = __DIR__ . '/church_events.sqlite';
        $needsSetup = !file_exists($sqlitePath);
        
        $pdo = new PDO("sqlite:" . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($needsSetup) {
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email TEXT UNIQUE,
                role TEXT NOT NULL DEFAULT 'user',
                status TEXT NOT NULL DEFAULT 'Pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                otp_code TEXT
            );
            ");
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                event_date DATE NOT NULL,
                start_time TIME,
                end_time TIME,
                location TEXT,
                category TEXT,
                status TEXT DEFAULT 'Upcoming',
                description TEXT,
                image_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ");
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS attendees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                phone TEXT,
                email TEXT,
                event_id INTEGER NOT NULL,
                attendance_status TEXT DEFAULT 'Registered',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ");
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS volunteers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                phone TEXT,
                email TEXT,
                event_id INTEGER,
                ministry TEXT,
                availability TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ");
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ");
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS gallery (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                image_path TEXT NOT NULL,
                caption TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ");
            
            // Seed default admin
            $adminHash = password_hash('123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, status) VALUES ('admin', ?, 'admin', 'Approved')");
            $stmt->execute([$adminHash]);
        }
    } catch(PDOException $fallbackError) {
        $pdo = null;
        $db_connect_error = "MySQL Failed: " . $e->getMessage() . " | SQLite Fallback Failed: " . $fallbackError->getMessage();
    }
}
