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
    
    // Perform background tasks if supported (makes the UI feel instant)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
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
 * Universal Dual-Provider Email Delivery System (High Availability)
 * 
 * Provider 1 (Primary): Gmail SMTP via port 587 + STARTTLS (works on cloud platforms)
 * Provider 2 (Fallback): Brevo SMTP relay via port 2525
 * 
 * Includes Reply-To header so recipients can reply directly to the church admin.
 */
function send_church_email(string $to, string $subject, string $message): bool {
    $date = date('Y-m-d H:i:s');
    $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
    if (!file_exists(dirname($logFile))) mkdir(dirname($logFile), 0777, true);

    // Support for locally saved API settings from the Dashboard
    if (file_exists(__DIR__ . '/config_mail_local.php')) {
        include_once __DIR__ . '/config_mail_local.php';
    }

    // --- Build HTML email body ---
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'HAPPY CHURCH';
    $htmlBody = "
    <html><head><style>
            body { font-family: sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px; }
            .header { background: #7c5cff; color: #fff; padding: 15px; border-radius: 8px 8px 0 0; text-align: center; }
            .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style></head><body><div class='container'>
            <div class='header'><h2>$fromName</h2></div>
            <div class='content'>" . nl2br($message) . "</div>
            <div class='footer'>Sent via $fromName Management System &bull; Reply directly to this email</div>
    </div></body></html>";

    $success = false;
    $errors = [];

    // --- Helper closures for raw SMTP communication ---
    $smtpRead = function($s) {
        $data = "";
        while ($str = @fgets($s, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === " ") break;
        }
        return $data;
    };
    $smtpWrite = function($s, $cmd) { @fputs($s, $cmd . "\r\n"); };

    // --- Build MIME headers (shared by all providers) ---
    $buildHeaders = function(string $senderEmail, string $senderName) use ($to, $subject) {
        $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : $senderEmail;
        $msgId = '<' . uniqid('church_', true) . '@' . gethostname() . '>';
        return [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: $senderName <$senderEmail>",
            "Reply-To: $senderName <$replyTo>",
            "To: $to",
            "Subject: $subject",
            "Message-ID: $msgId",
            "X-Mailer: ChurchManagementSystem/2.0",
            "Date: " . date('r'),
        ];
    };

    // =====================================================
    // PROVIDER 1: Gmail SMTP via Port 587 + STARTTLS
    // (Port 465/SSL is blocked on most cloud platforms)
    // =====================================================
    $g_user = defined('GMAIL_USERNAME') ? GMAIL_USERNAME : (getenv('GMAIL_USERNAME') ?: '');
    $g_pass = defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : (getenv('GMAIL_PASSWORD') ?: '');

    if ($g_user && $g_pass) {
        try {
            $ctx = stream_context_create(['ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]]);
            $socket = @stream_socket_client('tcp://smtp.gmail.com:587', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
            if ($socket) {
                stream_set_timeout($socket, 15);
                $smtpRead($socket); // 220 banner

                $smtpWrite($socket, "EHLO " . gethostname());
                $smtpRead($socket);

                // Upgrade to TLS
                $smtpWrite($socket, "STARTTLS");
                $tlsResponse = $smtpRead($socket);

                if (strpos($tlsResponse, "220") !== false) {
                    $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                    if (!$cryptoOk) {
                        // Fallback to broader TLS method
                        $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    }

                    if ($cryptoOk) {
                        $smtpWrite($socket, "EHLO " . gethostname());
                        $smtpRead($socket);

                        $smtpWrite($socket, "AUTH LOGIN");
                        $smtpRead($socket);
                        $smtpWrite($socket, base64_encode($g_user));
                        $smtpRead($socket);
                        $smtpWrite($socket, base64_encode($g_pass));
                        $authRes = $smtpRead($socket);

                        if (strpos($authRes, "235") !== false) {
                            $smtpWrite($socket, "MAIL FROM: <$g_user>");
                            $smtpRead($socket);
                            $smtpWrite($socket, "RCPT TO: <$to>");
                            $smtpRead($socket);
                            $smtpWrite($socket, "DATA");
                            $smtpRead($socket);

                            $headers = $buildHeaders($g_user, $fromName);
                            $smtpWrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.");
                            $dataRes = $smtpRead($socket);

                            if (strpos($dataRes, "250") !== false) {
                                $success = true;
                            } else {
                                $errors[] = "Gmail DATA rejected: " . trim($dataRes);
                            }
                        } else {
                            $errors[] = "Gmail Auth Failed (Check App Password)";
                        }
                    } else {
                        $errors[] = "Gmail TLS handshake failed";
                    }
                } else {
                    $errors[] = "Gmail STARTTLS not supported: " . trim($tlsResponse);
                }
                $smtpWrite($socket, "QUIT");
                @fclose($socket);
            } else {
                $errors[] = "Gmail Connection Failed on Port 587 ($errstr)";
            }
        } catch (Exception $e) {
            $errors[] = "Gmail Exception: " . $e->getMessage();
        }
    } else {
        $errors[] = "Gmail credentials not configured";
    }

    // =====================================================
    // PROVIDER 2 (Fallback): Brevo / Generic SMTP Relay
    // Uses config from mail_config.php (MAIL_HOST, MAIL_PORT, etc.)
    // =====================================================
    if (!$success) {
        $b_host = defined('MAIL_HOST') ? MAIL_HOST : (getenv('MAIL_HOST') ?: '');
        $b_port = defined('MAIL_PORT') ? MAIL_PORT : (int)(getenv('MAIL_PORT') ?: 2525);
        $b_user = defined('MAIL_USERNAME') ? MAIL_USERNAME : (getenv('MAIL_USERNAME') ?: '');
        $b_pass = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : (getenv('MAIL_PASSWORD') ?: '');
        $b_enc  = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : (getenv('MAIL_ENCRYPTION') ?: 'tls');

        if ($b_host && $b_user && $b_pass) {
            try {
                $prefix = ($b_enc === 'ssl') ? 'ssl://' : 'tcp://';
                $ctx = stream_context_create(['ssl' => [
                    'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
                ]]);
                $socket = @stream_socket_client($prefix . $b_host . ':' . $b_port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

                if ($socket) {
                    stream_set_timeout($socket, 15);
                    $smtpRead($socket); // 220 banner

                    $smtpWrite($socket, "EHLO " . gethostname());
                    $ehloRes = $smtpRead($socket);

                    // Upgrade to TLS if using tcp:// and server supports STARTTLS
                    if ($prefix === 'tcp://' && strpos($ehloRes, 'STARTTLS') !== false) {
                        $smtpWrite($socket, "STARTTLS");
                        $smtpRead($socket);
                        @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                        $smtpWrite($socket, "EHLO " . gethostname());
                        $smtpRead($socket);
                    }

                    $smtpWrite($socket, "AUTH LOGIN");
                    $smtpRead($socket);
                    $smtpWrite($socket, base64_encode($b_user));
                    $smtpRead($socket);
                    $smtpWrite($socket, base64_encode($b_pass));
                    $authRes = $smtpRead($socket);

                    if (strpos($authRes, "235") !== false) {
                        $senderEmail = $b_user;
                        $smtpWrite($socket, "MAIL FROM: <$senderEmail>");
                        $smtpRead($socket);
                        $smtpWrite($socket, "RCPT TO: <$to>");
                        $smtpRead($socket);
                        $smtpWrite($socket, "DATA");
                        $smtpRead($socket);

                        $headers = $buildHeaders($senderEmail, $fromName);
                        $smtpWrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.");
                        $dataRes = $smtpRead($socket);

                        if (strpos($dataRes, "250") !== false) {
                            $success = true;
                            $errors = []; // Clear Gmail errors since Brevo succeeded
                        } else {
                            $errors[] = "Brevo DATA rejected: " . trim($dataRes);
                        }
                    } else {
                        $errors[] = "Brevo Auth Failed (Check API Key)";
                    }
                    $smtpWrite($socket, "QUIT");
                    @fclose($socket);
                } else {
                    $errors[] = "Brevo Connection Failed ($errstr)";
                }
            } catch (Exception $e) {
                $errors[] = "Brevo Exception: " . $e->getMessage();
            }
        }
    }

    // --- Logging ---
    if (!$success && !empty($errors)) {
        file_put_contents($logFile, "[$date] FAILED: " . implode(" | ", $errors) . "\n", FILE_APPEND);
    }
    $status = $success ? "[SUCCESS]" : "[FAILED]";
    file_put_contents($logFile, "$status [$date] TO: $to | SUBJECT: $subject\n", FILE_APPEND);
    return $success;
}
