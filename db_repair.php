<?php
require_once __DIR__ . "/db.php";

echo "<h1>🛠️ HAPPY CHURCH RUIRU - Database Repair</h1>";
echo "<pre>";

if (!$pdo) {
    die("❌ Connection failed. Ensure MySQL is running in XAMPP.");
}

$tasks = [
    // [table, column, definition]
    ['volunteers', 'ministry', "VARCHAR(100) DEFAULT NULL AFTER email"],
    ['volunteers', 'event_id', "INT(11) DEFAULT NULL AFTER email"],
    ['events',     'image_path', "VARCHAR(255) DEFAULT NULL AFTER description"],
    ['attendees',  'event_id', "INT(11) NOT NULL AFTER email"],
];

foreach ($tasks as $task) {
    list($table, $col, $def) = $task;
    echo "Checking $table.$col... ";
    try {
        $q = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        if ($q->rowCount() === 0) {
            echo "MISSING. Adding... ";
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            echo "✅ FIXED.\n";
        } else {
            echo "OK.\n";
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

// Check gallery table
echo "Checking gallery table... ";
try {
    $pdo->query("SELECT 1 FROM `gallery` LIMIT 1");
    echo "OK.\n";
} catch (Exception $e) {
    echo "MISSING. Creating... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `gallery` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `image_path` varchar(255) NOT NULL,
        `caption` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    )");
    echo "✅ FIXED.\n";
}

// Check constraint for volunteers
echo "Checking foreign key for volunteers... ";
try {
    $pdo->exec("ALTER TABLE `volunteers` ADD CONSTRAINT `fk_vol_event_repair` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE");
    echo "✅ ADDED.\n";
} catch (Exception $e) {
    echo "ALREADY EXISTS or skipped.\n";
}

echo "\n✨ Repair complete. Please refresh your Dashboard.";
echo "</pre>";
