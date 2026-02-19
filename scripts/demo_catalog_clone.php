<?php
/**
 * Demonstração prática do Sistema de Clonagem de Catálogo
 * Este script simula um uso real do sistema
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\CatalogCloneService;
use App\Services\JobService;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "=== Demo: Sistema de Clonagem de Catálogo ===\n\n";

try {
    $db = Database::getInstance();
    $cloneService = new CatalogCloneService();
    $jobService = new JobService();
    
    // Obter contas disponíveis
    $stmt = $db->query("SELECT id, nickname, ml_user_id FROM ml_accounts WHERE status = 'active' ORDER BY id LIMIT 2");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($accounts) < 2) {
        echo "❌ Necessário pelo menos 2 contas ativas para a demonstração.\n";
        exit(1);
    }
    
    $sourceAccount = $accounts[0];
    $targetAccount = $accounts[1];
    
    echo "🎯 Cenário: Clonar anúncios de catálogo\n";
    echo "   Origem: {$sourceAccount['nickname']} (ID: {$sourceAccount['id']})\n";
    echo "   Destino: {$targetAccount['nickname']} (ID: {$targetAccount['id']})\n\n";
    
    // Simular clonagem individual com diferentes estratégias
    echo "📋 Simulações de Clonagem Individual:\n\n";
    
    $scenarios = [
        [
            'name' => 'Estratégia Conservadora (Cópia de Preço)',
            'params' => [
                'source_account_id' => $sourceAccount['id'],
                'source_item_id' => 'MLB3687442391', // ID fictício para teste
                'target_account_id' => $targetAccount['id'],
                'pricing_strategy' => ['type' => 'copy'],
                'stock_strategy' => ['type' => 'copy']
            ]
        ],
        [
            'name' => 'Estratégia Agressiva (Preço Competitivo)',
            'params' => [
                'source_account_id' => $sourceAccount['id'],
                'source_item_id' => 'MLB3687442392',
                'target_account_id' => $targetAccount['id'],
                'pricing_strategy' => ['type' => 'aggressive'],
                'stock_strategy' => ['type' => 'fixed', 'value' => 10]
            ]
        ],
        [
            'name' => 'Estratégia Premium (+20% markup)',
            'params' => [
                'source_account_id' => $sourceAccount['id'],
                'source_item_id' => 'MLB3687442393',
                'target_account_id' => $targetAccount['id'],
                'pricing_strategy' => ['type' => 'markup_percent', 'value' => 20],
                'stock_strategy' => ['type' => 'fixed', 'value' => 5]
            ]
        ]
    ];
    
    foreach ($scenarios as $i => $scenario) {
        echo ($i + 1) . ". {$scenario['name']}\n";
        echo "   Item: {$scenario['params']['source_item_id']}\n";
        echo "   Estratégia: {$scenario['params']['pricing_strategy']['type']}\n";
        
        // Executar clonagem (que falhará por item não existir, mas testará a lógica)
        $result = $cloneService->cloneCatalogItem($scenario['params']);
        
        if ($result['status'] === 'error') {
            $isExpectedError = strpos($result['message'], 'buscar item origem') !== false;
            echo "   Resultado: " . ($isExpectedError ? "✅ Validação funcionando" : "❌ Erro inesperado") . "\n";
            echo "   Detalhes: " . $result['message'] . "\n";
        } else {
            echo "   Resultado: ✅ {$result['message']}\n";
        }
        echo "\n";
    }
    
    // Demonstração de clonagem em lote
    echo "📦 Simulação de Clonagem em Lote:\n\n";
    
    $batchItems = [
        'MLB3687442394',
        'MLB3687442395',
        'MLB3687442396'
    ];
    
    $jobsCreated = 0;
    foreach ($batchItems as $itemId) {
        $payload = [
            'source_account_id' => $sourceAccount['id'],
            'source_item_id' => $itemId,
            'target_account_id' => $targetAccount['id'],
            'pricing_strategy' => ['type' => 'competitive'],
            'stock_strategy' => ['type' => 'copy']
        ];
        
        $jobId = $jobService->dispatch('catalog_clone_item', $payload);
        $jobsCreated++;
        echo "   ✅ Job #{$jobId} criado para item $itemId\n";
    }
    
    echo "\n📊 Lote processado: $jobsCreated jobs criados\n";
    echo "   Os jobs serão processados pelo worker em segundo plano\n";
    echo "   Execute: php scripts/process_jobs.php\n\n";
    
    // Status da fila
    echo "📈 Status Atual da Fila:\n";
    $stats = $jobService->getStats();
    foreach ($stats as $status => $count) {
        $icon = match($status) {
            'pending' => '⏳',
            'processing' => '🔄', 
            'completed' => '✅',
            'failed' => '❌'
        };
        echo "   $icon $status: $count\n";
    }
    
    echo "\n🎉 Demonstração concluída!\n\n";
    
    echo "🔧 Para uso real:\n";
    echo "1. Acesse /dashboard/catalog/clone\n";
    echo "2. Selecione contas origem e destino\n";
    echo "3. Informe IDs de anúncios reais do Mercado Livre\n";
    echo "4. Escolha a estratégia de preço\n";
    echo "5. Execute a clonagem\n\n";
    
    echo "⚙️  Configuração do Worker (Produção):\n";
    echo "Adicione ao crontab:\n";
    echo "* * * * * cd " . dirname(__DIR__) . " && php scripts/process_jobs.php >> storage/logs/jobs.log 2>&1\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}