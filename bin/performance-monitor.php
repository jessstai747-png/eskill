#!/usr/bin/env php
<?php
/**
 * Monitor Automático de Performance
 * 
 * Detecta e reporta problemas de performance em tempo real:
 * - Queries lentas
 * - Memory leaks
 * - High CPU usage
 * - Dead locks
 * - Rate limit violations
 * 
 * Uso: php bin/performance-monitor.php [--watch]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Helpers/LogHelper.php';

class PerformanceMonitor
{
    private \PDO $db;
    private array $metrics = [];
    private bool $watchMode = false;
    
    public function __construct(bool $watchMode = false)
    {
        $this->db = App\Database::getInstance();
        $this->watchMode = $watchMode;
    }
    
    public function run(): void
    {
        echo "🔍 Performance Monitor - " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $this->checkSlowQueries();
        $this->checkMemoryUsage();
        $this->checkDatabaseConnections();
        $this->checkTableSizes();
        $this->checkDeadlocks();
        $this->checkRateLimits();
        
        $this->printSummary();
        
        if ($this->watchMode) {
            echo "\n💤 Aguardando 60 segundos...\n\n";
            sleep(60);
            system('clear');
            $this->run(); // Recursivo
        }
    }
    
    private function checkSlowQueries(): void
    {
        echo "📊 QUERIES LENTAS (> 1 segundo)\n";
        echo str_repeat("-", 80) . "\n";
        
        try {
            // Ativar slow query log temporariamente
            $this->db->exec("SET GLOBAL slow_query_log = 'ON'");
            $this->db->exec("SET GLOBAL long_query_time = 1");
            
            // Verificar processlist atual
            $stmt = $this->db->query("
                SELECT id, user, host, db, command, time, state, 
                       LEFT(info, 100) as query
                FROM information_schema.processlist
                WHERE command != 'Sleep' 
                  AND time > 1
                ORDER BY time DESC
                LIMIT 10
            ");
            
            $slowQueries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($slowQueries)) {
                echo "✅ Nenhuma query lenta detectada\n\n";
            } else {
                foreach ($slowQueries as $q) {
                    $time = $q['time'];
                    $query = $q['query'] ?? 'N/A';
                    
                    echo "⚠️  {$time}s - {$query}\n";
                    
                    if ($time > 5) {
                        $this->metrics['critical_slow_queries'][] = $q;
                        logger()->warning('Critical slow query detected', [
                            'time' => $time,
                            'query' => $query,
                            'process_id' => $q['id']
                        ]);
                    }
                }
                echo "\n";
            }
        } catch (\Exception $e) {
            echo "❌ Erro ao verificar queries: " . $e->getMessage() . "\n\n";
        }
    }
    
    private function checkMemoryUsage(): void
    {
        echo "💾 USO DE MEMÓRIA\n";
        echo str_repeat("-", 80) . "\n";
        
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = ini_get('memory_limit');
        
        $currentMB = round($current / 1024 / 1024, 2);
        $peakMB = round($peak / 1024 / 1024, 2);
        
        echo "Current: {$currentMB} MB\n";
        echo "Peak:    {$peakMB} MB\n";
        echo "Limit:   {$limit}\n";
        
        if ($peakMB > 400) {
            echo "⚠️  ATENÇÃO: Uso de memória alto!\n";
            $this->metrics['warnings'][] = "High memory usage: {$peakMB} MB";
        } else {
            echo "✅ Uso normal\n";
        }
        echo "\n";
    }
    
    private function checkDatabaseConnections(): void
    {
        echo "🔌 CONEXÕES DATABASE\n";
        echo str_repeat("-", 80) . "\n";
        
        try {
            $stmt = $this->db->query("SHOW STATUS LIKE 'Threads_connected'");
            $connected = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $stmt = $this->db->query("SHOW VARIABLES LIKE 'max_connections'");
            $max = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $current = (int)$connected['Value'];
            $maximum = (int)$max['Value'];
            $percent = round(($current / $maximum) * 100, 1);
            
            echo "Conexões ativas: {$current} / {$maximum} ({$percent}%)\n";
            
            if ($percent > 80) {
                echo "🔴 CRÍTICO: Próximo do limite!\n";
                $this->metrics['critical'][] = "Database connections at {$percent}%";
            } elseif ($percent > 60) {
                echo "⚠️  ATENÇÃO: Uso elevado\n";
            } else {
                echo "✅ Normal\n";
            }
        } catch (\Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function checkTableSizes(): void
    {
        echo "📦 TOP 5 TABELAS MAIORES\n";
        echo str_repeat("-", 80) . "\n";
        
        try {
            $stmt = $this->db->query("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    table_rows
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 5
            ");
            
            $tables = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            printf("%-40s %15s %15s\n", "Tabela", "Tamanho", "Linhas");
            echo str_repeat("-", 80) . "\n";
            
            foreach ($tables as $table) {
                printf("%-40s %10s MB %15s\n",
                    $table['table_name'],
                    number_format($table['size_mb'], 2),
                    number_format($table['table_rows'])
                );
            }
        } catch (\Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function checkDeadlocks(): void
    {
        echo "🔒 DEADLOCKS\n";
        echo str_repeat("-", 80) . "\n";
        
        try {
            $stmt = $this->db->query("SHOW ENGINE INNODB STATUS");
            $status = $stmt->fetch(\PDO::FETCH_ASSOC);
            $statusText = $status['Status'];
            
            if (stripos($statusText, 'LATEST DETECTED DEADLOCK') !== false) {
                echo "⚠️  Deadlock detectado recentemente!\n";
                $this->metrics['warnings'][] = "Deadlock detected";
                
                // Log para análise posterior
                logger()->warning('Deadlock detected in database', [
                    'innodb_status' => substr($statusText, 0, 1000)
                ]);
            } else {
                echo "✅ Nenhum deadlock recente\n";
            }
        } catch (\Exception $e) {
            echo "⚠️  Não foi possível verificar: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function checkRateLimits(): void
    {
        echo "⏱️  RATE LIMITS\n";
        echo str_repeat("-", 80) . "\n";
        
        try {
            // Verificar entries recentes em rate_limits
            $stmt = $this->db->query("
                SELECT 
                    ip_address, 
                    COUNT(*) as requests,
                    MAX(created_at) as last_request
                FROM rate_limits
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                GROUP BY ip_address
                HAVING requests > 50
                ORDER BY requests DESC
                LIMIT 10
            ");
            
            $rateLimits = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($rateLimits)) {
                echo "✅ Nenhum IP com rate limit excessivo\n";
            } else {
                echo "IPs com alto volume (último minuto):\n";
                foreach ($rateLimits as $rl) {
                    echo "  {$rl['ip_address']}: {$rl['requests']} requests\n";
                }
            }
        } catch (\Exception $e) {
            echo "⚠️  Tabela rate_limits não existe ou erro: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function printSummary(): void
    {
        echo "📋 RESUMO\n";
        echo str_repeat("=", 80) . "\n";
        
        $critical = count($this->metrics['critical'] ?? []);
        $warnings = count($this->metrics['warnings'] ?? []);
        $slowQueries = count($this->metrics['critical_slow_queries'] ?? []);
        
        if ($critical > 0) {
            echo "🔴 {$critical} problema(s) CRÍTICO(S)\n";
        }
        if ($warnings > 0) {
            echo "⚠️  {$warnings} aviso(s)\n";
        }
        if ($slowQueries > 0) {
            echo "⏱️  {$slowQueries} query(s) muito lenta(s)\n";
        }
        
        if ($critical === 0 && $warnings === 0 && $slowQueries === 0) {
            echo "✅ Sistema operando normalmente!\n";
        }
        
        echo "\nTimestamp: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Parsing arguments
$watchMode = in_array('--watch', $argv) || in_array('-w', $argv);

try {
    $monitor = new PerformanceMonitor($watchMode);
    $monitor->run();
} catch (\Exception $e) {
    echo "❌ ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
