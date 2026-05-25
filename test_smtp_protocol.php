<?php
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

require_once "config_mail_local.php";

$to = "simonnjoro965@gmail.com";
$g_user = defined('GMAIL_USERNAME') ? GMAIL_USERNAME : '';
$g_pass = defined('GMAIL_PASSWORD') ? GMAIL_PASSWORD : '';

echo "Gmail User: $g_user\n";
echo "Gmail Pass: " . (strlen($g_pass) > 0 ? "Configured (" . strlen($g_pass) . " chars)" : "Not Configured") . "\n";

if (!$g_user || !$g_pass) {
    die("Error: Gmail credentials not found in config_mail_local.php\n");
}

$smtpRead = function($s) {
    $data = "";
    $timeout = 0;
    while ($str = fgets($s, 515)) {
        echo "S: " . $str;
        flush();
        $data .= $str;
        if (isset($str[3]) && $str[3] === " ") break;
        $timeout++;
        if ($timeout > 100) break;
    }
    return $data;
};

$smtpWrite = function($s, $cmd) {
    echo "C: " . $cmd . "\n";
    flush();
    fputs($s, $cmd . "\r\n");
};

$port = 587;
echo "\nConnecting to tcp://smtp.gmail.com:$port...\n";
flush();

$ctx = stream_context_create(['ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
]]);

$socket = stream_socket_client('tcp://smtp.gmail.com:' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);

if (!$socket) {
    die("Connection failed: $errstr ($errno)\n");
}

stream_set_timeout($socket, 10);
$smtpRead($socket); // Banner

$smtpWrite($socket, "EHLO [127.0.0.1]");
$smtpRead($socket);

$smtpWrite($socket, "STARTTLS");
$tlsRes = $smtpRead($socket);

if (strpos($tlsRes, "220") !== false) {
    echo "Upgrading to TLS...\n";
    flush();
    $cryptoOk = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if ($cryptoOk) {
        echo "TLS upgrade successful!\n";
        flush();
        
        $hostname = "[127.0.0.1]";
        echo "Testing EHLO with '$hostname'...\n";
        flush();
        $smtpWrite($socket, "EHLO " . $hostname);
        $ehloRes = $smtpRead($socket);
        
        $smtpWrite($socket, "AUTH LOGIN");
        $smtpRead($socket);
        
        $smtpWrite($socket, base64_encode($g_user));
        $smtpRead($socket);
        
        $smtpWrite($socket, base64_encode($g_pass));
        $authRes = $smtpRead($socket);
        
        if (strpos($authRes, "235") !== false) {
            echo "SUCCESS: SMTP Authentication Succeeded!\n";
            flush();
        } else {
            echo "FAILED: SMTP Authentication Failed: " . trim($authRes) . "\n";
            flush();
        }
    } else {
        echo "FAILED: TLS upgrade failed.\n";
        flush();
    }
} else {
    echo "FAILED: STARTTLS rejected.\n";
    flush();
}

$smtpWrite($socket, "QUIT");
fclose($socket);
?>
