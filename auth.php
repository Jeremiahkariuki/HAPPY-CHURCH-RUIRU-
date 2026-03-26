<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function require_login(): void {
  if (empty($_SESSION["user"])) {
    header("Location: login.php");
    exit;
  }
  
  // High-Security Check: Verify account status hasn't changed since login
  global $pdo;
  if (!isset($pdo)) { require_once __DIR__ . "/db.php"; }
  
  try {
      $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
      $stmt->execute([$_SESSION["user"]["id"]]);
      $status = $stmt->fetchColumn();
      
      if ($status !== 'Approved' && ($_SESSION["user"]["role"] ?? "") !== "admin") {
          session_destroy();
          header("Location: login.php?error=Account+not+approved");
          exit;
      }
  } catch (Exception $e) {
      // If DB is down, we let them stay in session but log the error
      error_log("Auth system could not verify status: " . $e->getMessage());
  }
}

function current_user(): ?array {
  return $_SESSION["user"] ?? null;
}

// Global authentication variables
$sessionUser = $_SESSION["user"] ?? [];
$user = $sessionUser["username"] ?? "Guest";
$userRole = $sessionUser["role"] ?? "guest";
