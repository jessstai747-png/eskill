<?php
/**
 * Script de renovação automática de tokens
 * 
 * Este script deve ser executado via CRON a cada 5-10 minutos para garantir
 * que nenhum token expire sem tentativa de renovação.
 * 
 * Exemplo de CRON:
 * *\/5 * * * * php /path/to/scripts/cron_refresh_tokens.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Jobs\TokenRefreshJob;
use App\Services\StructuredLogService;

$logger = new StructuredLogService();

try {
    // Configurações via ENV ou padrões
    // TOKEN_REFRESH_MARGIN_MINUTES: Quanto tempo antes de expirar deve renovar (default 60 min)
    // Recomenda-se aumentar para evitar janelas curtas.
    
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando Job de Renovação de Tokens...\n";
    
    $job = new TokenRefreshJob();
    $results = $job->run();
    
    echo "Resumo:\n";
    echo "  - Contas verificadas: {$results['accounts_checked']}\n";
    echo "  - Tokens renovados: {$results['tokens_refreshed']}\n";
    echo "  - Falhas: {$results['tokens_failed']}\n";
    
    if ($results['tokens_refreshed'] > 0 || $results['tokens_failed'] > 0) {
        $logger->info('Job de renovação executado', ['summary' => $results]);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Job concluído.\n";
    
} catch (Exception $e) {
    echo "ERRO CRÍTICO: " . $e->getMessage() . "\n";
    $logger->error('Erro fatal no job de renovação', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
