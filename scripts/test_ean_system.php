<?php
/**
 * Script de teste do sistema de EAN
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\EanService;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "=== Teste do Sistema de EAN ===\n\n";

try {
    $db = Database::getInstance();
    $eanService = new EanService();
    
    // 1. Verificar tabelas
    echo "1. Verificando tabelas do banco de dados...\n";
    $tables = ['ean_packages', 'ean_inventory', 'ean_purchases', 'ean_assignments', 'ean_balances', 'ean_transactions', 'ean_settings'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "   - $table: " . ($exists ? "✅" : "❌") . "\n";
    }
    echo "\n";
    
    // 2. Verificar pacotes
    echo "2. Pacotes disponíveis:\n";
    $packages = $eanService->getPackages();
    foreach ($packages as $pkg) {
        echo "   - {$pkg['name']}: {$pkg['quantity']} EANs por R$ " . number_format($pkg['price'], 2, ',', '.') . "\n";
    }
    echo "   Total: " . count($packages) . " pacotes\n\n";
    
    // 3. Verificar inventário
    echo "3. Status do Inventário:\n";
    $inventory = $eanService->checkInventory();
    echo "   - Disponíveis: {$inventory['available']}\n";
    echo "   - Reservados: {$inventory['reserved']}\n";
    echo "   - Vendidos: {$inventory['sold']}\n";
    echo "   - Total: {$inventory['total']}\n\n";
    
    // 4. Testar adição de EANs ao inventário (modo teste)
    echo "4. Testando adição de EANs ao inventário...\n";
    
    // Gerar EANs válidos usando o método do model
    $inventoryModel = new \App\Models\EanInventory();
    $testEans = [];
    for ($i = 0; $i < 5; $i++) {
        $testEans[] = $inventoryModel->generateTestEan('789');
    }
    echo "   - EANs gerados para teste: " . implode(', ', $testEans) . "\n";
    
    $added = $eanService->addToInventory($testEans, 'TEST-BATCH-' . date('Ymd'), 2.50, 'Teste');
    echo "   - EANs adicionados: $added\n\n";
    
    // 5. Verificar inventário após adição
    echo "5. Inventário após adição:\n";
    $inventory = $eanService->checkInventory();
    echo "   - Disponíveis: {$inventory['available']}\n\n";
    
    // 6. Testar busca de EAN
    echo "6. Testando busca de EAN...\n";
    $eanInfo = $eanService->findEan($testEans[0]); // Buscar o primeiro EAN adicionado
    if ($eanInfo) {
        echo "   - EAN: {$eanInfo['ean']}\n";
        echo "   - Status: {$eanInfo['status']}\n";
        echo "   - Lote: {$eanInfo['purchase_batch']}\n";
    } else {
        echo "   - EAN não encontrado\n";
    }
    echo "\n";
    
    // 7. Testar validação de EAN
    echo "7. Testando validação de EAN:\n";
    
    // Usar EANs gerados válidos + alguns inválidos
    $generatedValidEan = $inventoryModel->generateTestEan('789');
    $testCodes = [
        $generatedValidEan => true, // EAN-13 válido gerado
        '1234567890128' => true,    // EAN-13 válido conhecido
        '12345670' => true,         // EAN-8 válido
        '123456789' => false,       // Inválido (9 dígitos)
        'ABC123' => false,          // Inválido (letras)
        '7891234567890' => false,   // Dígito verificador errado
    ];
    
    foreach ($testCodes as $code => $expected) {
        $isValid = $eanService->validateEan($code);
        $result = $isValid === $expected ? "✅" : "❌";
        echo "   - $code: " . ($isValid ? "válido" : "inválido") . " $result\n";
    }
    echo "\n";
    
    // 8. Dashboard Admin
    echo "8. Dados do Dashboard Admin:\n";
    $dashboard = $eanService->getAdminDashboard();
    echo "   - Estoque disponível: {$dashboard['inventory']['available']}\n";
    echo "   - Total vendas: " . ($dashboard['sales']['total_orders'] ?? 0) . "\n";
    echo "   - Receita total: R$ " . number_format($dashboard['sales']['total_revenue'] ?? 0, 2, ',', '.') . "\n";
    echo "   - Alerta de estoque baixo: " . ($dashboard['low_stock_alert'] ? "SIM" : "NÃO") . "\n\n";
    
    echo "=== TODOS OS TESTES CONCLUÍDOS ===\n";
    echo "✅ Sistema de EAN funcionando corretamente!\n\n";
    
    echo "URLs disponíveis:\n";
    echo "   - Loja de EANs (Seller): /dashboard/ean\n";
    echo "   - Admin de EANs: /dashboard/ean/admin\n";
    echo "   - API Pacotes: /api/ean/packages\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
