<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

use App\Services\CompetitorService;
use App\Database;

$db = Database::getInstance();

echo "=============================================\n";
echo "   🕵️ TESTE DE INTELIGÊNCIA COMPETITIVA (PHASE 16) \n";
echo "=============================================\n";

// 1. Setup Data
$itemId = 'MLB-HIST-TEST';
$db->exec("DELETE FROM competitor_items WHERE ml_item_id = '$itemId'");
$db->exec("INSERT INTO competitor_items (account_id, ml_item_id, seller_id, title, price, status, created_at)
           VALUES (1, '$itemId', 12345, 'Item Historico Teste', 100.00, 'active', NOW())");

$internalId = $db->lastInsertId();
// Clean history for this item (cascade delete should handle, but just in case)
// Warning: Foreign key might prevent deletion if cascade not set properly in setup? setup had cascade.
// Actually let's just use internalId.

echo "[1] Item criado (ID Interno: $internalId)\n";

// 2. Inject History
$history = [
    ['2023-01-01', 100.00],
    ['2023-01-02', 95.00],
    ['2023-01-03', 90.00]
];

// Note: Using dates far in past to avoid conflict with "today" if script runs
$stmt = $db->prepare("INSERT INTO competitor_price_history (competitor_item_id, price, recorded_at) VALUES (?, ?, ?)");
foreach ($history as $h) {
    $stmt->execute([$internalId, $h[1], $h[0]]);
}
echo "[2] Histórico simulado inserido (3 dias)\n";

// 3. Retrieve
$service = new CompetitorService(1);
$data = $service->getPriceHistory($itemId, 3650); // High days to catch 2023

echo "[3] Dados recuperados: " . count($data) . " registros\n";

if (count($data) >= 3 && $data[2]['price'] == 90.00) {
    echo "✅ SUCESSO: Histórico recuperado corretamente!\n";
} else {
    echo "❌ FALHA: Dados incorretos. " . json_encode($data) . "\n";
}

// Cleanup
$db->exec("DELETE FROM competitor_items WHERE id = $internalId");
