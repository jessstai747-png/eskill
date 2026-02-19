#!/usr/bin/env php
<?php
/**
 * 🎯 Cron: Smart Fill para Ficha Técnica
 * 
 * Executa o preenchimento inteligente de lacunas usando múltiplas fontes SEO:
 * - Título do produto
 * - Descrição do produto  
 * - Benchmark de concorrentes
 * - Autocomplete ML
 * - Tendências ML
 * 
 * Uso via cron:
 *   # Executar diariamente às 3h
 *   0 3 * * * php /path/to/scripts/cron_smart_fill.php >> /var/log/smart_fill.log 2>&1
 * 
 * Configuração:
 *   - ACCOUNTS_TO_PROCESS: IDs das contas (ou "all" para todas)
 *   - LIMIT_PER_ACCOUNT: Máximo de itens por conta por execução
 *   - MIN_CONFIDENCE: Confiança mínima para sugestões
 *   - SOURCES: Fontes de dados a usar
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

use Dotenv\Dotenv;
use App\Database;
use App\Services\TechSheetSmartGapFillerService;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// =====================================================
// CONFIGURAÇÃO
// =====================================================

$config = [
    // Contas a processar: array de IDs ou "all"
    'accounts' => getenv('SMART_FILL_ACCOUNTS') ?: 'all',
    
    // Limite de itens por conta
    'limit_per_account' => (int)(getenv('SMART_FILL_LIMIT') ?: 50),
    
    // Confiança mínima
    'min_confidence' => (int)(getenv('SMART_FILL_MIN_CONFIDENCE') ?: 50),
    
    // Fontes de dados
    'sources' => explode(',', getenv('SMART_FILL_SOURCES') ?: 'title,description,benchmark,autocomplete,trends'),
    
    // Auto-aprovar sugestões com confiança >= este valor
    'auto_approve_threshold' => (int)(getenv('SMART_FILL_AUTO_APPROVE') ?: 85),
    
    // Enviar relatório por email
    'send_email' => filter_var(getenv('SMART_FILL_SEND_EMAIL') ?: false, FILTER_VALIDATE_BOOLEAN),
    'email_to' => getenv('SMART_FILL_EMAIL_TO') ?: '',
];

// =====================================================
// FUNÇÕES AUXILIARES
// =====================================================

function log_msg(string $msg, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$msg}" . PHP_EOL;
}

function get_accounts_to_process(string $accountsConfig): array
{
    $db = Database::getInstance();
    
    if ($accountsConfig === 'all') {
        $stmt = $db->query("
            SELECT DISTINCT account_id 
            FROM items 
            WHERE status = 'active'
            ORDER BY account_id
        ");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    return array_map('intval', explode(',', $accountsConfig));
}

function get_items_with_gaps(int $accountId, int $limit): array
{
    $db = Database::getInstance();

    $limitSql = max(1, min(500, (int)$limit));
    
    $stmt = $db->prepare("
        SELECT s.item_id, s.category_id, s.missing_required, s.missing_filter, s.missing_hidden
        FROM tech_sheet_item_summary s
        INNER JOIN items i ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
        WHERE s.account_id = :account_id
          AND i.status = 'active'
          AND (s.missing_required > 0 OR s.missing_filter > 0 OR s.missing_hidden > 0)
        ORDER BY 
            s.missing_required DESC,
            s.missing_filter DESC,
            s.last_analyzed_at ASC
        LIMIT {$limitSql}
    ");
    $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function auto_approve_high_confidence(int $accountId, int $threshold): int
{
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        UPDATE tech_sheet_suggestions 
        SET status = 'approved',
            decided_at = NOW(),
            notes = CONCAT(COALESCE(notes, ''), ' [auto-approved by cron]')
        WHERE account_id = :account_id
          AND status = 'pending'
          AND confidence >= :threshold
    ");
    $stmt->execute([
        ':account_id' => $accountId,
        ':threshold' => $threshold,
    ]);
    
    return $stmt->rowCount();
}

// =====================================================
// EXECUÇÃO PRINCIPAL
// =====================================================

log_msg("🎯 Smart Fill Cron - Iniciando execução");
log_msg("Fontes: " . implode(', ', $config['sources']));
log_msg("Confiança mínima: {$config['min_confidence']}%");
log_msg("Auto-aprovar: >= {$config['auto_approve_threshold']}%");

$startTime = microtime(true);
$accounts = get_accounts_to_process($config['accounts']);

log_msg("Contas a processar: " . count($accounts));

$totalStats = [
    'accounts_processed' => 0,
    'items_processed' => 0,
    'suggestions_created' => 0,
    'gaps_covered' => 0,
    'auto_approved' => 0,
    'errors' => 0,
];

foreach ($accounts as $accountId) {
    log_msg("📦 Processando conta #{$accountId}");
    
    $items = get_items_with_gaps($accountId, $config['limit_per_account']);
    
    if (empty($items)) {
        log_msg("  → Nenhum item com lacunas");
        continue;
    }
    
    log_msg("  → {" . count($items) . "} itens com lacunas");
    
    $service = new TechSheetSmartGapFillerService($accountId);
    $accountStats = [
        'processed' => 0,
        'suggestions' => 0,
        'gaps' => 0,
    ];
    
    foreach ($items as $item) {
        $itemId = $item['item_id'];
        $totalGaps = ($item['missing_required'] ?? 0) + ($item['missing_filter'] ?? 0);
        
        try {
            $result = $service->fillGaps($itemId, [
                'sources' => $config['sources'],
                'min_confidence' => $config['min_confidence'],
                'max_suggestions' => 3,
            ]);
            
            if ($result['success'] ?? false) {
                $saved = $result['saved_count'] ?? 0;
                $covered = $result['gaps_covered'] ?? 0;
                
                $accountStats['processed']++;
                $accountStats['suggestions'] += $saved;
                $accountStats['gaps'] += $covered;
                
                if ($saved > 0) {
                    log_msg("    ✓ {$itemId}: {$saved} sugestões, {$covered} gaps cobertos", 'DEBUG');
                }
            } else {
                $totalStats['errors']++;
            }
        } catch (\Exception $e) {
            log_msg("    ✗ {$itemId}: " . $e->getMessage(), 'ERROR');
            $totalStats['errors']++;
        }
        
        // Rate limit
        usleep(100000); // 100ms
    }
    
    // Auto-aprovar sugestões de alta confiança
    $autoApproved = 0;
    if ($config['auto_approve_threshold'] > 0) {
        $autoApproved = auto_approve_high_confidence($accountId, $config['auto_approve_threshold']);
        if ($autoApproved > 0) {
            log_msg("  → Auto-aprovadas: {$autoApproved} sugestões com confiança >= {$config['auto_approve_threshold']}%");
        }
    }
    
    log_msg("  → Resumo: {$accountStats['processed']} itens, {$accountStats['suggestions']} sugestões, {$accountStats['gaps']} gaps cobertos");
    
    $totalStats['accounts_processed']++;
    $totalStats['items_processed'] += $accountStats['processed'];
    $totalStats['suggestions_created'] += $accountStats['suggestions'];
    $totalStats['gaps_covered'] += $accountStats['gaps'];
    $totalStats['auto_approved'] += $autoApproved;
}

$elapsedSeconds = round(microtime(true) - $startTime, 2);

log_msg("═════════════════════════════════════════════════");
log_msg("📊 RESUMO FINAL");
log_msg("═════════════════════════════════════════════════");
log_msg("Contas processadas:    {$totalStats['accounts_processed']}");
log_msg("Itens processados:     {$totalStats['items_processed']}");
log_msg("Sugestões criadas:     {$totalStats['suggestions_created']}");
log_msg("Gaps cobertos:         {$totalStats['gaps_covered']}");
log_msg("Auto-aprovadas:        {$totalStats['auto_approved']}");
log_msg("Erros:                 {$totalStats['errors']}");
log_msg("Tempo de execução:     {$elapsedSeconds}s");
log_msg("═════════════════════════════════════════════════");

// Enviar email se configurado
if ($config['send_email'] && !empty($config['email_to'])) {
    $subject = "🎯 Smart Fill Report - " . date('Y-m-d');
    $body = "
Smart Fill executado com sucesso!

📊 Estatísticas:
- Contas processadas: {$totalStats['accounts_processed']}
- Itens processados: {$totalStats['items_processed']}
- Sugestões criadas: {$totalStats['suggestions_created']}
- Gaps cobertos: {$totalStats['gaps_covered']}
- Auto-aprovadas: {$totalStats['auto_approved']}
- Erros: {$totalStats['errors']}
- Tempo: {$elapsedSeconds}s

Fontes utilizadas: " . implode(', ', $config['sources']) . "
";
    
    mail($config['email_to'], $subject, $body);
    log_msg("Email enviado para: {$config['email_to']}");
}

log_msg("🎯 Smart Fill Cron - Finalizado");
