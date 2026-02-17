<?php

declare(strict_types=1);

/**
 * Infrastructure Diagnosis Script
 * Checks MySQL, Redis, DNS, and ML API connectivity from the PHP process context.
 */

echo "=== INFRASTRUCTURE DIAGNOSIS ===\n\n";

// 1. MySQL Socket Check
echo "1. MySQL Sockets:\n";
$sockets = ['/var/run/mysqld/mysqld.sock', '/tmp/mysql.sock', '/var/lib/mysql/mysql.sock'];
foreach ($sockets as $sock) {
    echo "   $sock: " . (file_exists($sock) ? "EXISTS" : "NOT FOUND") . "\n";
}

// 2. MySQL TCP Connection
echo "\n2. MySQL TCP (localhost:3306):\n";
$conn = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 3);
if ($conn) {
    echo "   CONNECTED (port open)\n";
    fclose($conn);
} else {
    echo "   FAILED: $errstr ($errno)\n";
}

// 3. Redis Connection
echo "\n3. Redis (127.0.0.1:6379):\n";
$conn = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 3);
if ($conn) {
    echo "   CONNECTED (port open)\n";
    fclose($conn);
} else {
    echo "   FAILED: $errstr ($errno)\n";
}

// 4. DNS Resolution
echo "\n4. DNS Resolution:\n";
$hosts = ['api.mercadolibre.com', 'google.com', 'eskill.com.br'];
foreach ($hosts as $host) {
    $ip = gethostbyname($host);
    if ($ip === $host) {
        echo "   $host: FAILED (not resolved)\n";
    } else {
        echo "   $host: $ip\n";
    }
}

// 5. ML API Public Endpoint
echo "\n5. ML API Public Test:\n";
$ch = curl_init('https://api.mercadolibre.com/sites/MLB');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_FOLLOWLOCATION => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   FAILED: $curlError\n";
} else {
    echo "   HTTP $httpCode\n";
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "   Site: " . ($data['name'] ?? 'unknown') . "\n";
    }
}

// 6. ML Token Check
echo "\n6. ML Token (.env):\n";
// Load .env manually
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, 'ML_ACCESS_TOKEN=') === 0) {
            $token = substr($line, strlen('ML_ACCESS_TOKEN='));
            $len = strlen($token);
            echo "   Token length: $len chars\n";
            echo "   Prefix: " . substr($token, 0, 20) . "...\n";

            // Test token with /users/me
            echo "\n7. ML Auth Test (/users/me):\n";
            $ch = curl_init('https://api.mercadolibre.com/users/me');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                echo "   FAILED: $err\n";
            } else {
                echo "   HTTP $code\n";
                if ($code === 200) {
                    $user = json_decode($resp, true);
                    echo "   Seller ID: " . ($user['id'] ?? 'N/A') . "\n";
                    echo "   Nickname: " . ($user['nickname'] ?? 'N/A') . "\n";
                } else {
                    $errData = json_decode($resp, true);
                    echo "   Error: " . ($errData['message'] ?? $resp) . "\n";
                }
            }
            break;
        }
    }
} else {
    echo "   .env not found\n";
}

// 8. Process info
echo "\n8. Process Context:\n";
echo "   PHP: " . PHP_VERSION . "\n";
echo "   User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";
echo "   PID: " . getmypid() . "\n";
echo "   cURL: " . (curl_version()['version'] ?? 'unknown') . "\n";

echo "\n=== END ===\n";
