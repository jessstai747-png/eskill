<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

use App\Services\ReportService;
use App\Database;

$db = Database::getInstance();

echo "=============================================\n";
echo "   📊 TESTE DE RELATÓRIOS REAIS (PHASE 17) \n";
echo "=============================================\n";

// 1. Setup Data
$orderId1 = 1999999001;
$orderId2 = 1999999002;

$json1 = json_encode([
    'order_items' => [
        ['item' => ['id' => 'TEST-SKU-A', 'title' => 'Produto Top A'], 'quantity' => 2, 'unit_price' => 50.00],
        ['item' => ['id' => 'TEST-SKU-B', 'title' => 'Produto Top B'], 'quantity' => 1, 'unit_price' => 100.00]
    ]
]);
// Total: 200

$json2 = json_encode([
    'order_items' => [
        ['item' => ['id' => 'TEST-SKU-A', 'title' => 'Produto Top A'], 'quantity' => 1, 'unit_price' => 50.00]
    ]
]);
// Total: 50

$db->exec("DELETE FROM ml_orders WHERE ml_order_id IN ($orderId1, $orderId2)");

$sql = "INSERT INTO ml_orders (ml_order_id, ml_account_id, user_id, status, total_amount, date_created, order_data, net_profit) 
        VALUES 
        ($orderId1, 1, 1, 'paid', 200.00, NOW(), '$json1', 50.00),
        ($orderId2, 1, 1, 'paid', 50.00, NOW(), '$json2', 10.00)";

$db->exec($sql);
echo "[1] Pedidos de teste inseridos (Total R$ 250.00)\n";

// 2. Generate Report
$service = new ReportService();
$today = date('Y-m-d');
$pdfPath = $service->generateSalesReport($today, $today);

echo "[2] PDF Gerado: $pdfPath\n";

if (strpos($pdfPath, 'relatorio_vendas') !== false) {
    echo "✅ Caminho do PDF válido.\n";
} else {
    echo "❌ Erro no caminho do PDF.\n";
}

// 3. Verify Data internally (using reflection or duplicated logic since method is private, actually let's trust PDF generation didn't crash)
// Alternatively, let's verify CSV export which is easier to read back.
$csvPath = $service->generateCsvExport($today, $today);
echo "[3] CSV Gerado: $csvPath\n";

// Read CSV content to check sum
$absPath = __DIR__ . '/../public' . $csvPath;
$handle = fopen($absPath, "r");
$header = fgetcsv($handle);
$row1 = fgetcsv($handle); // Order 1 or 2
$row2 = fgetcsv($handle); // Other order

$totalFound = 0;
if ($row1) $totalFound += (float)$row1[3];
if ($row2) $totalFound += (float)$row2[3];

if ($totalFound == 250.00) {
    echo "✅ CSV Contém soma correta (R$ 250.00)\n";
} else {
    echo "❌ Soma incorreta no CSV: $totalFound\n";
}
fclose($handle);

// Cleanup
$db->exec("DELETE FROM ml_orders WHERE ml_order_id IN ($orderId1, $orderId2)");
