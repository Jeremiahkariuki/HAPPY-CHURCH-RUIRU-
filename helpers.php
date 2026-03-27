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
 * Universal High-Reliability Email Delivery System
 * Automatically detects whether to use Brevo HTTP API or Gmail Secure SMTP.
 */
function send_church_email(string $to, string $subject, string $message): bool {
    $date = date('Y-m-d H:i:s');
    $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }

    $username  = MAIL_USERNAME; 
    $password  = MAIL_PASSWORD; 
    $from_name = MAIL_FROM_NAME;

    $htmlBody = "
    <html><head><style>
            body { font-family: sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
            .header { background: #7c5cff; color: #fff; padding: 15px; border-radius: 8px 8px 0 0; text-align: center; }
            .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style></head><body><div class='container'>
            <div class='header'><h2>$from_name</h2></div>
            <div class='content'>" . nl2br($message) . "</div>
            <div class='footer'>Sent via Church Management System</div>
    </div></body></html>";

    $success = false;

    // AUTO-DETECT: Is it a Brevo API Key (starts with xsmtp)?
    if (strpos($password, 'xsmtp') === 0) {
        // --- MODE A: Brevo HTTP API ---
        try {
            $api_url = 'https://api.brevo.com/v3/smtp/email';
            $payload = [
                'sender' => ['name' => $from_name, 'email' => $username],
                'to' => [['email' => $to]],
                'subject' => $subject,
                'htmlContent' => $htmlBody
            ];
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
            if ($http_code >= 200 && $http_code < 300) $success = true;
            else {
                $resp = json_decode($response, true);
                throw new Exception("Brevo API Error ($http_code): " . ($resp['message'] ?? $response));
            }
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    } else {
        // --- MODE B: Gmail / Standard SMTP (Secure SSL Port 465) ---
        // Using Port 465 as it is often more compatible with cloud services than 587
        try {
            $smtp_host = strpos($username, 'gmail.com') !== false ? 'ssl://smtp.gmail.com' : 'ssl://' . MAIL_HOST;
            $socket = @fsockopen($smtp_host, 465, $errno, $errstr, 15);
            if (!$socket) throw new Exception("Could not connect to SMTP server: $errstr ($errno)");

            $read = function($s) {
                $d = ""; while ($str = fgets($s, 515)) { $d .= $str; if (substr($str, 3, 1) == " ") break; } return $d;
            };
            $write = function($s, $c) { fputs($s, $c . "\r\n"); };

            $read($socket); // 220
            $write($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost')); $read($socket);
            $write($socket, "AUTH LOGIN"); $read($socket);
            $write($socket, base64_encode($username)); $read($socket);
            $write($socket, base64_encode($password)); $res = $read($socket);
            if (strpos($res, "235") === false) throw new Exception("SMTP Authentication failed: $res");

            $write($socket, "MAIL FROM: <$username>"); $read($socket);
            $write($socket, "RCPT TO: <$to>"); $read($socket);
            $write($socket, "DATA"); $read($socket);
            $headers = ["MIME-Version: 1.0", "Content-Type: text/html; charset=UTF-8", "From: $from_name <$username>", "To: $to", "Subject: $subject"];
            $write($socket, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.");
            if (strpos($read($socket), "250") !== false) $success = true;
            $write($socket, "QUIT"); fclose($socket);
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }

    if (!$success) file_put_contents($logFile, "[$date] ERROR: " . ($errorMsg ?? "Unknown error") . "\n", FILE_APPEND);
    $status = $success ? "[SUCCESS]" : "[FAILED]";
    file_put_contents($logFile, "$status [$date] TO: $to | SUBJECT: $subject\n", FILE_APPEND);
    return $success;
}
