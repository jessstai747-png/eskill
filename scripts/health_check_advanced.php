<?php

/**
 * Sistema de Monitoramento e Health Check Avançado
 * Verifica saúde do sistema e envia alertas
 * 
 * Uso:
 * php scripts/health_check_advanced.php
 * php scripts/health_check_advanced.php --detailed
 * php scripts/health_check_advanced.php --json
 */

require_once __DIR__ . '/../vendor/autoload.php';

class HealthChecker
{
    private $config;
    private $issues = [];
    private $warnings = [];
    private $metrics = [];
    private $isHealthy = true;
    private $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->config = require __DIR__ . '/../config/app.php';

        // Carregar .env
        if (file_exists(__DIR__ . '/../.env')) {
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    putenv(trim($line));
                }
            }
        }
    }

    public function runAllChecks(): array
    {
        $this->log("Iniciando verificação de saúde do sistema...");

        $this->checkDatabase();
        $this->checkDiskSpace();
        $this->checkLogFiles();
        $this->checkTokens();
        $this->checkSystemLoad();
        $this->checkHTTPEndpoints();
        $this->checkFilePermissions();
        $this->checkPHPConfiguration();
        $this->checkBackupStatus();

        $this->metrics['execution_time'] = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->metrics['timestamp'] = date('Y-m-d H:i:s');

        return $this->generateReport();
    }

    private function checkDatabase(): void
    {
        $this->log("Verificando banco de dados...");

        try {
            $db = App\Database::getInstance();

            // Teste básico de conexão
            $start = microtime(true);
            $result = $db->query("SELECT 1 as test");
            $this->metrics['db_connection_time'] = round((microtime(true) - $start) * 1000, 2);

            if (!$result) {
                throw new Exception("Consulta falhou");
            }

            // Verificar tabelas essenciais
            $tables = ['users', 'ml_accounts', 'sync_logs'];
            foreach ($tables as $table) {
                $stmt = $db->prepare("SHOW TABLES LIKE :table");
                $stmt->bindValue(':table', $table);
                $stmt->execute();

                if (!$stmt->fetch()) {
                    $this->addIssue("Tabela essencial não encontrada: $table");
                }
            }

            // Verificar tamanho do banco
            $stmt = $db->prepare("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb,
                    COUNT(*) as table_count
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $stmt->execute();
            $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->metrics['db_size_mb'] = $dbInfo['size_mb'];
            $this->metrics['db_table_count'] = $dbInfo['table_count'];

            $this->log("✅ Banco de dados OK ({$dbInfo['size_mb']} MB)");
        } catch (Exception $e) {
            $this->addIssue("Banco de dados: " . $e->getMessage());
        }
    }

    private function checkDiskSpace(): void
    {
        $this->log("Verificando espaço em disco...");

        $paths = [
            'root' => __DIR__ . '/..',
            'storage' => __DIR__ . '/../storage',
            'backup' => '/backup/mercadolivre'
        ];

        foreach ($paths as $name => $path) {
            if (!is_dir($path)) continue;

            $diskFree = disk_free_space($path);
            $diskTotal = disk_total_space($path);
            $diskUsed = $diskTotal - $diskFree;
            $diskPercent = ($diskUsed / $diskTotal) * 100;

            $this->metrics["disk_{$name}_used_percent"] = round($diskPercent, 1);
            $this->metrics["disk_{$name}_free_gb"] = round($diskFree / 1024 / 1024 / 1024, 2);

            if ($diskPercent > 95) {
                $this->addIssue("Espaço crítico em $name: " . round($diskPercent, 1) . "% usado");
            } elseif ($diskPercent > 85) {
                $this->addWarning("Espaço baixo em $name: " . round($diskPercent, 1) . "% usado");
            }
        }

        $this->log("✅ Espaço em disco verificado");
    }

    private function checkLogFiles(): void
    {
        $this->log("Verificando logs...");

        $logFiles = [
            'app' => __DIR__ . '/../storage/logs/app.log',
            'php' => '/var/log/php_errors.log',
            'apache' => '/var/log/apache2/error.log',
            'nginx' => '/var/log/nginx/error.log'
        ];

        $errorCount = 0;

        foreach ($logFiles as $name => $logFile) {
            if (!file_exists($logFile)) continue;

            // Contar erros das últimas 24 horas
            $command = "find " . escapeshellarg($logFile) . " -mtime -1 -exec grep -i 'error\\|critical\\|fatal' {} \\; | wc -l";
            $recentErrors = (int)shell_exec($command);

            $this->metrics["log_{$name}_errors_24h"] = $recentErrors;
            $errorCount += $recentErrors;

            // Verificar tamanho do arquivo de log
            $logSize = filesize($logFile) / 1024 / 1024; // MB
            $this->metrics["log_{$name}_size_mb"] = round($logSize, 2);

            if ($logSize > 100) {
                $this->addWarning("Log $name muito grande: " . round($logSize, 1) . " MB");
            }
        }

        if ($errorCount > 100) {
            $this->addIssue("Muitos erros recentes: $errorCount nas últimas 24h");
        } elseif ($errorCount > 50) {
            $this->addWarning("Erros elevados: $errorCount nas últimas 24h");
        }

        $this->log("✅ Logs verificados ($errorCount erros recentes)");
    }

    private function checkTokens(): void
    {
        $this->log("Verificando tokens ML...");

        try {
            $db = App\Database::getInstance();

            // Tokens expirando em 3 dias
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as expiring_count,
                    COUNT(CASE WHEN token_expires_at < NOW() THEN 1 END) as expired_count
                FROM ml_accounts 
                WHERE token_expires_at < DATE_ADD(NOW(), INTERVAL 3 DAY)
                AND status = 'active'
            ");
            $stmt->execute();
            $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->metrics['tokens_expiring_3d'] = $tokenInfo['expiring_count'];
            $this->metrics['tokens_expired'] = $tokenInfo['expired_count'];

            if ($tokenInfo['expired_count'] > 0) {
                $this->addIssue("Tokens expirados: {$tokenInfo['expired_count']}");
            }

            if ($tokenInfo['expiring_count'] > 0) {
                $this->addWarning("Tokens expirando em 3 dias: {$tokenInfo['expiring_count']}");
            }

            // Total de contas ativas
            $stmt = $db->prepare("SELECT COUNT(*) as active_count FROM ml_accounts WHERE status = 'active'");
            $stmt->execute();
            $this->metrics['accounts_active'] = $stmt->fetchColumn();

            $this->log("✅ Tokens verificados");
        } catch (Exception $e) {
            $this->addWarning("Erro ao verificar tokens: " . $e->getMessage());
        }
    }

    private function checkSystemLoad(): void
    {
        $this->log("Verificando carga do sistema...");

        // Load average
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->metrics['load_1m'] = round($load[0], 2);
            $this->metrics['load_5m'] = round($load[1], 2);
            $this->metrics['load_15m'] = round($load[2], 2);

            $cpuCount = (int)shell_exec('nproc');
            if ($load[0] > $cpuCount * 2) {
                $this->addIssue("Carga alta do sistema: {$load[0]}");
            }
        }

        // Uso de memória
        $memInfo = $this->parseMemInfo();
        if ($memInfo) {
            $memUsed = $memInfo['MemTotal'] - $memInfo['MemFree'] - $memInfo['Buffers'] - $memInfo['Cached'];
            $memPercent = ($memUsed / $memInfo['MemTotal']) * 100;

            $this->metrics['memory_used_percent'] = round($memPercent, 1);
            $this->metrics['memory_free_gb'] = round($memInfo['MemFree'] / 1024 / 1024, 2);

            if ($memPercent > 90) {
                $this->addIssue("Uso crítico de memória: " . round($memPercent, 1) . "%");
            } elseif ($memPercent > 80) {
                $this->addWarning("Uso alto de memória: " . round($memPercent, 1) . "%");
            }
        }

        $this->log("✅ Carga do sistema verificada");
    }

    private function checkHTTPEndpoints(): void
    {
        $this->log("Verificando endpoints HTTP...");

        $baseUrl = getenv('APP_URL') ?: 'http://localhost';
        $endpoints = [
            'home' => '/',
            'health' => '/public/diagnostic.php'
        ];

        foreach ($endpoints as $name => $path) {
            $url = rtrim($baseUrl, '/') . $path;

            $start = microtime(true);
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($response === false) {
                $this->addIssue("Endpoint $name não responde: $url");
                $this->metrics["http_{$name}_status"] = 'ERROR';
            } else {
                $httpCode = 200; // Simplificado
                $this->metrics["http_{$name}_response_time"] = $responseTime;
                $this->metrics["http_{$name}_status"] = 'OK';

                if ($responseTime > 5000) {
                    $this->addWarning("Endpoint $name lento: {$responseTime}ms");
                }
            }
        }

        $this->log("✅ Endpoints HTTP verificados");
    }

    private function checkFilePermissions(): void
    {
        $this->log("Verificando permissões...");

        $paths = [
            'storage' => __DIR__ . '/../storage',
            'cache' => __DIR__ . '/../storage/cache',
            'logs' => __DIR__ . '/../storage/logs',
            'env' => __DIR__ . '/../.env'
        ];

        foreach ($paths as $name => $path) {
            if (!file_exists($path)) {
                $this->addWarning("Caminho não existe: $name ($path)");
                continue;
            }

            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $this->metrics["perms_$name"] = $perms;

            if ($name === 'env' && $perms !== '0644') {
                $this->addWarning(".env com permissões incorretas: $perms");
            }
        }

        $this->log("✅ Permissões verificadas");
    }

    private function checkPHPConfiguration(): void
    {
        $this->log("Verificando configuração PHP...");

        $config = [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'display_errors' => ini_get('display_errors'),
            'log_errors' => ini_get('log_errors'),
        ];

        $this->metrics['php_version'] = PHP_VERSION;
        $this->metrics['php_config'] = $config;

        // Verificações críticas para produção
        if (getenv('APP_ENV') === 'production') {
            if ($config['display_errors']) {
                $this->addIssue("display_errors está ativado em produção");
            }

            if (!$config['log_errors']) {
                $this->addWarning("log_errors está desativado");
            }
        }

        $this->log("✅ Configuração PHP verificada");
    }

    private function checkBackupStatus(): void
    {
        $this->log("Verificando status de backup...");

        $backupPath = getenv('BACKUP_PATH') ?: '/backup/mercadolivre';

        if (!is_dir($backupPath)) {
            $this->addWarning("Diretório de backup não encontrado: $backupPath");
            return;
        }

        // Verificar backup mais recente
        $latestBackup = shell_exec("find $backupPath -name '*.sql*' -type f -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | cut -d' ' -f2-");

        if ($latestBackup) {
            $latestBackup = trim($latestBackup);
            $backupTime = filemtime($latestBackup);
            $hoursAgo = (time() - $backupTime) / 3600;

            $this->metrics['backup_latest'] = date('Y-m-d H:i:s', $backupTime);
            $this->metrics['backup_hours_ago'] = round($hoursAgo, 1);

            if ($hoursAgo > 48) {
                $this->addIssue("Último backup há " . round($hoursAgo, 1) . " horas");
            } elseif ($hoursAgo > 30) {
                $this->addWarning("Último backup há " . round($hoursAgo, 1) . " horas");
            }
        } else {
            $this->addWarning("Nenhum backup encontrado");
        }

        $this->log("✅ Status de backup verificado");
    }

    private function parseMemInfo(): ?array
    {
        $memInfoFile = '/proc/meminfo';
        if (!file_exists($memInfoFile)) return null;

        $memInfo = [];
        $lines = file($memInfoFile);

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s*(\d+)\s*kB/', $line, $matches)) {
                $memInfo[$matches[1]] = (int)$matches[2];
            }
        }

        return $memInfo;
    }

    private function addIssue(string $message): void
    {
        $this->issues[] = $message;
        $this->isHealthy = false;
        $this->log("❌ " . $message);
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
        $this->log("⚠️ " . $message);
    }

    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] $message\n";
    }

    private function generateReport(): array
    {
        $status = $this->isHealthy ? 'healthy' : 'unhealthy';
        $level = $this->isHealthy ?
            (count($this->warnings) > 0 ? 'warning' : 'ok') :
            'critical';

        return [
            'status' => $status,
            'level' => $level,
            'timestamp' => date('c'),
            'execution_time_ms' => $this->metrics['execution_time'],
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
            'summary' => [
                'healthy' => $this->isHealthy,
                'issue_count' => count($this->issues),
                'warning_count' => count($this->warnings),
                'check_count' => 9
            ]
        ];
    }

    public function sendAlerts(array $report): void
    {
        if ($report['level'] === 'ok') return;

        $message = $this->formatAlertMessage($report);

        // Telegram
        if (getenv('TELEGRAM_ENABLED') === 'true') {
            $this->sendTelegramAlert($message, $report['level']);
        }

        // Email
        if (getenv('EMAIL_ENABLED') === 'true') {
            $this->sendEmailAlert($message, $report['level']);
        }

        // Log
        $this->logAlert($report);
    }

    private function formatAlertMessage(array $report): string
    {
        $emoji = [
            'ok' => '✅',
            'warning' => '⚠️',
            'critical' => '❌'
        ];

        $message = $emoji[$report['level']] . " ML Manager Health Check\n\n";
        $message .= "Status: " . strtoupper($report['status']) . "\n";
        $message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

        if (!empty($report['issues'])) {
            $message .= "🚨 Problemas Críticos:\n";
            foreach ($report['issues'] as $issue) {
                $message .= "• $issue\n";
            }
            $message .= "\n";
        }

        if (!empty($report['warnings'])) {
            $message .= "⚠️ Avisos:\n";
            foreach ($report['warnings'] as $warning) {
                $message .= "• $warning\n";
            }
        }

        return $message;
    }

    private function sendTelegramAlert(string $message, string $level): void
    {
        $botToken = getenv('TELEGRAM_BOT_TOKEN');
        $chatId = getenv('TELEGRAM_CHAT_ID');

        if (!$botToken || !$chatId) return;

        $url = "https://api.telegram.org/bot$botToken/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data)
            ]
        ]);

        @file_get_contents($url, false, $context);
    }

    private function sendEmailAlert(string $message, string $level): void
    {
        $to = getenv('ALERT_EMAIL');
        if (!$to) return;

        $subject = "ML Manager Health Alert - " . strtoupper($level);
        $headers = "From: " . (getenv('EMAIL_FROM') ?: 'noreply@localhost');

        @mail($to, $subject, $message, $headers);
    }

    private function logAlert(array $report): void
    {
        $logFile = __DIR__ . '/../storage/logs/health_alerts.log';
        $logEntry = json_encode($report) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Execução do script
if (php_sapi_name() === 'cli') {
    $checker = new HealthChecker();
    $detailed = in_array('--detailed', $argv);
    $json = in_array('--json', $argv);

    $report = $checker->runAllChecks();

    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT);
    } else {
        echo "\n====================================\n";
        echo "RELATÓRIO DE SAÚDE DO SISTEMA\n";
        echo "====================================\n\n";

        echo "Status Geral: " . strtoupper($report['status']) . "\n";
        echo "Nível: " . strtoupper($report['level']) . "\n";
        echo "Tempo de Execução: {$report['execution_time_ms']}ms\n\n";

        if (!empty($report['issues'])) {
            echo "🚨 PROBLEMAS CRÍTICOS:\n";
            foreach ($report['issues'] as $issue) {
                echo "  • $issue\n";
            }
            echo "\n";
        }

        if (!empty($report['warnings'])) {
            echo "⚠️ AVISOS:\n";
            foreach ($report['warnings'] as $warning) {
                echo "  • $warning\n";
            }
            echo "\n";
        }

        if ($detailed && !empty($report['metrics'])) {
            echo "📊 MÉTRICAS:\n";
            foreach ($report['metrics'] as $key => $value) {
                if (!is_array($value)) {
                    echo "  $key: $value\n";
                }
            }
        }
    }

    // Enviar alertas se necessário
    $checker->sendAlerts($report);

    // Exit code baseado no status
    exit($report['status'] === 'healthy' ? 0 : 1);
}
