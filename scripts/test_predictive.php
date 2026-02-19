<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\AI\ML\PredictiveAnalytics;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "--- Testing Predictive Analytics ---\n";

// 1. Initialize
$accountId = 123456;
$pa = new PredictiveAnalytics($accountId);
echo "[OK] Predictive Analytics initialized.\n";

// 2. Mock Item Data
// A high-quality item to ensure good scores
$itemData = [
    'title' => "Smartphone X100 Pro 256GB 5G Câmera 108MP Tela 120Hz AMOLED - Novo Lacrado Nota Fiscal Garantia", // > 50 chars, has numbers
    'price' => 2500.00,
    'original_price' => 3000.00, // Has discount
    'images' => array_fill(0, 6, 'img.jpg'), // 6 images
    'description' => str_repeat("Excelente produto • ", 50), // > 1000 chars, has bullets
    'attributes' => array_fill(0, 10, 'attr'), // 10 attributes
    'free_shipping' => true,
    'sold_quantity' => 100
];
$itemId = "TEST-PREDICT-" . time();

// 3. Run Predictions
echo "Predicting Revenue...\n";
$prediction = $pa->predictRevenue($itemId, $itemData, ['improvement' => 20]); // Simulate 20% score improvement

print_r($prediction);

// 4. Verify
if (isset($prediction['monthly']['revenue']) && $prediction['monthly']['revenue'] > 0) {
    echo "[SUCCESS] Revenue predicted: R$ " . number_format($prediction['monthly']['revenue'], 2) . "\n";
    echo "Confidence: " . $prediction['confidence'] . "\n";
} else {
    echo "[FAILURE] Prediction failed.\n";
}

// Cleanup
$db = Database::getInstance();
$db->exec("DELETE FROM ai_predictions WHERE account_id = $accountId");
