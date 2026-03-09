<?php
declare(strict_types=1);

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

function redirect(string $to): void {
  header("Location: " . $to);
  exit;
}

function flash_set(string $msg, string $type="success"): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION["flash"] = ["msg" => $msg, "type" => $type];
}

function flash_get(): ?array {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $f = $_SESSION["flash"] ?? null;
  unset($_SESSION["flash"]);
  return $f;
}
