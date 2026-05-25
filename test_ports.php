<?php
echo "Testing smtp.gmail.com connections...\n";

$ports = [587, 465];
foreach ($ports as $port) {
    echo "\nTesting port $port...\n";
    $prefix = ($port === 465) ? 'ssl://' : 'tcp://';
    $t1 = microtime(true);
    $socket = @fsockopen($prefix . 'smtp.gmail.com', $port, $errno, $errstr, 5);
    $t2 = microtime(true);
    $elapsed = round($t2 - $t1, 3);
    if ($socket) {
        echo "SUCCESS: Connected in $elapsed seconds.\n";
        fclose($socket);
    } else {
        echo "FAILED: $errstr ($errno) in $elapsed seconds.\n";
    }
}
?>
