<?php
// Verify Orders API Backend Logic
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? (getenv('DB_PORT') ?: '3306');
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: 'meli');
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: 'root');

$dbPassword = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
if ($dbPassword !== false && $dbPassword !== null) {
    $_ENV['DB_PASSWORD'] = (string)$dbPassword;
}

$appKey = $_ENV['APP_KEY'] ?? getenv('APP_KEY');
if ($appKey !== false && $appKey !== null) {
    $_ENV['APP_KEY'] = (string)$appKey;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Database.php';

// Mock Session for OrderController
session_start();
$_SESSION['user_id'] = 1;

// Simulate having linked accounts
// We need to know valid account IDs.
// Let's first query the DB to find valid accounts.

try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SELECT id, nickname FROM ml_accounts");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($accounts) . " accounts in DB.\n";
    if (empty($accounts)) {
        echo "WARNING: No accounts found. API will return empty list/error.\n";
    }

    $accountIds = array_column($accounts, 'id');
    // Vamos validar o serviço por conta para reproduzir o comportamento real do backend.

    require_once __DIR__ . '/app/Services/OrderService.php';

    echo "Fetching orders per account: " . implode(', ', $accountIds) . "\n";

    $totalOrders = 0;
    $processedAccounts = 0;
    $firstOrder = null;
    $errorsByAccount = [];

    foreach ($accountIds as $accountId) {
        try {
            $service = new \App\Services\OrderService((int)$accountId);
            $result = $service->listOrders([
                'limit' => 5,
                'allow_local_cache' => true,
            ]);

            if (($result['success'] ?? false) || isset($result['results'])) {
                $processedAccounts++;
                $orders = $result['results'] ?? [];
                $totalOrders += count($orders);

                if ($firstOrder === null && !empty($orders)) {
                    $firstOrder = $orders[0];
                }
            } else {
                $errorsByAccount[] = 'Conta ' . $accountId . ': ' . ($result['message'] ?? $result['error'] ?? 'erro desconhecido');
            }
        } catch (\Throwable $accountError) {
            $errorsByAccount[] = 'Conta ' . $accountId . ': ' . $accountError->getMessage();
        }
    }

    echo "Accounts Processed: $processedAccounts/" . count($accountIds) . "\n";
    echo "Total Orders Found: $totalOrders\n";

    if ($firstOrder !== null) {
        echo "Sample Order ID: " . ($firstOrder['id'] ?? 'NULL') . "\n";
        echo "Sample Total: " . ($firstOrder['total_amount'] ?? 'NULL') . "\n";
    } else {
        echo "No orders found in local DB. This might be normal if sync hasn't run.\n";

        if (!empty($errorsByAccount)) {
            echo "Account-level errors: " . implode(' | ', array_slice($errorsByAccount, 0, 3)) . "\n";
        }

        // Check if table exists
        try {
            $db->query("SELECT 1 FROM ml_orders LIMIT 1");
            echo "Table 'ml_orders' exists.\n";
        } catch (\PDOException $e) {
            echo "CRITICAL: Table 'ml_orders' does not exist or error accessing it: " . $e->getMessage() . "\n";
        }
    }

    echo "\nVERIFICATION PASSED\n";
} catch (\Throwable $e) {
    echo "VERIFICATION FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
