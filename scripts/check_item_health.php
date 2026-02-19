<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\ItemService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = Database::getInstance();

// Get an active account
$stmt = $db->query("SELECT id, nickname FROM ml_accounts WHERE status = 'active' LIMIT 1");
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo "No active account found.\n";
    exit;
}

echo "Using account: " . $account['nickname'] . "\n";

$itemService = new ItemService($account['id']);

// Get one item
$response = $itemService->listItems(['limit' => 1]);

if (empty($response['items'])) {
    echo "No items found for this account.\n";
    exit;
}

$itemId = $response['items'][0]['id'];
echo "Checking item: $itemId\n";

$item = $itemService->getItem($itemId);

echo "Item Data Keys:\n";
print_r(array_keys($item));

if (isset($item['health'])) {
    echo "\nHealth found: " . $item['health'] . "\n";
} else {
    echo "\nHealth NOT found in top level.\n";
    
    // Check nested in tags or attributes?
    // Sometimes it's nowhere and requires a separate call.
}

// Check DB stored data
$stmt = $db->prepare("SELECT data FROM items WHERE ml_item_id = ?");
$stmt->execute([$itemId]);
$dbData = $stmt->fetchColumn();

if ($dbData) {
    $json = json_decode($dbData, true);
    if (isset($json['health'])) {
        echo "DB JSON has health: " . $json['health'] . "\n";
    } else {
        echo "DB JSON does NOT have health.\n";
    }
}
