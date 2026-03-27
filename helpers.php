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
 * Universal Dual-Provider Email Delivery System (High Availability)
 * Tries Brevo FIRST (via HTTP API), then Falls back to Gmail (via SSL SMTP).
 */
function send_church_email(string $to, string $subject, string $message): bool {
    $date = date('Y-m-d H:i:s');
    $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
    if (!file_exists(dirname($logFile))) mkdir(dirname($logFile), 0777, true);

    $htmlBody = "
    <html><head><style>
            body { font-family: sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
            .header { background: #7c5cff; color: #fff; padding: 15px; border-radius: 8px 8px 0 0; text-align: center; }
            .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style></head><body><div class='container'>
            <div class='header'><h2>" . (MAIL_FROM_NAME ?? 'Happy Church Ruiru') . "</h2></div>
            <div class='content'>" . nl2br($message) . "</div>
            <div class='footer'>Sent via Church Management System</div>
    </div></body></html>";

    $success = false;
    $errors = [];

    // --- STEP 1: TRY BREVO FIRST (Most reliable on cloud) ---
    $b_user = getenv('BREVO_USERNAME') ?: (defined('BREVO_USERNAME') ? BREVO_USERNAME : '');
    $b_pass = getenv('BREVO_PASSWORD') ?: (defined('BREVO_PASSWORD') ? BREVO_PASSWORD : '');
    
    if ($b_pass) {
        try {
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'sender' => ['name' => MAIL_FROM_NAME, 'email' => $b_user],
                'to' => [['email' => $to]], 'subject' => $subject, 'htmlContent' => $htmlBody
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json', 'api-key: '.$b_pass, 'content-type: application/json']);
            $res = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($http_code >= 200 && $http_code < 300) $success = true;
            else $errors[] = "Brevo Failed ($http_code)";
        } catch (Exception $e) { $errors[] = "Brevo Exception"; }
    }

    if ($success) goto finalized;

    // --- STEP 2: TRY GMAIL FALLBACK ---
    $g_user = getenv('GMAIL_USERNAME') ?: (defined('GMAIL_USERNAME') ? GMAIL_USERNAME : 'simonnjoro965@gmail.com');
    $g_pass = getenv('GMAIL_PASSWORD') ?: (defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : 'Sy.123456789.');
    
    if ($g_pass && !$success) {
        try {
            $socket = @fsockopen('ssl://smtp.gmail.com', 465, $errno, $errstr, 10);
            if ($socket) {
                $read = function($s){$d="";while($str=fgets($s,515)){$d.=$str;if(substr($str,3,1)==" ")break;}return $d;};
                $write = function($s,$c){fputs($s,$c."\r\n");};
                $read($socket); // 220
                $write($socket, "EHLO localhost"); $read($socket);
                $write($socket, "AUTH LOGIN"); $read($socket);
                $write($socket, base64_encode($g_user)); $read($socket);
                $write($socket, base64_encode($g_pass)); $res = $read($socket);
                
                if (strpos($res, "235") !== false) {
                    $write($socket, "MAIL FROM: <$g_user>"); $read($socket);
                    $write($socket, "RCPT TO: <$to>"); $read($socket);
                    $write($socket, "DATA"); $read($socket);
                    $h = ["MIME-Version: 1.0", "Content-Type: text/html; charset=UTF-8", "From: ".MAIL_FROM_NAME." <$g_user>", "To: $to", "Subject: $subject"];
                    $write($socket, implode("\r\n", $h) . "\r\n\r\n" . $htmlBody . "\r\n.");
                    if (strpos($read($socket), "250") !== false) $success = true;
                } else $errors[] = "Gmail Auth Failed";
                $write($socket, "QUIT"); fclose($socket);
            } else $errors[] = "Gmail Connection Timeout";
        } catch (Exception $e) { $errors[] = "Gmail Exception"; }
    }

finalized:
    if (!$success) file_put_contents($logFile, "[$date] FAILED: " . implode(" | ", $errors) . "\n", FILE_APPEND);
    $status = $success ? "[SUCCESS]" : "[FAILED]";
    file_put_contents($logFile, "$status [$date] TO: $to | SUBJECT: $subject\n", FILE_APPEND);
    return $success;
}
