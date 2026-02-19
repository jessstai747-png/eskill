<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

// Mock Environment
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_NAME'] = 'eskill_db'; // Adjust if needed
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = '';

try {
    $db = \App\Database::getInstance();
    $stmt = $db->query('SELECT id FROM ml_accounts LIMIT 1');
    $accountId = $stmt->fetchColumn();

    if (!$accountId) {
        die("No active account found for testing.\n");
    }

    echo "Testing with Account ID: $accountId\n";
    
    $service = new \App\Services\MercadoLivreWebhookService((int)$accountId);

    // Test Claims
    echo "--- Testing Claims Webhook ---\n";
    $claimPayload = [
        'topic' => 'claims',
        'resource' => '/v1/claims/123456789',
        'user_id' => 12345,
        'application_id' => 123
    ];
    
    $result = $service->processWebhookEvent($claimPayload);
    print_r($result);

    // Test Messages
    echo "--- Testing Messages Webhook ---\n";
    $msgPayload = [
        'topic' => 'messages',
        'resource' => '/messages/MSG123456',
        'user_id' => 12345,
        'application_id' => 123
    ];
    
    $result2 = $service->processWebhookEvent($msgPayload);
    print_r($result2);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
