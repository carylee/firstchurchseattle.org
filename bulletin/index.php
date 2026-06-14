<?php
// Web bulletin server. Default (no query) serves the LATEST web bulletin — the
// stable link the church publishes. `?date=YYYY-MM-DD` serves that specific
// week's web page, and `&format=pdf` serves its print PDF. Files are written here
// by the firstchurch-bulletin-publish REST route (the editor's Publish action).

// Start output buffering with the Gzip handler.
ob_start("ob_gzhandler");

$dir    = __DIR__;
$date   = isset($_GET['date']) ? (string) $_GET['date'] : '';
$format = isset($_GET['format']) ? (string) $_GET['format'] : 'html';

// A valid date is the only thing ever interpolated into a path — anchored
// YYYY-MM-DD, so it doubles as the path-traversal guard.
$valid_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;

// Modern 2026 browser caching: 5 minutes. Reduces server load while ensuring
// next week's bulletin shows up on time.
$cache = 'public, max-age=300, must-revalidate';

if ($valid_date && $format === 'pdf') {
    $file = $dir . '/' . $date . '.pdf';
    if (is_file($file)) {
        header('Cache-Control: ' . $cache);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $date . '.pdf"');
        readfile($file);
        exit;
    }
} elseif ($valid_date) {
    $file = $dir . '/' . $date . '.html';
    if (is_file($file)) {
        header('Cache-Control: ' . $cache);
        header('Content-Type: text/html; charset=utf-8');
        echo file_get_contents($file);
        exit;
    }
} else {
    // Default: the latest web bulletin (most recent by date — files are named
    // YYYY-MM-DD.html so a lexical sort is chronological).
    $files = glob($dir . '/*.html');
    if (!empty($files)) {
        sort($files);
        $latest_file = end($files);
        header('Cache-Control: ' . $cache);
        header('Content-Type: text/html; charset=utf-8');
        echo file_get_contents($latest_file);
        exit;
    }
}

header("HTTP/1.0 404 Not Found");
