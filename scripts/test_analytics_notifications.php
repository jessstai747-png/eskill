<?php

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['current_account_id'] = 1;
$_GET['limit'] = 5;
$_GET['days'] = 30;

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "=== TESTANDO ANALYTICS E NOTIFICATIONS ===\n\n";

echo "1️⃣ TESTANDO AnalyticsController...\n";
try {
    $ctrl = new App\Controllers\AnalyticsController();
    ob_start();
    $ctrl->salesMetrics();
    $json = ob_get_clean();
    $data = json_decode($json, true);
    
    if (isset($data['error'])) {
        echo "   ❌ Erro: " . $data['error'] . "\n";
    } else {
        echo "   ✅ Analytics funcionando\n";
        echo "   📊 Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
    echo "   📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n2️⃣ TESTANDO NotificationController...\n";
try {
    $ctrl = new App\Controllers\NotificationController();
    ob_start();
    $ctrl->index();
    $json = ob_get_clean();
    $data = json_decode($json, true);
    
    if (isset($data['error'])) {
        echo "   ❌ Erro: " . $data['error'] . "\n";
    } else {
        echo "   ✅ Notifications funcionando\n";
        echo "   📊 Total: " . ($data['pagination']['total'] ?? 0) . " notificações\n";
    }
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
    echo "   📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
}
