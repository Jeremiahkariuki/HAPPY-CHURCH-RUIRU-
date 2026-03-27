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
function send_church_email(string $to, string $subject, string $message): bool {
    $date = date('Y-m-d H:i:s');
    $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }

    $smtp_host = MAIL_HOST;
    $smtp_port = (int)MAIL_PORT;
    $username  = MAIL_USERNAME;
    $password  = MAIL_PASSWORD;
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

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: $from_name <$username>",
        "Reply-To: $username",
        "To: $to",
        "Subject: $subject",
        "Date: " . date('r')
    ];

    $smtp = [];
    $success = false;

    try {
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15);
        if (!$socket) throw new Exception("Could not connect to $smtp_host on port $smtp_port: $errstr ($errno)");

        $read = function($socket) {
            $data = "";
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) == " ") break;
            }
            return $data;
        };

        $write = function($socket, $cmd) {
            fputs($socket, $cmd . "\r\n");
        };

        $read($socket); // 220
        $write($socket, "EHLO " . $_SERVER['SERVER_NAME']); $read($socket);
        $write($socket, "STARTTLS"); $read($socket);
        
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("STARTTLS failed");
        }

        $write($socket, "EHLO " . $_SERVER['SERVER_NAME']); $read($socket);
        $write($socket, "AUTH LOGIN"); $read($socket);
        $write($socket, base64_encode($username)); $read($socket);
        $write($socket, base64_encode($password)); $res = $read($socket);
        
        if (strpos($res, "235") === false) {
            throw new Exception("Authentication failed on Brevo (535). Please ensure your API Key is correct and your Sender Email ($username) is verified in your Brevo account settings.");
        }

        $write($socket, "MAIL FROM: <$username>"); $read($socket);
        $write($socket, "RCPT TO: <$to>"); $read($socket);
        $write($socket, "DATA"); $read($socket);
        $write($socket, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.");
        $res = $read($socket);

        if (strpos($res, "250") !== false) $success = true;

        $write($socket, "QUIT"); fclose($socket);
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        file_put_contents($logFile, "[$date] ERROR: $errorMsg\n", FILE_APPEND);
    }

    $status = $success ? "[SUCCESS]" : "[FAILED]";
    file_put_contents($logFile, "$status [$date] TO: $to | SUBJECT: $subject\n", FILE_APPEND);
    
    return $success;
}
