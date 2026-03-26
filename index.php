<?php
declare(strict_types=1);
// Immediate health check response for Render
if ($_SERVER['REQUEST_URI'] === '/health' || $_SERVER['REQUEST_URI'] === '/health.php' || (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Render') !== false)) {
    http_response_code(200);
    echo "OK";
    exit;
}

header("Location: login.php");
exit;
