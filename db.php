<?php

$host = "localhost";
$db   = "church_events_system";
$user = "root";
$pass = "";

try {

            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5, // 5 second timeout to prevent hangs
            ]);

} catch(PDOException $e) {

$pdo = null;

}
