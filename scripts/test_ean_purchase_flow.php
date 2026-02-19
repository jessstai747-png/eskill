<?php
/**
 * Teste de fluxo completo de compra de EAN
 * 
 * Este script simula o fluxo completo:
 * 1. Visualizar pacotes
 * 2. Iniciar compra
 * 3. Confirmar pagamento (simular)
 * 4. Verificar saldo
 * 5. Usar EAN
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\EanService;
use App\Models\EanBalance;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "=== Teste de Fluxo Completo de Compra de EAN ===\n\n";

// Usar uma conta de teste
$testAccountId = 1; // Ajuste conforme sua base

try {
    $db = Database::getInstance();
    $eanService = new EanService();
    
    // Verificar se a conta existe
    $stmt = $db->prepare("SELECT id, nickname FROM ml_accounts WHERE id = :id");
    $stmt->execute(['id' => $testAccountId]);
    $account = $stmt->fetch();
    
    if (!$account) {
        // Criar conta de teste
        echo "Criando conta de teste...\n";
        $stmt = $db->prepare("INSERT INTO ml_accounts (nickname, email) VALUES ('Conta Teste EAN', 'teste@ean.com')");
        $stmt->execute();
        $testAccountId = (int) $db->lastInsertId();
        echo "Conta criada com ID: $testAccountId\n\n";
    } else {
        echo "Usando conta: {$account['nickname']} (ID: {$account['id']})\n\n";
    }
    
    // 1. Verificar pacotes disponíveis
    echo "1. PACOTES DISPONÍVEIS:\n";
    $packages = $eanService->getPackages();
    foreach ($packages as $pkg) {
        echo "   [{$pkg['id']}] {$pkg['name']}: {$pkg['quantity']} EANs por R$ " . number_format($pkg['price'], 2, ',', '.') . "\n";
    }
    echo "\n";
    
    // 2. Verificar inventário
    echo "2. INVENTÁRIO ATUAL:\n";
    $inventory = $eanService->checkInventory();
    echo "   Disponíveis: {$inventory['available']}\n";
    echo "   Reservados: {$inventory['reserved']}\n";
    echo "   Vendidos: {$inventory['sold']}\n\n";
    
    if ($inventory['available'] < 10) {
        echo "⚠️  Estoque baixo! Execute: php scripts/generate_eans.php 1000\n\n";
    }
    
    // 3. Verificar saldo antes da compra
    echo "3. SALDO ANTES DA COMPRA:\n";
    $balanceBefore = $eanService->getBalance($testAccountId);
    echo "   Total comprado: {$balanceBefore['total_purchased']}\n";
    echo "   Total usado: {$balanceBefore['total_used']}\n";
    echo "   Disponível: {$balanceBefore['available']}\n\n";
    
    // 4. Iniciar compra do pacote Starter (10 EANs)
    echo "4. INICIANDO COMPRA (Pacote Starter - 10 EANs):\n";
    $package = $packages[0]; // Starter
    
    try {
        $purchaseResult = $eanService->initiatePurchase($testAccountId, $package['id'], 'pix');
        echo "   ✅ Compra iniciada!\n";
        echo "   ID da compra: {$purchaseResult['purchase_id']}\n";
        echo "   Pacote: {$purchaseResult['package']['name']}\n";
        echo "   Valor: R$ " . number_format($purchaseResult['package']['price'], 2, ',', '.') . "\n";
        
        if (isset($purchaseResult['payment']['qr_code'])) {
            echo "   PIX: " . substr($purchaseResult['payment']['qr_code'], 0, 50) . "...\n";
        } else {
            echo "   Método: {$purchaseResult['payment']['method']}\n";
        }
        echo "\n";
        
        // 5. Simular confirmação de pagamento
        echo "5. CONFIRMANDO PAGAMENTO (simulação):\n";
        $confirmResult = $eanService->confirmPayment($purchaseResult['purchase_id']);
        echo "   ✅ {$confirmResult['message']}\n";
        echo "   Novo saldo: {$confirmResult['new_balance']} EANs\n\n";
        
    } catch (Exception $e) {
        echo "   ❌ Erro: " . $e->getMessage() . "\n\n";
    }
    
    // 6. Verificar saldo após compra
    echo "6. SALDO APÓS COMPRA:\n";
    $balanceAfter = $eanService->getBalance($testAccountId);
    echo "   Total comprado: {$balanceAfter['total_purchased']}\n";
    echo "   Total usado: {$balanceAfter['total_used']}\n";
    echo "   Disponível: {$balanceAfter['available']}\n\n";
    
    // 7. Listar EANs do seller
    echo "7. EANS ATRIBUÍDOS AO SELLER:\n";
    $eans = $eanService->getSellerEans($testAccountId, true);
    $showCount = min(5, count($eans));
    for ($i = 0; $i < $showCount; $i++) {
        echo "   - {$eans[$i]['ean']}" . ($eans[$i]['ml_item_id'] ? " (usado em {$eans[$i]['ml_item_id']})" : " (disponível)") . "\n";
    }
    if (count($eans) > 5) {
        echo "   ... e mais " . (count($eans) - 5) . " EANs\n";
    }
    echo "\n";
    
    // 8. Usar um EAN
    echo "8. USANDO UM EAN:\n";
    $mlItemId = 'MLB' . rand(1000000000, 9999999999);
    $useResult = $eanService->useEan($testAccountId, $mlItemId, 'Produto de Teste');
    
    if ($useResult) {
        echo "   ✅ EAN usado: {$useResult['ean']}\n";
        echo "   Vinculado ao item: $mlItemId\n\n";
    } else {
        echo "   ❌ Nenhum EAN disponível\n\n";
    }
    
    // 9. Saldo final
    echo "9. SALDO FINAL:\n";
    $balanceFinal = $eanService->getBalance($testAccountId);
    echo "   Total comprado: {$balanceFinal['total_purchased']}\n";
    echo "   Total usado: {$balanceFinal['total_used']}\n";
    echo "   Disponível: {$balanceFinal['available']}\n\n";
    
    // 10. Histórico de transações
    echo "10. ÚLTIMAS TRANSAÇÕES:\n";
    $transactions = $eanService->getTransactionHistory($testAccountId);
    $showTxCount = min(5, count($transactions));
    for ($i = 0; $i < $showTxCount; $i++) {
        $tx = $transactions[$i];
        $sign = $tx['type'] === 'credit' ? '+' : '-';
        echo "   [{$tx['created_at']}] {$sign}{$tx['quantity']} - {$tx['type']}\n";
    }
    echo "\n";
    
    echo "=== TESTE CONCLUÍDO COM SUCESSO ===\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
