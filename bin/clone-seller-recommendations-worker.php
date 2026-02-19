#!/usr/bin/env php
<?php
/**
 * Clone Seller Recommendations Worker
 * 
 * Analisa vendedores e gera recomendações para clonagem baseado em ML
 * 
 * Usage:
 *   php bin/clone-seller-recommendations-worker.php [options]
 * 
 * Options:
 *   --once              Run once and exit
 *   --account=ID        Process specific account only
 *   --category=ID       Analyze specific category only
 *   --refresh-all       Force refresh all recommendations
 *   --top-sellers=N     Analyze top N sellers per category (default: 50)
 *   --dry-run           Show what would be done
 *   --verbose           Verbose output
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Database;
use App\Services\CloneSellerRecommendationService;
use App\Services\MercadoLivreClient;

// CLI options
$options = getopt('', [
    'once',
    'account:',
    'category:',
    'refresh-all',
    'top-sellers:',
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo <<<HELP
Clone Seller Recommendations Worker

Analisa vendedores e gera recomendações para clonagem baseado em análise de performance.

Usage:
  php bin/clone-seller-recommendations-worker.php [options]

Options:
  --once              Executa uma vez e sai
  --account=ID        Processa apenas conta específica
  --category=ID       Analisa apenas categoria específica
  --refresh-all       Força atualização de todas as recomendações
  --top-sellers=N     Analisa top N vendedores por categoria (default: 50)
  --dry-run           Mostra o que seria feito sem executar
  --verbose           Saída detalhada
  --help              Mostra esta ajuda

Exemplos:
  php bin/clone-seller-recommendations-worker.php --once
  php bin/clone-seller-recommendations-worker.php --category=MLB1234 --verbose
  php bin/clone-seller-recommendations-worker.php --refresh-all --top-sellers=100

HELP;
    exit(0);
}

$runOnce = isset($options['once']);
$specificAccount = $options['account'] ?? null;
$specificCategory = $options['category'] ?? null;
$refreshAll = isset($options['refresh-all']);
$topSellers = (int) ($options['top-sellers'] ?? 50);
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Logging
function logMessage(string $message, string $level = 'INFO'): void
{
    global $verbose;
    
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] $message\n";
    
    if ($level === 'ERROR' || $verbose || $level === 'INFO') {
        echo $formatted;
    }
    
    $logFile = __DIR__ . '/../storage/logs/clone-seller-recommendations-' . date('Y-m-d') . '.log';
    file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
}

function logVerbose(string $message): void
{
    global $verbose;
    if ($verbose) {
        logMessage($message, 'DEBUG');
    }
}

// Popular categories for analysis
$POPULAR_CATEGORIES = [
    'MLB1648' => 'Informática',
    'MLB1051' => 'Celulares e Telefones',
    'MLB1000' => 'Eletrônicos',
    'MLB1574' => 'Casa e Decoração',
    'MLB1276' => 'Esportes e Fitness',
    'MLB1168' => 'Música',
    'MLB1132' => 'Brinquedos e Hobbies',
    'MLB1430' => 'Roupas e Acessórios',
    'MLB1367' => 'Antiguidades',
    'MLB1953' => 'Mais Categorias',
];

// Main worker loop
function runWorker(): void
{
    global $runOnce, $specificAccount, $specificCategory, $refreshAll, $topSellers, $dryRun, $POPULAR_CATEGORIES;
    
    logMessage("Clone Seller Recommendations Worker iniciado");
    logMessage("Top sellers por categoria: $topSellers");
    
    if ($dryRun) {
        logMessage("Modo DRY-RUN ativo", 'WARN');
    }
    
    if ($refreshAll) {
        logMessage("Modo REFRESH-ALL ativo - todas as recomendações serão atualizadas");
    }
    
    $iteration = 0;
    $sleepInterval = 21600; // 6 horas entre iterações
    
    do {
        $iteration++;
        logVerbose("Iteração #$iteration");
        
        try {
            $db = Database::getInstance();
            
            // Determinar contas para processar
            if ($specificAccount) {
                $accounts = [(int) $specificAccount];
            } else {
                // Buscar contas ativas
                $stmt = $db->query("
                    SELECT DISTINCT id FROM ml_accounts 
                    WHERE status = 'active' 
                    ORDER BY id
                    LIMIT 10
                ");
                $accounts = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }
            
            if (empty($accounts)) {
                logMessage("Nenhuma conta ativa encontrada");
            } else {
                logMessage("Processando " . count($accounts) . " conta(s)");
                
                foreach ($accounts as $accountId) {
                    processAccount((int) $accountId);
                }
            }
            
            // Calcular tendências globais
            if (!$dryRun) {
                calculateTrends($db);
            }
            
        } catch (\Exception $e) {
            logMessage("Erro no worker: " . $e->getMessage(), 'ERROR');
        }
        
        if (!$runOnce) {
            logVerbose("Aguardando $sleepInterval segundos...");
            sleep($sleepInterval);
        }
        
    } while (!$runOnce);
    
    logMessage("Worker finalizado");
}

function processAccount(int $accountId): void
{
    global $specificCategory, $topSellers, $dryRun, $refreshAll, $POPULAR_CATEGORIES;
    
    logMessage("Processando conta #$accountId");
    
    try {
        $db = Database::getInstance();
        $client = new MercadoLivreClient($accountId);
        
        // Determinar categorias para analisar
        $categories = [];
        
        if ($specificCategory) {
            $categories = [$specificCategory => 'Categoria Específica'];
        } else {
            // Usar categorias populares + categorias do histórico de clones
            $categories = $POPULAR_CATEGORIES;
            
            // Adicionar categorias do histórico
            $stmt = $db->prepare("
                SELECT DISTINCT JSON_EXTRACT(source_snapshot, '\$.category_id') as category_id
                FROM cloned_items
                WHERE target_account_id = :account_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                LIMIT 20
            ");
            $stmt->execute(['account_id' => $accountId]);
            $historyCategories = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            foreach ($historyCategories as $catId) {
                $catId = trim($catId, '"');
                if ($catId && !isset($categories[$catId])) {
                    $categories[$catId] = 'Histórico';
                }
            }
        }
        
        logMessage("Analisando " . count($categories) . " categorias");
        
        $totalSellers = 0;
        $totalRecommendations = 0;
        
        foreach ($categories as $categoryId => $categoryName) {
            $result = analyzeCategory($client, $db, $accountId, $categoryId, $categoryName);
            $totalSellers += $result['sellers'];
            $totalRecommendations += $result['recommendations'];
            
            // Rate limit between categories
            sleep(2);
        }
        
        logMessage("Conta #$accountId: $totalSellers vendedores analisados, $totalRecommendations recomendações geradas");
        
    } catch (\Exception $e) {
        logMessage("Erro ao processar conta #$accountId: " . $e->getMessage(), 'ERROR');
    }
}

function analyzeCategory(
    MercadoLivreClient $client,
    \PDO $db,
    int $accountId,
    string $categoryId,
    string $categoryName
): array {
    global $topSellers, $dryRun, $refreshAll;
    
    logVerbose("Analisando categoria $categoryId ($categoryName)");
    
    $sellersAnalyzed = 0;
    $recommendationsGenerated = 0;
    
    try {
        // Buscar top sellers da categoria
        $searchResult = $client->get("/sites/MLB/search", [
            'category' => $categoryId,
            'sort' => 'sold_quantity_desc',
            'limit' => 50,
            'offset' => 0
        ]);
        
        if (empty($searchResult['results'])) {
            logVerbose("Nenhum resultado para categoria $categoryId");
            return ['sellers' => 0, 'recommendations' => 0];
        }
        
        // Extrair sellers únicos
        $sellerIds = [];
        foreach ($searchResult['results'] as $item) {
            $sellerId = $item['seller']['id'] ?? null;
            if ($sellerId && !in_array($sellerId, $sellerIds)) {
                $sellerIds[] = $sellerId;
            }
        }
        
        $sellerIds = array_slice($sellerIds, 0, $topSellers);
        logVerbose("Encontrados " . count($sellerIds) . " vendedores únicos");
        
        foreach ($sellerIds as $sellerId) {
            // Verificar se já temos análise recente
            if (!$refreshAll) {
                $stmt = $db->prepare("
                    SELECT id FROM clone_seller_recommendations 
                    WHERE seller_id = :seller_id 
                    AND account_id = :account_id
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $stmt->execute(['seller_id' => $sellerId, 'account_id' => $accountId]);
                
                if ($stmt->fetch()) {
                    logVerbose("Seller $sellerId já tem análise recente, pulando");
                    continue;
                }
            }
            
            // Analisar seller
            $sellerData = analyzeSeller($client, $sellerId, $categoryId);
            $sellersAnalyzed++;
            
            if ($sellerData && $sellerData['score'] >= 50) {
                if (!$dryRun) {
                    saveRecommendation($db, $accountId, $sellerData, $categoryId, $categoryName);
                    $recommendationsGenerated++;
                } else {
                    logVerbose("[DRY-RUN] Salvaria recomendação para seller $sellerId (score: {$sellerData['score']})");
                    $recommendationsGenerated++;
                }
            }
            
            // Rate limit
            usleep(200000); // 200ms
        }
        
    } catch (\Exception $e) {
        logMessage("Erro ao analisar categoria $categoryId: " . $e->getMessage(), 'ERROR');
    }
    
    return ['sellers' => $sellersAnalyzed, 'recommendations' => $recommendationsGenerated];
}

function analyzeSeller(MercadoLivreClient $client, int $sellerId, string $categoryId): ?array
{
    try {
        // Buscar dados do vendedor
        $sellerData = $client->get("/users/$sellerId");
        
        if (!$sellerData || !isset($sellerData['id'])) {
            return null;
        }
        
        // Buscar reputação
        $reputation = $sellerData['seller_reputation'] ?? [];
        
        // Buscar itens do vendedor
        $itemsResult = $client->get("/users/$sellerId/items/search", [
            'status' => 'active',
            'limit' => 50
        ]);
        
        $totalItems = (int) ($itemsResult['paging']['total'] ?? 0);
        $itemIds = $itemsResult['results'] ?? [];
        
        // Calcular métricas
        $totalSales = 0;
        $totalVisits = 0;
        $topCategories = [];
        
        if (!empty($itemIds)) {
            $idsStr = implode(',', array_slice($itemIds, 0, 20));
            $itemsData = $client->get("/items?ids=$idsStr");
            
            foreach ($itemsData as $itemWrapper) {
                if (!isset($itemWrapper['body'])) continue;
                $item = $itemWrapper['body'];
                
                $totalSales += (int) ($item['sold_quantity'] ?? 0);
                
                $cat = $item['category_id'] ?? 'unknown';
                $topCategories[$cat] = ($topCategories[$cat] ?? 0) + 1;
            }
        }
        
        // Calcular score (0-100)
        $score = calculateSellerScore([
            'total_items' => $totalItems,
            'total_sales' => $totalSales,
            'reputation' => $reputation,
            'power_seller' => $sellerData['seller_reputation']['power_seller_status'] ?? null
        ]);
        
        // Top 3 categorias
        arsort($topCategories);
        $topCategoriesNames = array_slice(array_keys($topCategories), 0, 3);
        
        return [
            'seller_id' => $sellerId,
            'nickname' => $sellerData['nickname'] ?? null,
            'total_items' => $totalItems,
            'total_sales' => $totalSales,
            'score' => $score,
            'reputation' => $reputation['level_id'] ?? null,
            'reputation_level' => $reputation['power_seller_status'] ?? null,
            'power_seller' => !empty($sellerData['seller_reputation']['power_seller_status']),
            'mercado_lider' => ($sellerData['seller_reputation']['power_seller_status'] ?? '') === 'gold',
            'top_categories' => $topCategoriesNames,
            'conversion_rate' => $totalItems > 0 ? ($totalSales / $totalItems) * 100 : 0
        ];
        
    } catch (\Exception $e) {
        logVerbose("Erro ao analisar seller $sellerId: " . $e->getMessage());
        return null;
    }
}

function calculateSellerScore(array $data): int
{
    $score = 0;
    
    // Items (max 20 pontos)
    $items = $data['total_items'];
    if ($items >= 100) $score += 20;
    elseif ($items >= 50) $score += 15;
    elseif ($items >= 20) $score += 10;
    elseif ($items >= 5) $score += 5;
    
    // Vendas (max 30 pontos)
    $sales = $data['total_sales'];
    if ($sales >= 1000) $score += 30;
    elseif ($sales >= 500) $score += 25;
    elseif ($sales >= 100) $score += 20;
    elseif ($sales >= 50) $score += 15;
    elseif ($sales >= 10) $score += 10;
    
    // Reputação (max 30 pontos)
    $rep = $data['reputation'];
    if (is_array($rep)) {
        $positivePct = (float) ($rep['transactions']['ratings']['positive'] ?? 0);
        if ($positivePct >= 0.98) $score += 30;
        elseif ($positivePct >= 0.95) $score += 25;
        elseif ($positivePct >= 0.90) $score += 20;
        elseif ($positivePct >= 0.80) $score += 10;
    }
    
    // Power Seller (max 20 pontos)
    $powerStatus = $data['power_seller'];
    if ($powerStatus === 'gold') $score += 20;
    elseif ($powerStatus === 'platinum') $score += 20;
    elseif ($powerStatus === 'silver') $score += 10;
    
    return min(100, $score);
}

function saveRecommendation(\PDO $db, int $accountId, array $sellerData, string $categoryId, string $categoryName): void
{
    $stmt = $db->prepare("
        INSERT INTO clone_seller_recommendations (
            account_id, seller_id, nickname, category_id, category_name,
            score, total_items, total_sales, conversion_rate,
            reputation, reputation_level, power_seller, mercado_lider,
            top_categories, reason, created_at, updated_at
        ) VALUES (
            :account_id, :seller_id, :nickname, :category_id, :category_name,
            :score, :total_items, :total_sales, :conversion_rate,
            :reputation, :reputation_level, :power_seller, :mercado_lider,
            :top_categories, :reason, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            nickname = VALUES(nickname),
            score = VALUES(score),
            total_items = VALUES(total_items),
            total_sales = VALUES(total_sales),
            conversion_rate = VALUES(conversion_rate),
            reputation = VALUES(reputation),
            reputation_level = VALUES(reputation_level),
            power_seller = VALUES(power_seller),
            mercado_lider = VALUES(mercado_lider),
            top_categories = VALUES(top_categories),
            reason = VALUES(reason),
            updated_at = NOW()
    ");
    
    $reason = generateReason($sellerData);
    
    $stmt->execute([
        'account_id' => $accountId,
        'seller_id' => $sellerData['seller_id'],
        'nickname' => $sellerData['nickname'],
        'category_id' => $categoryId,
        'category_name' => $categoryName,
        'score' => $sellerData['score'],
        'total_items' => $sellerData['total_items'],
        'total_sales' => $sellerData['total_sales'],
        'conversion_rate' => $sellerData['conversion_rate'],
        'reputation' => $sellerData['reputation'],
        'reputation_level' => $sellerData['reputation_level'],
        'power_seller' => $sellerData['power_seller'] ? 1 : 0,
        'mercado_lider' => $sellerData['mercado_lider'] ? 1 : 0,
        'top_categories' => json_encode($sellerData['top_categories']),
        'reason' => $reason
    ]);
}

function generateReason(array $data): string
{
    $reasons = [];
    
    if ($data['score'] >= 90) {
        $reasons[] = 'Score excelente';
    } elseif ($data['score'] >= 80) {
        $reasons[] = 'Score alto';
    }
    
    if ($data['mercado_lider']) {
        $reasons[] = 'Mercado Líder';
    } elseif ($data['power_seller']) {
        $reasons[] = 'Power Seller';
    }
    
    if ($data['total_sales'] >= 500) {
        $reasons[] = 'Alto volume de vendas';
    }
    
    if ($data['conversion_rate'] >= 50) {
        $reasons[] = 'Alta taxa de conversão';
    }
    
    return implode(', ', $reasons) ?: 'Vendedor recomendado';
}

function calculateTrends(\PDO $db): void
{
    logVerbose("Calculando tendências de categorias");
    
    try {
        // Calcular crescimento por categoria (últimos 30 dias vs 30 dias anteriores)
        $db->exec("
            INSERT INTO clone_analytics_aggregates (
                account_id, metric_type, metric_name, metric_value, period_start, period_end, created_at
            )
            SELECT 
                0 as account_id,
                'trend' as metric_type,
                category_id as metric_name,
                COUNT(*) as metric_value,
                DATE_SUB(NOW(), INTERVAL 30 DAY) as period_start,
                NOW() as period_end,
                NOW() as created_at
            FROM clone_seller_recommendations
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY category_id
            ON DUPLICATE KEY UPDATE
                metric_value = VALUES(metric_value),
                created_at = NOW()
        ");
        
        logVerbose("Tendências atualizadas");
        
    } catch (\Exception $e) {
        logMessage("Erro ao calcular tendências: " . $e->getMessage(), 'ERROR');
    }
}

// Executar
runWorker();
