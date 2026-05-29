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
 * Universal Dual-Provider Email Delivery System
 * 
 * Provider 1 (Primary): Gmail SMTP via port 587 + STARTTLS
 * Provider 2 (Fallback): Brevo SMTP relay via port 2525
 * 
 * Includes Reply-To header so recipients can reply directly to the church admin.
 */
function send_church_email(string $to, string $subject, string $message): bool {
    $date = date('Y-m-d H:i:s');
    $logFile = defined('MAIL_LOG_FILE') ? MAIL_LOG_FILE : __DIR__ . '/logs/mail.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

    // Support for locally saved API settings from the Dashboard
    if (file_exists(__DIR__ . '/config_mail_local.php')) {
        include_once __DIR__ . '/config_mail_local.php';
    }

    // Validate recipient email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        @file_put_contents($logFile, "[$date] FAILED: Invalid recipient email: $to\n", FILE_APPEND);
        return false;
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
        $timeout = 0;
        while ($str = @fgets($s, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === " ") break;
            $timeout++;
            if ($timeout > 100) break; // Safety valve
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
    // PROVIDER 1: Gmail SMTP via Auto-Port Detection (587/465)
    // =====================================================
    $g_user = defined('GMAIL_USERNAME') ? GMAIL_USERNAME : (getenv('GMAIL_USERNAME') ?: '');
    $g_pass = defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : (getenv('GMAIL_PASSWORD') ?: '');
    $g_pass = str_replace(' ', '', $g_pass);

    if ($g_user && $g_pass) {
        if (!extension_loaded('openssl')) {
            $errors[] = "PHP 'openssl' extension is missing. Secure Gmail connection is impossible without it.";
            return false;
        }
        $ports = [587, 465];
        $gmail_errors = [];
        
        foreach ($ports as $port) {
            if ($success) break;
            
            // 1 attempt per port is optimal for web-request responsiveness
            for ($attempt = 1; $attempt <= 1 && !$success; $attempt++) {
                try {
                    $prefix = ($port === 465) ? 'ssl://' : 'tcp://';
                    $ctx = stream_context_create(['ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]]);
                    
                    $socket = @stream_socket_client($prefix . 'smtp.gmail.com:' . $port, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
                    if ($socket) {
                        stream_set_timeout($socket, 5);
                        $smtpRead($socket); // Banner

                        $smtpWrite($socket, "EHLO [127.0.0.1]");
                        $ehloRes = $smtpRead($socket);

                        if ($port === 587) {
                            // Upgrade to TLS
                            $smtpWrite($socket, "STARTTLS");
                            $tlsRes = $smtpRead($socket);
                            if (strpos($tlsRes, "220") !== false) {
                                $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                                if ($cryptoOk) {
                                    $smtpWrite($socket, "EHLO " . gethostname());
                                    $smtpRead($socket);
                                } else {
                                    $gmail_errors["$port-attempt-$attempt"] = "Gmail 587: TLS upgrade failed (attempt $attempt)";
                                    @fclose($socket); continue;
                                }
                            } else {
                                $gmail_errors["$port-attempt-$attempt"] = "Gmail 587: STARTTLS rejected (attempt $attempt)";
                                @fclose($socket); continue;
                            }
                        }

                        // Auth
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
                            $rcptRes = $smtpRead($socket);
                            
                            if (strpos($rcptRes, "250") !== false) {
                                $smtpWrite($socket, "DATA");
                                $smtpRead($socket);
                                $headers = $buildHeaders($g_user, $fromName);
                                $smtpWrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.");
                                $dataRes = $smtpRead($socket);
                                if (strpos($dataRes, "250") !== false) {
                                    $success = true;
                                } else {
                                    $gmail_errors["$port-attempt-$attempt"] = "Gmail $port: DATA rejected: " . trim($dataRes);
                                }
                            } else {
                                $gmail_errors["$port-attempt-$attempt"] = "Gmail $port: RCPT rejected: " . trim($rcptRes);
                            }
                        } else {
                            $gmail_errors["$port-attempt-$attempt"] = "Gmail $port: Auth failed (535). Ensure you used a 16-char 'App Password', not your normal password.";
                        }
                        
                        $smtpWrite($socket, "QUIT");
                        @fclose($socket);
                    } else {
                        $gmail_errors["$port-attempt-$attempt"] = "Gmail $port: Connection failed ($errstr)";
                    }
                } catch (Exception $e) {
                    $gmail_errors["$port-attempt-$attempt"] = "Gmail $port error: " . $e->getMessage();
                }
            }
        }
        
        // Consolidate Gmail errors
        if (!$success && !empty($gmail_errors)) {
            $errors[] = "Gmail SMTP failed: " . implode(" | ", array_slice($gmail_errors, -2));
        }
    } else {
        $errors[] = "Gmail credentials not configured (Check Gmail Setup section above)";
    }

    // =====================================================
    // PROVIDER 2 (Fallback): Brevo / Generic SMTP Relay
    // =====================================================
    if (!$success) {
        $b_host = defined('MAIL_HOST') ? MAIL_HOST : (getenv('MAIL_HOST') ?: '');
        $b_port = defined('MAIL_PORT') ? MAIL_PORT : (int)(getenv('MAIL_PORT') ?: 2525);
        $b_user = defined('MAIL_USERNAME') ? MAIL_USERNAME : (getenv('MAIL_USERNAME') ?: '');
        $b_pass = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : (getenv('MAIL_PASSWORD') ?: '');
        $b_enc  = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : (getenv('MAIL_ENCRYPTION') ?: 'tls');

        if ($b_host && $b_user && $b_pass) {
            $brevo_errors = [];
            
            // 1 attempt is optimal for web-request responsiveness
            for ($attempt = 1; $attempt <= 1 && !$success; $attempt++) {
                try {
                    $prefix = ($b_enc === 'ssl') ? 'ssl://' : 'tcp://';
                    $ctx = stream_context_create(['ssl' => [
                        'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
                    ]]);
                    $socket = @stream_socket_client($prefix . $b_host . ':' . $b_port, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);

                    if ($socket) {
                        stream_set_timeout($socket, 5);
                        $smtpRead($socket);

                        $smtpWrite($socket, "EHLO " . gethostname());
                        $ehloRes = $smtpRead($socket);

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
                                $errors = [];
                            } else {
                                $brevo_errors["attempt-$attempt"] = "Brevo DATA rejected: " . trim($dataRes);
                            }
                        } else {
                            $brevo_errors["attempt-$attempt"] = "Brevo Auth Failed (attempt $attempt): " . trim($authRes);
                        }
                        $smtpWrite($socket, "QUIT");
                        @fclose($socket);
                    } else {
                        $brevo_errors["attempt-$attempt"] = "Brevo Connection Failed ($errstr)";
                    }
                } catch (Exception $e) {
                    $brevo_errors["attempt-$attempt"] = "Brevo Exception (attempt $attempt): " . $e->getMessage();
                }
            }
            
            if (!$success && !empty($brevo_errors)) {
                $errors[] = "Brevo SMTP failed: " . implode(" | ", array_slice($brevo_errors, -1));
            }
        } else {
            if (!$success && !empty($errors)) {
                // Only add Brevo config error if Gmail also failed
                @$errors[] = "Brevo not configured as fallback (MAIL_HOST/MAIL_USERNAME/MAIL_PASSWORD)";
            }
        }
    }

    // --- Logging ---
    $status = $success ? "[SUCCESS]" : "[FAILED]";
    if (!$success && !empty($errors)) {
        @file_put_contents($logFile, "[$date] ERRORS: " . implode(" | ", $errors) . "\n", FILE_APPEND);
    }
    @file_put_contents($logFile, "$status [$date] TO: $to | SUBJECT: $subject\n", FILE_APPEND);
    return $success;
}

/**
 * Fetch reply emails from Gmail via IMAP.
 * Stores new replies into the email_replies database table.
 * Returns count of new replies fetched.
 */
function fetch_gmail_replies(PDO $pdo): array {
    $results = ['new' => 0, 'total' => 0, 'error' => ''];
    
    $g_user = defined('GMAIL_USERNAME') ? GMAIL_USERNAME : (getenv('GMAIL_USERNAME') ?: '');
    $g_pass = defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : (getenv('GMAIL_PASSWORD') ?: '');
    $g_pass = str_replace(' ', '', $g_pass);
    
    if (!$g_user || !$g_pass) {
        $results['error'] = 'Gmail credentials not configured.';
        return $results;
    }

    // Ensure the email_replies table exists
    ensure_replies_table($pdo);

    // Check if IMAP extension is available
    if (!function_exists('imap_open')) {
        // Fallback: Use raw socket IMAP for environments without php-imap
        return fetch_replies_via_socket($pdo, $g_user, $g_pass);
    }

    try {
        $mailbox = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
        $imap = @imap_open($mailbox, $g_user, $g_pass);
        
        if (!$imap) {
            $results['error'] = 'IMAP connection failed: ' . imap_last_error();
            return $results;
        }

        // Search for recent emails (last 7 days) that are replies (have Re: in subject)
        $since = date('d-M-Y', strtotime('-7 days'));
        $emails = imap_search($imap, "SINCE \"$since\"");

        if ($emails) {
            $results['total'] = count($emails);
            
            foreach ($emails as $emailNum) {
                $header = imap_headerinfo($imap, $emailNum);
                $overview = imap_fetch_overview($imap, (string)$emailNum, 0);
                
                if (!$header || !$overview) continue;
                
                $uid = imap_uid($imap, $emailNum);
                $gmailUid = 'gmail_' . $uid;
                
                // Skip if already stored
                $check = $pdo->prepare("SELECT id FROM email_replies WHERE gmail_uid = ?");
                $check->execute([$gmailUid]);
                if ($check->fetch()) continue;
                
                // Get sender info
                $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
                $fromName = isset($header->from[0]->personal) ? imap_utf8($header->from[0]->personal) : $fromEmail;
                
                // Skip our own sent messages
                if (strcasecmp($fromEmail, $g_user) === 0) continue;
                
                $subject = isset($overview[0]->subject) ? imap_utf8($overview[0]->subject) : '(No Subject)';
                $receivedAt = date('Y-m-d H:i:s', strtotime($header->date));
                
                // Get body (prefer plain text, fallback to HTML)
                $body = get_imap_body($imap, $emailNum);
                
                // Store in database
                $stmt = $pdo->prepare("INSERT INTO email_replies (from_email, from_name, subject, body, received_at, gmail_uid) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fromEmail, $fromName, $subject, $body, $receivedAt, $gmailUid]);
                $results['new']++;
            }
        }
        
        imap_close($imap);
    } catch (Exception $e) {
        $results['error'] = 'IMAP error: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Get email body from IMAP message
 */
function get_imap_body($imap, int $msgNum): string {
    $structure = imap_fetchstructure($imap, $msgNum);
    
    if (!$structure->parts) {
        // Simple single-part message
        $body = imap_fetchbody($imap, $msgNum, '1');
        if ($structure->encoding === 3) $body = base64_decode($body);
        if ($structure->encoding === 4) $body = quoted_printable_decode($body);
        return trim(strip_tags($body));
    }
    
    // Multipart — find text/plain first, then text/html
    $body = '';
    foreach ($structure->parts as $partNum => $part) {
        if ($part->subtype === 'PLAIN') {
            $body = imap_fetchbody($imap, $msgNum, (string)($partNum + 1));
            if ($part->encoding === 3) $body = base64_decode($body);
            if ($part->encoding === 4) $body = quoted_printable_decode($body);
            return trim($body);
        }
    }
    
    // Fallback to HTML
    foreach ($structure->parts as $partNum => $part) {
        if ($part->subtype === 'HTML') {
            $body = imap_fetchbody($imap, $msgNum, (string)($partNum + 1));
            if ($part->encoding === 3) $body = base64_decode($body);
            if ($part->encoding === 4) $body = quoted_printable_decode($body);
            return trim(strip_tags($body));
        }
    }
    
    return '(Could not read message body)';
}

/**
 * Fallback IMAP fetch via raw sockets (for servers without php-imap extension)
 */
function fetch_replies_via_socket(PDO $pdo, string $user, string $pass): array {
    $results = ['new' => 0, 'total' => 0, 'error' => ''];
    
    try {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
        ]]);
        $socket = @stream_socket_client('ssl://imap.gmail.com:993', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
        
        if (!$socket) {
            $results['error'] = "IMAP connection failed: $errstr";
            return $results;
        }
        
        stream_set_timeout($socket, 30);
        $greeting = fgets($socket, 1024); // Read greeting
        
        // Login
        fwrite($socket, "A001 LOGIN \"$user\" \"$pass\"\r\n");
        $loginRes = '';
        while ($line = fgets($socket, 1024)) {
            $loginRes .= $line;
            if (preg_match('/^A001 /', $line)) break;
        }
        
        if (strpos($loginRes, 'A001 OK') === false) {
            @fclose($socket);
            $results['error'] = 'IMAP login failed. Check Gmail credentials and App Password.';
            return $results;
        }
        
        // Select INBOX
        fwrite($socket, "A002 SELECT INBOX\r\n");
        $selectRes = '';
        while ($line = fgets($socket, 1024)) {
            $selectRes .= $line;
            if (preg_match('/^A002 /', $line)) break;
        }
        
        // Search for recent emails (last 7 days)
        $since = date('d-M-Y', strtotime('-7 days'));
        fwrite($socket, "A003 SEARCH SINCE $since\r\n");
        $searchRes = '';
        while ($line = fgets($socket, 1024)) {
            $searchRes .= $line;
            if (preg_match('/^A003 /', $line)) break;
        }
        
        // Parse UIDs from search results
        $uids = [];
        if (preg_match('/\* SEARCH (.+)/', $searchRes, $m)) {
            $uids = array_filter(array_map('intval', explode(' ', trim($m[1]))));
        }
        
        $results['total'] = count($uids);
        
        // Fetch headers for each message (limit to last 50)
        $uids = array_slice($uids, -50);
        $cmdIdx = 4;
        
        foreach ($uids as $uid) {
            $tag = "A" . str_pad((string)$cmdIdx, 3, '0', STR_PAD_LEFT);
            $cmdIdx++;
            
            $gmailUid = 'gmail_sock_' . $uid;
            
            // Skip if already stored
            $check = $pdo->prepare("SELECT id FROM email_replies WHERE gmail_uid = ?");
            $check->execute([$gmailUid]);
            if ($check->fetch()) continue;
            
            // Fetch envelope and body
            fwrite($socket, "$tag FETCH $uid (ENVELOPE BODY[TEXT])\r\n");
            $fetchRes = '';
            while ($line = fgets($socket, 4096)) {
                $fetchRes .= $line;
                if (preg_match("/^$tag /", $line)) break;
            }
            
            // Parse envelope
            $fromEmail = '';
            $fromName = '';
            $subject = '(No Subject)';
            $receivedAt = date('Y-m-d H:i:s');
            
            if (preg_match('/ENVELOPE \("([^"]*)"/', $fetchRes, $em)) {
                $receivedAt = date('Y-m-d H:i:s', strtotime($em[1]) ?: time());
            }
            if (preg_match('/ENVELOPE \("[^"]*" "([^"]*)"/', $fetchRes, $sm)) {
                $subject = $sm[1] ?: '(No Subject)';
            }
            
            // Try to extract From
            if (preg_match('/From:\s*(?:"?([^"<]*)"?\s*)?<?([^>\s]+@[^>\s]+)>?/i', $fetchRes, $fm)) {
                $fromName = trim($fm[1] ?? '');
                $fromEmail = trim($fm[2]);
            }
            
            // Skip our own messages
            if ($fromEmail && strcasecmp($fromEmail, $user) === 0) continue;
            if (!$fromEmail) continue;
            
            // Extract body text (simplified)
            $body = '';
            if (preg_match('/\r\n\r\n(.+)/s', $fetchRes, $bm)) {
                $body = trim(strip_tags($bm[1]));
                $body = preg_replace('/^A\d{3} .*/m', '', $body); // Remove IMAP tags
                // Remove some common IMAP artifacts
                $body = preg_replace('/\)\r\n$/', '', $body);
                $body = trim($body);
                if (strlen($body) > 2000) $body = substr($body, 0, 2000) . '...';
            }
            
            if (!$body) $body = '(Could not read message body)';
            
            // Store in database
            $stmt = $pdo->prepare("INSERT INTO email_replies (from_email, from_name, subject, body, received_at, gmail_uid) VALUES (?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$fromEmail, $fromName ?: $fromEmail, $subject, $body, $receivedAt, $gmailUid]);
                $results['new']++;
            } catch (Exception $e) {
                // Duplicate UID or other error — skip silently
            }
        }
        
        // Logout
        fwrite($socket, "A999 LOGOUT\r\n");
        @fclose($socket);
        
    } catch (Exception $e) {
        $results['error'] = 'Socket IMAP error: ' . $e->getMessage();
    }
    
    return $results;
}

/**
 * Ensure the email_replies table exists (auto-migration)
 */
function ensure_replies_table(PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    try {
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_replies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_email TEXT NOT NULL,
                from_name TEXT DEFAULT '',
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                received_at DATETIME NOT NULL,
                is_read INTEGER DEFAULT 0,
                gmail_uid TEXT UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                from_email VARCHAR(255) NOT NULL,
                from_name VARCHAR(255) DEFAULT '',
                subject VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                received_at DATETIME NOT NULL,
                is_read TINYINT DEFAULT 0,
                gmail_uid VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {
        // Table likely already exists
    }
}
