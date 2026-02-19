<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\SeoService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = Database::getInstance();
$seoService = new SeoService();

echo "Starting SEO Score Backfill...\n";

// Get all items
$stmt = $db->query("SELECT ml_item_id, data FROM items");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($items) . " items.\n";

$updated = 0;
foreach ($items as $row) {
    if (!$row['data']) continue;
    
    $data = json_decode($row['data'], true);
    if (!$data) continue;

    // Provide default health if missing
    if (!isset($data['health']) || $data['health'] === null) {
        $health = $seoService->calculateHealth($data);
        $data['health'] = $health;
        
        // Update DB
        $updateStmt = $db->prepare("UPDATE items SET data = ? WHERE ml_item_id = ?");
        $updateStmt->execute([json_encode($data), $row['ml_item_id']]);
        $updated++;
        echo "Updated Item {$row['ml_item_id']} -> Health: $health\n";
    }
}

echo "Backfill Complete. Updated $updated items.\n";
