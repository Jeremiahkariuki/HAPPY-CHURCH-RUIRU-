<?php
declare(strict_types=1);
require_once __DIR__ . "/mail_config.php";

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); }

/**
 * Standardizes date display across the church system.
 */
function format_date(?string $date, string $format = "d M Y"): string {
    if (!$date) return "-";
    $time = strtotime($date);
    return $time ? date($format, $time) : "-";
}

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

/**
 * Robust SMTP Client for Gmail (Bypasses unreliable Windows mail() function)
 */
/**
 * High-Reliability Brevo HTTP API Implementation
 * This bypasses SMTP port blocks (587, 465, 25) which are common on Render.
 */
function send_church_email(string $to, string $subject, string $message): bool {
    $date = date('Y-m-d H:i:s');
    $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }

    $username  = MAIL_USERNAME; // Sender Email (must be verified in Brevo)
    $password  = MAIL_PASSWORD; // Brevo API v3 Key (starts with xsmtp-...)
    $from_name = MAIL_FROM_NAME;

    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
            .header { background: #7c5cff; color: #fff; padding: 15px; border-radius: 8px 8px 0 0; text-align: center; }
            .content { padding: 20px; }
            .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'><h2>$from_name</h2></div>
            <div class='content'>" . nl2br($message) . "</div>
            <div class='footer'>Sent via Church Management System</div>
        </div>
    </body>
    </html>";

    $api_url = 'https://api.brevo.com/v3/smtp/email';
    $payload = [
        'sender' => ['name' => $from_name, 'email' => $username],
        'to' => [['email' => $to]],
        'subject' => $subject,
        'htmlContent' => $htmlBody
    ];

    $success = false;

    try {
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $password,
            'content-type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            $success = true;
        } else {
            $resp = json_decode($response, true);
            $errText = $resp['message'] ?? $response;
            throw new Exception("Brevo API Error ($http_code): $errText. Ensure your API Key is correct and Sender Email ($username) is verified in Brevo.");
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        file_put_contents($logFile, "[$date] ERROR: $errorMsg\n", FILE_APPEND);
    }

    $status = $success ? "[SUCCESS]" : "[FAILED]";
    file_put_contents($logFile, "$status [$date] TO: $to | SUBJECT: $subject\n", FILE_APPEND);
    
    return $success;
}
