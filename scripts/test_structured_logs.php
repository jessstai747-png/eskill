<?php

/**
 * Exemplo de Uso do Sistema de Logs Estruturados
 * 
 * Execute: php scripts/test_structured_logs.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\StructuredLogService;

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$logger = new StructuredLogService();

echo "=== Teste do Sistema de Logs Estruturados ===\n\n";

// 1. Log de debug
echo "1. Log de DEBUG...\n";
$logger->debug('Iniciando teste do sistema de logs', [
    'test_id' => 'test_001',
    'version' => '1.0'
]);

// 2. Log informativo
echo "2. Log de INFO...\n";
$logger->info('Usuário autenticado com sucesso', [
    'user_id' => 123,
    'email' => 'teste@example.com',
    'login_method' => 'password'
]);

// 3. Log de warning
echo "3. Log de WARNING...\n";
$logger->warning('Taxa de requisições elevada detectada', [
    'requests_per_minute' => 150,
    'limit' => 100,
    'ip' => '192.168.1.100'
]);

// 4. Log de erro
echo "4. Log de ERROR...\n";
$logger->error('Falha ao conectar com API do Mercado Livre', [
    'endpoint' => '/api/items/MLB123456',
    'http_code' => 500,
    'attempts' => 3,
    'last_error' => 'Connection timeout'
]);

// 5. Log crítico
echo "5. Log de CRITICAL...\n";
$logger->critical('Banco de dados indisponível', [
    'database' => 'mercadolivre_manager',
    'host' => 'localhost',
    'error' => 'Connection refused'
]);

// 6. Log de exceção
echo "6. Log de EXCEPTION...\n";
try {
    throw new \Exception('Erro de teste simulado', 500);
} catch (\Exception $e) {
    $logger->exception($e, [
        'operation' => 'test_exception',
        'recoverable' => false
    ]);
}

// 7. Log de performance
echo "7. Log de PERFORMANCE...\n";
$startTime = microtime(true);
sleep(2); // Simular operação lenta
$duration = microtime(true) - $startTime;
$logger->performance('test_slow_operation', $duration, [
    'operation_type' => 'database_query',
    'records_processed' => 1000
]);

// 8. Log de audit
echo "8. Log de AUDIT...\n";
$logger->audit('user_updated_settings', [
    'user_id' => 123,
    'changes' => [
        'email_notifications' => true,
        'telegram_enabled' => false
    ],
    'old_values' => [
        'email_notifications' => false,
        'telegram_enabled' => true
    ]
]);

echo "\n=== Logs criados com sucesso! ===\n\n";

// Buscar logs recentes
echo "=== Buscando logs recentes (últimos 10) ===\n\n";
$recentLogs = $logger->search(['limit' => 10]);

foreach ($recentLogs as $log) {
    $level = str_pad($log['level_name'] ?? 'INFO', 8);
    $message = substr($log['message'] ?? '', 0, 60);
    $timestamp = substr($log['datetime'] ?? '', 0, 19);
    
    echo "[{$timestamp}] {$level} {$message}\n";
}

// Estatísticas
echo "\n=== Estatísticas dos Logs ===\n\n";
$stats = $logger->getStatistics();

echo "Total de logs: " . $stats['total'] . "\n";
echo "\nPor nível:\n";
foreach ($stats['by_level'] as $level => $count) {
    echo "  " . str_pad(ucfirst($level), 10) . ": " . $count . "\n";
}

if (!empty($stats['top_errors'])) {
    echo "\nTop 5 Erros:\n";
    $topErrors = array_slice($stats['top_errors'], 0, 5, true);
    foreach ($topErrors as $error => $count) {
        echo "  [{$count}x] " . substr($error, 0, 70) . "...\n";
    }
}

if (!empty($stats['performance']['slow_operations'])) {
    echo "\nOperações Lentas:\n";
    foreach (array_slice($stats['performance']['slow_operations'], 0, 5) as $op) {
        echo "  {$op['operation']}: " . round($op['duration'], 2) . "s\n";
    }
}

echo "\n=== Teste Concluído! ===\n";
echo "\nVisualize os logs em: http://localhost/dashboard/logs\n";
echo "Arquivo de log: " . (getenv('LOG_PATH') ?: '/storage/logs/app.log') . "\n\n";
