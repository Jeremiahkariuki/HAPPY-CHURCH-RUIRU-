<?php
require_once __DIR__ . '/db.php';

try {
  if (!$pdo) {
      throw new Exception("Database connection not established. Check your credentials.");
  }

  // Detect database driver
  $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
  $isSQLite = ($driver === 'sqlite');

  echo "<h2>🔧 HAPPY CHURCH RUIRU — Database Setup</h2>";
  echo "<p>Driver: <strong>$driver</strong></p>";

  if ($isSQLite) {
    // ── SQLite schema ──
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
    echo "✅ users table ready<br>";

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
    echo "✅ events table ready<br>";

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
    echo "✅ attendees table ready<br>";

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
    echo "✅ volunteers table ready<br>";

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS donations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        amount REAL NOT NULL,
        payment_method TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      );
    ");
    echo "✅ donations table ready<br>";

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        image_path TEXT NOT NULL,
        caption TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      );
    ");
    echo "✅ gallery table ready<br>";

  } else {
    // ── MySQL schema (one statement at a time) ──
    $pdo->exec("
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
      )
    ");
    echo "✅ users table ready<br>";

    $pdo->exec("
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
      )
    ");
    echo "✅ events table ready<br>";

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS `attendees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `full_name` varchar(100) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        `event_id` int(11) NOT NULL,
        `attendance_status` varchar(20) DEFAULT 'Registered',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `event_id` (`event_id`),
        CONSTRAINT `attendees_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
      )
    ");
    echo "✅ attendees table ready<br>";

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS `volunteers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `full_name` varchar(100) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        `event_id` int(11) DEFAULT NULL,
        `ministry` varchar(100) DEFAULT NULL,
        `availability` varchar(100) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
      )
    ");
    echo "✅ volunteers table ready<br>";

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS `donations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `full_name` varchar(100) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `payment_method` varchar(50) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
      )
    ");
    echo "✅ donations table ready<br>";

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS `gallery` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `image_path` varchar(255) NOT NULL,
        `caption` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
      )
    ");
    echo "✅ gallery table ready<br>";

    // MySQL-specific migrations
    try { $pdo->exec("ALTER TABLE `events` ADD COLUMN `image_path` VARCHAR(255) AFTER `description`"); echo "Added image_path to events<br>"; } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `otp_code` VARCHAR(10) DEFAULT NULL"); echo "Added otp_code to users<br>"; } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `volunteers` ADD CONSTRAINT `fk_vol_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE"); echo "Added FK to volunteers<br>"; } catch (Exception $e) {}
  }

  // Seed admin user (works for both MySQL and SQLite)
  $adminHash = password_hash('123', PASSWORD_DEFAULT);
  if ($isSQLite) {
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password_hash, role, status) VALUES ('admin', ?, 'admin', 'Approved')");
  } else {
    $stmt = $pdo->prepare("INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `status`) VALUES ('admin', ?, 'admin', 'Approved')");
  }
  $stmt->execute([$adminHash]);
  echo "✅ Admin user seeded<br>";

  // Ensure admin is approved
  $pdo->exec("UPDATE users SET status = 'Approved' WHERE role = 'admin'");
  echo "✅ Admin status set to Approved<br>";

  echo "<br><strong style='color:green;'>🎉 Database setup and migrations successful!</strong>";
  echo "<br><br><a href='login.php'>→ Go to Login</a>";

} catch (Exception $e) {
  echo '<strong style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</strong>';
}
?>
