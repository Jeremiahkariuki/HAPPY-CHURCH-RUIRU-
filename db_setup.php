<?php
try {
  $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('CREATE DATABASE IF NOT EXISTS `church_events_system`');
  $pdo->exec('USE `church_events_system`');
  
  $sql = "
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
    KEY `event_id` (`event_id`),
    CONSTRAINT `attendees_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
  );
  
  CREATE TABLE IF NOT EXISTS `volunteers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `full_name` varchar(100) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `ministry` varchar(100) DEFAULT NULL,
    `availability` varchar(100) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
  );
  
  CREATE TABLE IF NOT EXISTS `donations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `full_name` varchar(100) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `payment_method` varchar(50) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
  );

  ";
  
  $pdo->exec($sql);

  // Seed admin user with proper password hash
  $adminHash = password_hash('123', PASSWORD_DEFAULT);
  $pdo->prepare("INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`, `status`) VALUES ('admin', ?, 'admin', 'Approved')")->execute([$adminHash]);

  // Migration: Add status if not exists
  echo "Checking for status column...<br>";
  try {
     $pdo->exec("ALTER TABLE `users` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'Pending' AFTER `role` ");
     echo "Added 'status' column successfully.<br>";
  } catch (Exception $e) {
     echo "Status column already exists or could not be added: " . $e->getMessage() . "<br>";
  }

  try {
     $pdo->exec("UPDATE `users` SET `status` = 'Approved' WHERE `role` = 'admin' ");
     echo "Updated admin status to Approved.<br>";
  } catch (Exception $e) {
     echo "Could not update admin status.<br>";
  }

  try {
     $pdo->exec("ALTER TABLE `events` ADD COLUMN `image_path` VARCHAR(255) AFTER `description` ");
     echo "Added 'image_path' column to events table.<br>";
  } catch (Exception $e) {
     echo "Image path column already exists or could not be added.<br>";
  }

  try {
     $pdo->exec("ALTER TABLE `users` ADD COLUMN `email` varchar(100) DEFAULT NULL AFTER `username` ");
     $pdo->exec("ALTER TABLE `users` ADD UNIQUE (`email`) ");
     echo "Added 'email' column to users successfully.<br>";
  } catch (Exception $e) {
     echo "Email column already exists or could not be added.<br>";
  }

  try {
     $pdo->exec("ALTER TABLE `volunteers` ADD COLUMN `event_id` int(11) DEFAULT NULL AFTER `email` ");
     $pdo->exec("ALTER TABLE `volunteers` ADD CONSTRAINT `fk_vol_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ");
     echo "Added 'event_id' and foreign key to volunteers.<br>";
  } catch (Exception $e) {
     echo "Volunteer event_id column/FK already exists or could not be added.<br>";
  }

  try {
     $pdo->exec("CREATE TABLE IF NOT EXISTS `gallery` (
       `id` int(11) NOT NULL AUTO_INCREMENT,
       `image_path` varchar(255) NOT NULL,
       `caption` varchar(255) DEFAULT NULL,
       `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
       PRIMARY KEY (`id`)
     )");
     echo "Ensured 'gallery' table exists.<br>";
  } catch (Exception $e) {
     echo "Could not ensure gallery table.<br>";
  }

  echo "<strong>Database setup and migrations successful!</strong>";
} catch (PDOException $e) {
  echo '<strong style="color:red;">Error: ' . $e->getMessage() . '</strong>';
}
?>
