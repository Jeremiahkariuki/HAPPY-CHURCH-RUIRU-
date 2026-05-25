<?php
echo "Testing with context...\n";
$ctx = stream_context_create(['ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
]]);
$socket = @stream_socket_client('tcp://smtp.gmail.com:587', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
if ($socket) {
    echo "Socket opened with context.\n";
    stream_set_timeout($socket, 5);
    $banner = fgets($socket, 515);
    if ($banner === false) {
        echo "Failed to read banner with context.\n";
    } else {
        echo "Banner read: " . trim($banner) . "\n";
    }
    fclose($socket);
} else {
    echo "Socket failed: $errstr ($errno)\n";
}
?>
