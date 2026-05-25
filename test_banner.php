<?php
echo "Testing welcome banner...\n";
$socket = @stream_socket_client('tcp://smtp.gmail.com:587', $errno, $errstr, 10);
if ($socket) {
    echo "Socket opened successfully on port 587.\n";
    stream_set_timeout($socket, 5);
    $banner = fgets($socket, 515);
    if ($banner === false) {
        $info = stream_get_meta_data($socket);
        echo "Failed to read banner. Socket EOF: " . ($info['eof'] ? 'Yes' : 'No') . ", Timeout: " . ($info['timed_out'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "Banner read: " . trim($banner) . "\n";
    }
    fclose($socket);
} else {
    echo "Socket failed: $errstr ($errno)\n";
}
?>
