<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function require_login(): void {
  if (empty($_SESSION["user"])) {
    header("Location: login.php");
    exit;
  }
}

function current_user(): ?array {
  return $_SESSION["user"] ?? null;
}

// Global authentication variables
$sessionUser = $_SESSION["user"] ?? [];
$user = $sessionUser["username"] ?? "Guest";
$userRole = $sessionUser["role"] ?? "guest";
