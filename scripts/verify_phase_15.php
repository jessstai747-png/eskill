<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

use App\Services\ReturnService;
use App\Database;

$db = Database::getInstance();

echo "=============================================\n";
echo "   🔄 TESTE DE RMA E DEVOLUÇÕES (PHASE 15) \n";
echo "=============================================\n";

// 1. Setup Test Data
$orderId = time(); 
$claimId = 'CLM-' . time();
$sku = 'TEST-ITEM-' . rand(1000, 9999);

// Create dummy item for inventory check
$db->exec("DELETE FROM items WHERE sku = '$sku'");
$db->exec("INSERT INTO items (ml_item_id, account_id, title, price, available_quantity, sku, status) 
           VALUES ('MLB-{$sku}', 1, 'Test Item', 10.00, 10, '$sku', 'active')");

echo "[1] Setup: Item $sku criado com Estoque: 10\n";

// 2. Register Return
$service = new ReturnService();
$res = $service->registerReturn($orderId, $claimId, $sku, 1);
echo "[2] Registro de Devolução: " . json_encode($res) . "\n";
$returnId = $res['id'] ?? null;

if (!$returnId) die("❌ Falha ao registrar devolução\n");

// 3. Receive Item
$service->receiveItem($returnId);
echo "[3] Item Recebido no CD (Status CHANGED)\n";

// 4. Inspect and Restock
// Condition 5 (New), Resolution RESTOCK, Inspector ID 1 (Admin)
$res = $service->completeInspection($returnId, 5, 'RESTOCK', 1, 'Produto em perfeito estado, lacrado.');
echo "[4] Inspeção Concluída: " . json_encode($res) . "\n";

// 5. Verify Inventory
$stmt = $db->query("SELECT available_quantity FROM items WHERE sku = '$sku'");
$qty = $stmt->fetchColumn();

if ($qty == 11) { // Started with 10, restocked 1 -> 11
    echo "✅ SUCESSO: Estoque atualizado para $qty (+1)\n";
} else {
    echo "❌ FALHA: Estoque esperado 11, obtido: $qty\n";
}

// Cleanup
$db->exec("DELETE FROM returns WHERE id = $returnId");
$db->exec("DELETE FROM items WHERE sku = '$sku'");
