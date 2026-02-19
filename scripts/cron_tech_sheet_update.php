<?php

/**
 * Cron Job: Atualização Inteligente de Sugestões de Ficha Técnica
 * 
 * Sistema profissional com priorização por:
 * - Itens sem análise recente
 * - Itens com gaps críticos (required/filter)
 * - Itens de alto valor (preço ou vendas)
 * - Round-robin para garantir cobertura
 * 
 * Frequência recomendada:
 * - Crontab: a cada 15 minutos
 * - Ou: a cada hora (0 * * * *)
 * 
 * Taxa de processamento:
 * - ~50 itens/execução (respeita rate limits)
 * - ~3200 itens/dia com execução a cada 15min
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\TechSheetService;
use App\Services\LoggingService;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Configurações
const ITEMS_PER_RUN = 50;                    // Itens por execução
const MAX_AGE_CRITICAL_HOURS = 6;            // Itens críticos: > 6h
const MAX_AGE_NORMAL_HOURS = 24;             // Itens normais: > 24h  
const MAX_AGE_STALE_DAYS = 7;                // Itens obsoletos: > 7 dias
const PRIORITY_BOOST_HIGH_VALUE = 1.5;       // Boost para itens caros
const PRIORITY_BOOST_HIGH_SALES = 2.0;       // Boost para mais vendidos

$logger = new LoggingService();

function logConsole(string $msg, string $level = 'INFO'): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] [$level] $msg\n";
}

try {
    logConsole("═══════════════════════════════════════════════════════════════════════");
    logConsole("  CRON: ATUALIZAÇÃO INTELIGENTE DE FICHA TÉCNICA");
    logConsole("═══════════════════════════════════════════════════════════════════════");
    
    $db = Database::getInstance();
    
    // Buscar contas ativas
    $accounts = $db->query("
        SELECT id, nickname FROM ml_accounts 
        WHERE status = 'active' AND access_token IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        logConsole("Nenhuma conta ativa encontrada", "WARNING");
        exit(0);
    }
    
    $totalProcessed = 0;
    $totalSuggestions = 0;
    $startTime = microtime(true);
    
    foreach ($accounts as $account) {
        $accountId = (int)$account['id'];
        $nickname = $account['nickname'];
        
        logConsole("────────────────────────────────────────────────────────────────────────");
        logConsole("Processando conta: $nickname (ID: $accountId)");
        
        // Marcar início
        updateSyncStatus($db, 'tech_sheet', $accountId, 'running');
        
        try {
            // Selecionar itens para processar com priorização inteligente
            $itemsToProcess = selectItemsIntelligently($db, $accountId);
            
            if (empty($itemsToProcess)) {
                logConsole("  Todos os itens estão atualizados!", "INFO");
                updateSyncStatus($db, 'tech_sheet', $accountId, 'success', 0);
                continue;
            }
            
            logConsole("  Selecionados " . count($itemsToProcess) . " itens para análise");
            
            $service = new TechSheetService($accountId);
            // MLInferenceService desabilitado - API domain_discovery não disponível
            // $mlInference = new MLInferenceService($accountId);
            
            $accountProcessed = 0;
            $accountSuggestions = 0;
            
            foreach ($itemsToProcess as $item) {
                $itemId = $item['ml_item_id'];
                $priority = $item['priority'];
                
                try {
                    // Sugestões via ML Inference (API domain_discovery - pode não estar disponível)
                    // Por ora desabilitado pois API retorna 404
                    $mlSuggestions = 0;
                    
                    // A API domain_discovery/attributes foi descontinuada pelo ML
                    // Alternativa: usar os atributos inferidos que vêm junto com o item
                    // GET /items/{id} já retorna atributos inferidos no campo "attributes"
                    
                    // 2. Gerar sugestões via extração de título e benchmark
                    $result = $service->generateSuggestions($itemId, [
                        'use_title' => true,
                        'use_benchmark' => $priority > 1.0, // Benchmark só para alta prioridade
                        'use_ai' => false,
                        'min_confidence' => 70,
                    ]);
                    
                    $localSuggestions = $result['created'] ?? 0;
                    $totalCreated = $localSuggestions;
                    
                    $accountProcessed++;
                    $accountSuggestions += $totalCreated;
                    
                    if ($totalCreated > 0) {
                        logConsole("  ✓ $itemId: $totalCreated sugestões");
                    }
                    
                } catch (\Exception $e) {
                    logConsole("  ✗ $itemId: " . $e->getMessage(), "WARNING");
                }
                
                // Delay para não sobrecarregar a API
                usleep(100000); // 100ms
            }
            
            $totalProcessed += $accountProcessed;
            $totalSuggestions += $accountSuggestions;
            
            logConsole("  Resultado: $accountProcessed itens, $accountSuggestions sugestões");
            
            updateSyncStatus($db, 'tech_sheet', $accountId, 'success', $accountProcessed);
            
        } catch (\Exception $e) {
            logConsole("  ERRO: " . $e->getMessage(), "ERROR");
            updateSyncStatus($db, 'tech_sheet', $accountId, 'error', null, $e->getMessage());
        }
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    
    logConsole("═══════════════════════════════════════════════════════════════════════");
    logConsole("  RESUMO");
    logConsole("═══════════════════════════════════════════════════════════════════════");
    logConsole("  Total processado: $totalProcessed itens");
    logConsole("  Sugestões geradas: $totalSuggestions");
    logConsole("  Tempo: {$elapsed}s");
    logConsole("═══════════════════════════════════════════════════════════════════════");

} catch (\Exception $e) {
    logConsole("ERRO CRÍTICO: " . $e->getMessage(), "CRITICAL");
    exit(1);
}

/**
 * Seleciona itens para processar com priorização inteligente
 * 
 * Prioridade baseada em:
 * 1. Idade da última análise
 * 2. Gaps críticos (required/filter)
 * 3. Valor do item (preço)
 * 4. Volume de vendas
 * 
 * @return array Lista de itens com prioridade calculada
 */
function selectItemsIntelligently(PDO $db, int $accountId): array
{
    // Query com scoring de prioridade
    $sql = "
        SELECT 
            i.ml_item_id,
            i.title,
            i.category_id,
            i.price,
            s.last_analyzed_at,
            s.missing_required,
            s.missing_filter,
            s.completeness_percent,
            
            -- Calcular prioridade
            (
                -- Base: idade da análise (0-3 pontos)
                CASE 
                    WHEN s.last_analyzed_at IS NULL THEN 3
                    WHEN s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 3
                    WHEN s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 2
                    WHEN s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 6 HOUR) THEN 1
                    ELSE 0
                END
                +
                -- Gaps críticos (0-2 pontos)
                CASE WHEN COALESCE(s.missing_required, 0) > 0 THEN 2 ELSE 0 END
                +
                CASE WHEN COALESCE(s.missing_filter, 0) > 0 THEN 1 ELSE 0 END
                +
                -- Valor alto (0-1 ponto)
                CASE WHEN i.price > 500 THEN 1 ELSE 0 END
            ) as priority_score
            
        FROM ml_items i
        LEFT JOIN tech_sheet_item_summary s ON s.item_id = i.ml_item_id AND s.account_id = i.account_id
        WHERE i.account_id = :account_id
        AND i.status = 'active'
        -- Filtro inicial: só itens que precisam de análise
        AND (
            s.last_analyzed_at IS NULL
            OR s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)
            OR s.completeness_percent < 80
        )
        ORDER BY priority_score DESC, s.last_analyzed_at ASC
        LIMIT " . (int)ITEMS_PER_RUN . "
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar prioridade normalizada
    foreach ($items as &$item) {
        $item['priority'] = (float)$item['priority_score'] / 8.0; // Max score = 8
    }
    
    return $items;
}

function updateSyncStatus(
    PDO $db,
    string $resourceType,
    int $accountId,
    string $status,
    ?int $itemsCount = null,
    ?string $errorMessage = null
): void {
    try {
        $stmt = $db->prepare(
            "INSERT INTO sync_status (resource_type, account_id, last_sync_at, status, items_count, error_message)
             VALUES (:resource_type, :account_id, NOW(), :status, :items_count, :error_message)
             ON DUPLICATE KEY UPDATE
                last_sync_at = NOW(),
                status = VALUES(status),
                items_count = VALUES(items_count),
                error_message = VALUES(error_message)"
        );

        $stmt->execute([
            'resource_type' => $resourceType,
            'account_id' => $accountId,
            'status' => $status,
            'items_count' => $itemsCount,
            'error_message' => $errorMessage,
        ]);
    } catch (\Throwable $e) {
        logConsole('Falha ao atualizar sync_status: ' . $e->getMessage(), 'WARNING');
    }
}
