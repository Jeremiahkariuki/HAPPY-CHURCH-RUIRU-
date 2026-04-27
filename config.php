<?php
declare(strict_types=1);

/**
 * ============================================================
 * CHURCH CONFIGURATION — SINGLE SOURCE OF TRUTH
 * ============================================================
 * To rename the church, simply change the "name" value below.
 * The new name will instantly reflect across the ENTIRE system:
 *   login page, dashboard, emails, reports, header, sidebar,
 *   about page, contacts page, and all notifications.
 *
 * Example: change "LOVE CHURCH" to "GRACE CHURCH NAIROBI"
 * ============================================================
 */

return [
  "db" => [
    "host" => "127.0.0.1",
    "name" => "church_events_system",
    "user" => "root",
    "pass" => "",     // XAMPP default
    "charset" => "utf8mb4",
  ],
  "app" => [
    // ✏️  RENAME YOUR CHURCH HERE — changes apply site-wide instantly
    "name" => "LOVE CHURCH",
  ],
];
