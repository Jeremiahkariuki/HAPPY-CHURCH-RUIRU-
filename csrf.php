<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function csrf_token(): string {
  if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
  }
  return $_SESSION["csrf"];
}

function csrf_verify(): void {
  $token = $_POST["csrf"] ?? "";
  if (empty($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $token)) {
    http_response_code(403);
    exit("Invalid CSRF token.");
  }
}
