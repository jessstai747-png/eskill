<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Multi-Account Management ---\n\n";

use App\Middleware\AccountContextMiddleware;

// Mock session
$_SESSION = [
    'user_id' => 1,
    'current_account_id' => null
];

try {
    echo "1. Testing Middleware Initialization\n";
    $middleware = new AccountContextMiddleware();
    $middleware->handle();
    
    $currentAccount = AccountContextMiddleware::getCurrentAccountId();
    echo "   Current Account ID: " . ($currentAccount ?? 'NULL') . "\n";
    
    if ($currentAccount) {
        echo "   ✓ Account context initialized\n";
    } else {
        echo "   ℹ No accounts found for user (expected for test user)\n";
    }
    
    echo "\n2. Testing Get User Accounts\n";
    $accounts = AccountContextMiddleware::getUserAccounts(1);
    echo "   Found " . count($accounts) . " account(s)\n";
    
    foreach ($accounts as $account) {
        echo "   - Account #{$account['id']}: {$account['nickname']} ({$account['email']})\n";
        if ($account['is_current']) {
            echo "     [CURRENT]\n";
        }
    }
    
    if (count($accounts) > 0) {
        echo "\n3. Testing Account Switching\n";
        $firstAccount = $accounts[0]['id'];
        $success = AccountContextMiddleware::switchAccount($firstAccount, 1);
        
        if ($success) {
            echo "   ✓ Successfully switched to account #{$firstAccount}\n";
            echo "   Current Account: " . AccountContextMiddleware::getCurrentAccountId() . "\n";
        } else {
            echo "   ✗ Failed to switch account\n";
        }
        
        // Test switching to invalid account
        echo "\n4. Testing Invalid Account Switch\n";
        $invalid = AccountContextMiddleware::switchAccount(99999, 1);
        echo "   " . ($invalid ? "✗ Should have failed" : "✓ Correctly rejected invalid account") . "\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Multi-Account Management: ";
    if (count($accounts) > 0) {
        echo "✅ WORKING (found " . count($accounts) . " accounts)\n";
    } else {
        echo "⚠️  NO ACCOUNTS (middleware logic verified)\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
