#!/usr/bin/env php
<?php
/**
 * Smoke Test CLI - Módulo Ficha Técnica (SEO)
 * 
 * Testa o fluxo completo: gerar sugestões → visualizar → (opcional) aplicar no ML
 * 
 * Uso:
 *   php scripts/smoke_tech_sheet.php --account=1 --item=MLB123456789
 *   php scripts/smoke_tech_sheet.php --account=1 --item=MLB123456789 --use-benchmark=1 --min-confidence=80
 *   php scripts/smoke_tech_sheet.php --account=1 --item=MLB123456789 --apply --user-id=1
 * 
 * Opções:
 *   --account=ID        ID da conta ML (obrigatório)
 *   --item=MLB...       ID do item no Mercado Livre (obrigatório)
 *   --use-benchmark=0|1 Habilitar benchmark de concorrentes (default: 0)
 *   --use-ai=0|1        Habilitar sugestões via IA (default: 0)
 *   --min-confidence=N  Confiança mínima para sugestões (default: 60)
 *   --apply             Se presente, aplica sugestões aprovadas no ML
 *   --user-id=ID        ID do usuário para auditoria (default: null = sistema)
 *   --auto-approve      Auto-aprova sugestões elegíveis antes de aplicar
 *   --verbose           Modo verboso com detalhes extras
 *   --help              Exibe esta ajuda
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\TechSheetService;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================

function printHelp(): void
{
    echo <<<HELP
╔══════════════════════════════════════════════════════════════════════════════╗
║                    SMOKE TEST - FICHA TÉCNICA (SEO)                          ║
╚══════════════════════════════════════════════════════════════════════════════╝

USO:
  php scripts/smoke_tech_sheet.php --account=ID --item=MLB... [opções]

OPÇÕES OBRIGATÓRIAS:
  --account=ID        ID da conta ML no banco de dados
  --item=MLB...       ID do item no Mercado Livre

OPÇÕES DE GERAÇÃO:
  --use-benchmark=0|1 Habilitar benchmark de concorrentes (default: 0)
  --use-ai=0|1        Habilitar sugestões via IA (default: 0)
  --use-title=0|1     Habilitar extração do título (default: 1)
  --min-confidence=N  Confiança mínima para sugestões (default: 60)

OPÇÕES DE APLICAÇÃO:
  --apply             Aplica sugestões APROVADAS no Mercado Livre
  --auto-approve      Auto-aprova sugestões elegíveis antes de aplicar
  --user-id=ID        ID do usuário para auditoria (default: null = sistema)

OUTRAS:
  --verbose           Modo verboso com detalhes extras
  --help              Exibe esta ajuda

EXEMPLOS:
  # Apenas gerar sugestões
  php scripts/smoke_tech_sheet.php --account=1 --item=MLB123456789

  # Gerar com benchmark e alta confiança
  php scripts/smoke_tech_sheet.php --account=1 --item=MLB123456789 --use-benchmark=1 --min-confidence=80

  # Gerar, auto-aprovar e aplicar no ML
  php scripts/smoke_tech_sheet.php --account=1 --item=MLB123456789 --auto-approve --apply

HELP;
}

function printLine(string $char = '─', int $length = 80): void
{
    echo str_repeat($char, $length) . "\n";
}

function printHeader(string $title): void
{
    echo "\n";
    printLine('═');
    echo "  $title\n";
    printLine('═');
}

function printSection(string $title): void
{
    echo "\n";
    printLine('─');
    echo "► $title\n";
    printLine('─');
}

function printSuccess(string $msg): void
{
    echo "  ✅ $msg\n";
}

function printError(string $msg): void
{
    echo "  ❌ $msg\n";
}

function printWarning(string $msg): void
{
    echo "  ⚠️  $msg\n";
}

function printInfo(string $msg): void
{
    echo "  ℹ️  $msg\n";
}

function printKeyValue(string $key, $value, int $keyWidth = 20): void
{
    $formattedKey = str_pad($key, $keyWidth);
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    echo "  $formattedKey: $value\n";
}

// ============================================================================
// PARSE ARGUMENTOS
// ============================================================================

$options = getopt('', [
    'account:',
    'item:',
    'use-benchmark::',
    'use-ai::',
    'use-title::',
    'min-confidence::',
    'apply',
    'auto-approve',
    'user-id::',
    'verbose',
    'help',
]);

if (isset($options['help'])) {
    printHelp();
    exit(0);
}

$accountId = isset($options['account']) ? (int)$options['account'] : null;
$itemId = $options['item'] ?? null;

// Flags booleanas: isset = true, ou valor explícito 1/0
$useBenchmark = isset($options['use-benchmark']) && ($options['use-benchmark'] === false || (int)$options['use-benchmark'] === 1);
$useAi = isset($options['use-ai']) && ($options['use-ai'] === false || (int)$options['use-ai'] === 1);
$useTitle = !isset($options['use-title']) || (int)$options['use-title'] !== 0; // default true
$minConfidence = (int)($options['min-confidence'] ?? 60);
$shouldApply = isset($options['apply']);
$autoApprove = isset($options['auto-approve']);
$userId = isset($options['user-id']) ? (int)$options['user-id'] : null;
$verbose = isset($options['verbose']);

// Validar argumentos obrigatórios
if (!$accountId || !$itemId) {
    printError("Argumentos obrigatórios: --account=ID --item=MLB...");
    echo "\nUse --help para ver todas as opções.\n";
    exit(1);
}

// ============================================================================
// INÍCIO DO SMOKE TEST
// ============================================================================

printHeader("SMOKE TEST - FICHA TÉCNICA");

echo "\n";
printKeyValue("Account ID", $accountId);
printKeyValue("Item ID", $itemId);
printKeyValue("Use Title", $useTitle ? 'Sim' : 'Não');
printKeyValue("Use Benchmark", $useBenchmark ? 'Sim' : 'Não');
printKeyValue("Use AI", $useAi ? 'Sim' : 'Não');
printKeyValue("Min Confidence", $minConfidence);
printKeyValue("Auto Approve", $autoApprove ? 'Sim' : 'Não');
printKeyValue("Apply to ML", $shouldApply ? 'SIM ⚡' : 'Não');
printKeyValue("User ID", $userId ?? '(sistema)');

// ============================================================================
// PASSO 1: VALIDAR CONTA E ITEM
// ============================================================================

printSection("1. VALIDAÇÃO");

try {
    $db = Database::getInstance();
    
    // Verificar conta
    $stmt = $db->prepare("SELECT id, nickname, ml_user_id, status FROM ml_accounts WHERE id = :id");
    $stmt->execute([':id' => $accountId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        printError("Conta ID $accountId não encontrada no banco");
        exit(1);
    }
    
    if ($account['status'] !== 'active') {
        printWarning("Conta não está ativa (status: {$account['status']})");
    }
    
    printSuccess("Conta encontrada: {$account['nickname']} (ML: {$account['ml_user_id']})");
    
    // Verificar item no cache local
    $stmt = $db->prepare("
        SELECT ml_item_id, title, status, category_id 
        FROM ml_items 
        WHERE ml_item_id = :item_id AND account_id = :account_id
    ");
    $stmt->execute([':item_id' => $itemId, ':account_id' => $accountId]);
    $localItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$localItem) {
        printError("Item $itemId não encontrado no cache local (ml_items)");
        printInfo("Execute a sincronização primeiro: php scripts/cron_sync_items.php");
        exit(1);
    }
    
    printSuccess("Item encontrado: " . substr($localItem['title'], 0, 50) . "...");
    printKeyValue("Status", $localItem['status']);
    printKeyValue("Categoria", $localItem['category_id']);
    
} catch (Exception $e) {
    printError("Erro de banco: " . $e->getMessage());
    exit(1);
}

// ============================================================================
// PASSO 2: INSTANCIAR SERVICE E OBTER ESTADO ATUAL
// ============================================================================

printSection("2. ESTADO ATUAL DO ITEM");

try {
    $service = new TechSheetService($accountId);
    
    // Obter visão atual
    $currentState = $service->getItem($itemId);
    
    if (!($currentState['success'] ?? false)) {
        printError("Falha ao obter item: " . ($currentState['error'] ?? 'Erro desconhecido'));
        exit(1);
    }
    
    $summary = $currentState['summary'] ?? [];
    $gaps = $currentState['gaps'] ?? [];
    $suggestions = $currentState['suggestions'] ?? [];
    
    echo "\n  📊 SUMMARY:\n";
    printKeyValue("Completeness", ($summary['completeness_percent'] ?? 0) . '%', 22);
    printKeyValue("Total Atributos", $summary['total_attributes'] ?? 0, 22);
    printKeyValue("Preenchidos", $summary['filled_count'] ?? 0, 22);
    printKeyValue("Gaps", $summary['missing_count'] ?? 0, 22);
    
    // Gaps por tipo
    $gapsByType = [
        'required' => count($gaps['gaps']['required'] ?? []),
        'filter' => count($gaps['gaps']['filter'] ?? []),
        'hidden' => count($gaps['gaps']['hidden'] ?? []),
        'recommended' => count($gaps['gaps']['recommended'] ?? []),
    ];
    
    echo "\n  📋 GAPS POR TIPO:\n";
    foreach ($gapsByType as $type => $count) {
        printKeyValue(ucfirst($type), $count, 22);
    }
    
    // Sugestões existentes
    $suggestionsByStatus = [];
    $suggestionsBySource = [];
    foreach ($suggestions as $sug) {
        $status = $sug['status'] ?? 'unknown';
        $source = $sug['source'] ?? 'unknown';
        $suggestionsByStatus[$status] = ($suggestionsByStatus[$status] ?? 0) + 1;
        $suggestionsBySource[$source] = ($suggestionsBySource[$source] ?? 0) + 1;
    }
    
    echo "\n  💡 SUGESTÕES EXISTENTES:\n";
    printKeyValue("Total", count($suggestions), 22);
    
    if (!empty($suggestionsByStatus)) {
        echo "    Por status:\n";
        foreach ($suggestionsByStatus as $status => $count) {
            echo "      - $status: $count\n";
        }
    }
    
    if (!empty($suggestionsBySource) && $verbose) {
        echo "    Por source:\n";
        foreach ($suggestionsBySource as $source => $count) {
            echo "      - $source: $count\n";
        }
    }
    
} catch (Exception $e) {
    printError("Erro ao obter estado: " . $e->getMessage());
    exit(1);
}

// ============================================================================
// PASSO 3: GERAR SUGESTÕES
// ============================================================================

printSection("3. GERAR SUGESTÕES");

try {
    $genOptions = [
        'use_title' => $useTitle,
        'use_benchmark' => $useBenchmark,
        'use_ai' => $useAi,
        'min_confidence' => $minConfidence,
    ];
    
    printInfo("Executando generateSuggestions com opções:");
    foreach ($genOptions as $key => $val) {
        echo "    - $key: " . ($val === true ? 'true' : ($val === false ? 'false' : $val)) . "\n";
    }
    
    $genResult = $service->generateSuggestions($itemId, $genOptions);
    
    if (!($genResult['success'] ?? false)) {
        printError("Falha ao gerar sugestões: " . ($genResult['error'] ?? 'Erro desconhecido'));
        if ($verbose) {
            echo "    Debug: " . json_encode($genResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        exit(1);
    }
    
    printSuccess("Sugestões geradas com sucesso!");
    printKeyValue("Criadas", $genResult['created'] ?? 0, 22);
    printKeyValue("Do título", $genResult['title_extracted'] ?? 0, 22);
    printKeyValue("Do benchmark", $genResult['benchmark_created'] ?? 0, 22);
    
    if ($verbose && isset($genResult['plan'])) {
        echo "\n  📝 PLANO DETALHADO:\n";
        $plan = $genResult['plan'];
        printKeyValue("Gaps identificados", count($plan['gaps'] ?? []), 22);
        printKeyValue("Preenchimentos", count($plan['filled'] ?? []), 22);
    }
    
} catch (Exception $e) {
    printError("Exceção ao gerar: " . $e->getMessage());
    if ($verbose) {
        echo "    Trace: " . $e->getTraceAsString() . "\n";
    }
    exit(1);
}

// ============================================================================
// PASSO 4: RECONSULTAR ESTADO
// ============================================================================

printSection("4. ESTADO APÓS GERAÇÃO");

try {
    $newState = $service->getItem($itemId);
    
    if (!($newState['success'] ?? false)) {
        printWarning("Não foi possível reconsultar estado");
    } else {
        $newSuggestions = $newState['suggestions'] ?? [];
        
        // Contar por status e source
        $newByStatus = [];
        $newBySource = [];
        foreach ($newSuggestions as $sug) {
            $status = $sug['status'] ?? 'unknown';
            $source = $sug['source'] ?? 'unknown';
            $newByStatus[$status] = ($newByStatus[$status] ?? 0) + 1;
            $newBySource[$source] = ($newBySource[$source] ?? 0) + 1;
        }
        
        echo "\n  💡 SUGESTÕES ATUAIS:\n";
        printKeyValue("Total", count($newSuggestions), 22);
        
        echo "    Por status:\n";
        foreach ($newByStatus as $status => $count) {
            $icon = match($status) {
                'pending' => '⏳',
                'approved' => '✅',
                'rejected' => '❌',
                'applied' => '🚀',
                default => '❓',
            };
            echo "      $icon $status: $count\n";
        }
        
        echo "    Por source:\n";
        foreach ($newBySource as $source => $count) {
            echo "      - $source: $count\n";
        }
        
        // Listar detalhes se verbose
        if ($verbose && count($newSuggestions) > 0) {
            echo "\n  📝 DETALHES DAS SUGESTÕES:\n";
            foreach ($newSuggestions as $i => $sug) {
                echo "    [$i] {$sug['attribute_name']}: {$sug['suggested_value']}\n";
                echo "        source={$sug['source']}, confidence={$sug['confidence']}, status={$sug['status']}\n";
            }
        }
    }
    
} catch (Exception $e) {
    printWarning("Erro ao reconsultar: " . $e->getMessage());
}

// ============================================================================
// PASSO 5: AUTO-APROVAR (OPCIONAL)
// ============================================================================

if ($autoApprove) {
    printSection("5. AUTO-APROVAR SUGESTÕES");
    
    try {
        // Aprovar sugestões pendentes com alta confiança
        $approveResult = $service->autoApprove($itemId, $minConfidence);
        
        if ($approveResult['success'] ?? false) {
            printSuccess("Auto-aprovação concluída");
            printKeyValue("Aprovadas", $approveResult['approved'] ?? 0, 22);
        } else {
            printWarning("Auto-aprovação sem resultados: " . ($approveResult['message'] ?? ''));
        }
        
    } catch (Exception $e) {
        printWarning("Erro ao auto-aprovar: " . $e->getMessage());
    }
}

// ============================================================================
// PASSO 6: APLICAR NO ML (OPCIONAL)
// ============================================================================

if ($shouldApply) {
    printSection("6. APLICAR NO MERCADO LIVRE");
    
    printWarning("⚡ ATENÇÃO: Esta operação irá MODIFICAR o anúncio no Mercado Livre!");
    echo "    Aplicando sugestões APROVADAS para o item $itemId...\n";
    
    try {
        $applyResult = $service->applyApproved($itemId, $userId);
        
        if ($applyResult['success'] ?? false) {
            $appliedCount = $applyResult['applied'] ?? 0;
            
            if ($appliedCount > 0) {
                printSuccess("Sugestões aplicadas com sucesso no Mercado Livre!");
                printKeyValue("Aplicadas", $appliedCount, 22);
                
                if ($verbose && isset($applyResult['ml_response'])) {
                    echo "\n  📡 RESPOSTA DO ML:\n";
                    echo "    " . json_encode($applyResult['ml_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                }
            } else {
                printWarning($applyResult['message'] ?? 'Nenhuma sugestão aprovada para aplicar');
            }
        } else {
            printError("Falha ao aplicar: " . ($applyResult['error'] ?? 'Erro desconhecido'));
            if (isset($applyResult['ml'])) {
                echo "    ML Error: " . json_encode($applyResult['ml'], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        
    } catch (Exception $e) {
        printError("Exceção ao aplicar: " . $e->getMessage());
        if ($verbose) {
            echo "    Trace: " . $e->getTraceAsString() . "\n";
        }
        exit(1);
    }
} else {
    printSection("6. APLICAR NO MERCADO LIVRE");
    printInfo("Aplicação não solicitada (use --apply para aplicar sugestões aprovadas)");
}

// ============================================================================
// RESUMO FINAL
// ============================================================================

printHeader("RESUMO DO SMOKE TEST");

echo "\n";
printKeyValue("Item", $itemId);
printKeyValue("Conta", $account['nickname']);
printKeyValue("Sugestões criadas", $genResult['created'] ?? 0);
printKeyValue("Aplicação", $shouldApply ? 'Executada' : 'Não solicitada');

echo "\n";
printSuccess("Smoke test concluído!");
echo "\n";

exit(0);
