<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

use App\Services\SettlementService;
use App\Database;

$db = Database::getInstance();

echo "=============================================\n";
echo "   🛡️ TESTE CONFIAVEL DE CONCILIAÇÃO (PHASE 14) \n";
echo "=============================================\n";

// 1. Create a Dummy Order
$orderId = time(); // Must be numeric for BIGINT
$netProfit = 95.50; // We expect to receive 95.50

$db->prepare("DELETE FROM ml_orders WHERE ml_order_id = ?")->execute([$orderId]);
$db->prepare("DELETE FROM financial_settlements WHERE external_reference = ?")->execute([$orderId]);

$stmt = $db->prepare("
    INSERT INTO ml_orders (ml_order_id, ml_account_id, total_amount, net_profit, status, date_created, order_data, synced_at)
    VALUES (?, 1, 100.00, ?, 'paid', NOW(), '{}', NOW())
");
$stmt->execute([$orderId, $netProfit]);

echo "[1] Pedido Criado: ID $orderId | Esperado: R$ $netProfit\n";

// 2. Create Dummy CSV
$csvFile = __DIR__ . '/temp_settlement.csv';
$fp = fopen($csvFile, 'w');
// Header
fputcsv($fp, ['DATE', 'SOURCE_ID', 'EXTERNAL_REF', 'DESC', 'TYPE', 'GROSS', 'NET']);
// Matching Row
fputcsv($fp, ['01/01/2024', 'SET-' . time(), $orderId, 'Venda de Produto', 'sale', '100,00', '95,50']);
// Mismatch Row
fputcsv($fp, ['01/01/2024', 'SET-ERR-' . time(), $orderId . '-ERR', 'Venda Errada', 'sale', '100,00', '50,00']);
fclose($fp);

echo "[2] CSV Temporário gerado: $csvFile\n";

// 3. Import
$service = new SettlementService();
$result = $service->importReport($csvFile);
echo "[3] Importação: " . json_encode($result) . "\n";

// 4. Reconcile
$res = $service->reconcile();
echo "[4] Conciliação Executada: " . json_encode($res) . "\n";

// 5. Verify Database
$stmt = $db->prepare("SELECT status, net_amount FROM financial_settlements WHERE external_reference = ?");
$stmt->execute([$orderId]);
$row = $stmt->fetch();

if ($row && $row['status'] === 'CONCILIATED') {
    echo "✅ SUCESSO: Registro conciliado corretamente! (Valor: {$row['net_amount']})\n";
} else {
    echo "❌ FALHA: Status esperado CONCILIATED, obtido: " . ($row['status'] ?? 'NULL') . "\n";
}

// Cleanup
unlink($csvFile);
