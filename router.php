<?php

/**
 * Router script for PHP built-in web server
 * 
 * This script ensures all requests are routed through the main index.php
 * unless they are requesting an actual static file.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Security: prevent directory traversal attacks (SSRF)
// Resolve the real path and ensure it stays within public/
$publicDir = realpath(__DIR__ . '/public');
$path = realpath(__DIR__ . '/public' . $uri);

// If realpath returns false, the file doesn't exist — fall through to index.php
// If the resolved path doesn't start with publicDir, it's a traversal attempt
if ($path === false || strpos($path, $publicDir) !== 0) {
    $path = ''; // Force fall-through to index.php
}

// Serve static files directly
if ($uri !== '/' && $path !== '' && file_exists($path)) {
    if (is_dir($path)) {
        return false; // Let the server handle directory browsing (or return 404)
    }

    // Serve the file with appropriate mime type
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }

    readfile($path);
    return true;
}

// Route everything else through index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
