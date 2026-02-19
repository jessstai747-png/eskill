<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

use App\Services\NegotiationService;
use App\Database;

$db = Database::getInstance();

echo "=============================================\n";
echo "   🤖 TESTE DEALMAKER (PHASE 18) \n";
echo "=============================================\n";

// 1. Setup Data
$itemId = 'TEST-ITEM-18';
$db->exec("DELETE FROM items WHERE ml_item_id = '$itemId'");
$db->exec("INSERT INTO items (ml_item_id, account_id, title, price, min_price, auto_negotiate) VALUES ('$itemId', 1, 'Produto Teste Negociação', 100.00, 90.00, 1)");

$service = new NegotiationService();

// 2. Test Cases
$tests = [
    ['input' => 'Faz por 95?', 'expected' => 'ACCEPT'],
    ['input' => 'Aceita 200,00', 'expected' => 'ACCEPT'],
    ['input' => 'Faz por 80?', 'expected' => 'REJECT'], // 80 < 90*0.95 (85.5) -> REJECT? Wait logic: 80 vs 90. 80 < 85.5 -> Reject.
    ['input' => 'Faz por 88?', 'expected' => 'COUNTER'], // 88 >= 85.5 but < 90 -> COUNTER
    ['input' => 'Tem a pronta entrega?', 'expected' => 'NULL'],
];

foreach ($tests as $t) {
    echo "Testando: '{$t['input']}'... ";
    $result = $service->processNegotiation($t['input'], $itemId);
    
    if ($t['expected'] === 'NULL') {
        if ($result === null) echo "✅ OK (Ignorado)\n";
        else echo "❌ FALHA (Esperado NULL, retornou impl)\n";
    } else {
        if ($result && $result['action'] === $t['expected']) {
            echo "✅ OK ({$result['action']})\n";
            // echo "   Resp: {$result['text']}\n";
        } else {
            $got = $result ? $result['action'] : 'NULL';
            echo "❌ FALHA (Esperado {$t['expected']}, obteve $got)\n";
        }
    }
}

// 3. Cleanup
$db->exec("DELETE FROM items WHERE ml_item_id = '$itemId'");
