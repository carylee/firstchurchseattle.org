<?php
// Start output buffering with the Gzip handler
ob_start("ob_gzhandler");

$files = glob(__DIR__ . '/*.html');

if (!empty($files)) {
    sort($files);
    $latest_file = end($files);

    // Modern 2026 Browser Caching: Cache for 5 minutes (300 seconds)
    // This reduces server load while ensuring they get next week's bulletin on time
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('Content-Type: text/html; charset=utf-8');

    echo file_get_contents($latest_file);
    exit;
}

header("HTTP/1.0 404 Not Found");
