<?php
http_response_code(404);
exit;

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared\n\n";
} else {
    echo "OPcache not available\n\n";
}

// Test login page via curl
$ch = curl_init('https://eskill.com.br/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== LOGIN PAGE ===\n";
echo "HTTP: $httpCode\n";
if ($error) echo "Error: $error\n";
echo "Body length: " . strlen($body) . " bytes\n";
echo "Contains form: " . (str_contains($body, '<form') ? 'YES' : 'NO') . "\n";
echo "Contains 'Fatal error': " . (stripos($body, 'fatal error') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Startup validation': " . (stripos($body, 'Startup validation') !== false ? 'YES' : 'NO') . "\n\n";

// Test auth/authorize
$ch = curl_init('https://eskill.com.br/auth/authorize');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== AUTH/AUTHORIZE ===\n";
echo "HTTP: $httpCode\n";
// Extract Location header
if (preg_match('/Location:\s*(.+)/i', $result, $m)) {
    echo "Redirect: " . trim($m[1]) . "\n";
}

// Check recent error log  
echo "\n=== RECENT ERRORS (last 1KB) ===\n";
$logFile = dirname(__DIR__) . '/storage/logs/error.log';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    $fp = fopen($logFile, 'r');
    if ($size > 1000) fseek($fp, -1000, SEEK_END);
    echo fread($fp, 1000);
    fclose($fp);
}
