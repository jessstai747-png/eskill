<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de Monitoramento de Erros em Tempo Real
 * Captura, rastreia, analisa erros e envia alertas proativos.
 *
 * Funcionalidades:
 *  - Registra erros no banco de dados
 *  - Analisa logs de arquivo
 *  - Verifica saúde da aplicação (HTTP, DB, disco)
 *  - Envia alertas por e-mail quando problemas são detectados
 *
 * Uso via cron: php bin/error-monitor.php (a cada 5 min)
 */
class ErrorMonitoringService
{
    private PDO $db;
    private string $logPath;
    private string $stateFile;
    private string $alertRecipient;

    /** Segundos mínimos entre alertas do mesmo tipo */
    private const ALERT_COOLDOWN_SECONDS = 1800; // 30 min

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logPath = __DIR__ . '/../../storage/logs/';
        $this->stateFile = $this->logPath . 'error-monitor-state.json';
        $this->alertRecipient = $_ENV['ALERT_EMAIL_RECIPIENT'] ?? $_ENV['EMAIL_FROM'] ?? '';
    }

    /**
     * Registra erro no banco de dados
     */
    public function logError(array $errorData): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO error_monitoring
                (error_type, error_message, file, line, trace, context, user_id, account_id, url, ip_address, user_agent, severity, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $errorData['type'] ?? 'Error',
                $errorData['message'] ?? '',
                $errorData['file'] ?? '',
                $errorData['line'] ?? 0,
                json_encode($errorData['trace'] ?? []),
                json_encode($errorData['context'] ?? []),
                $errorData['user_id'] ?? null,
                $errorData['account_id'] ?? null,
                $errorData['url'] ?? $_SERVER['REQUEST_URI'] ?? '',
                $errorData['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
                $errorData['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
                $errorData['severity'] ?? 'error'
            ]);
        } catch (\Exception $e) {
            log_warning('ErrorMonitoring falhou ao registrar erro', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém erros recentes
     */
    public function getRecentErrors(int $limit = 50, ?string $severity = null): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $sql = "SELECT * FROM error_monitoring WHERE 1=1";
            $params = [];

            if ($severity) {
                $sql .= " AND severity = ?";
                $params[] = $severity;
            }

            $sql .= " ORDER BY created_at DESC LIMIT {$limitSql}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            log_error('Erro ao buscar erros recentes', [
                'limit' => $limit,
                'severity' => $severity,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Obtém estatísticas de erros
     */
    public function getErrorStats(int $hours = 24): array
    {
        try {
            // Total de erros no período
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    severity,
                    COUNT(DISTINCT error_message) as unique_errors
                FROM error_monitoring
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY severity
            ");
            $stmt->execute([$hours]);
            $bySeverity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Erros mais frequentes
            $stmt = $this->db->prepare("
                SELECT
                    error_type,
                    error_message,
                    file,
                    line,
                    COUNT(*) as occurrences,
                    MAX(created_at) as last_occurrence
                FROM error_monitoring
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY error_type, error_message, file, line
                ORDER BY occurrences DESC
                LIMIT 10
            ");
            $stmt->execute([$hours]);
            $topErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Erros por hora
            $stmt = $this->db->prepare("
                SELECT
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as count
                FROM error_monitoring
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute([$hours]);
            $byHour = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'period' => "{$hours} horas",
                'by_severity' => $bySeverity,
                'top_errors' => $topErrors,
                'timeline' => $byHour,
                'summary' => [
                    'total' => array_sum(array_column($bySeverity, 'total')),
                    'critical' => $this->countBySeverity($bySeverity, 'critical'),
                    'error' => $this->countBySeverity($bySeverity, 'error'),
                    'warning' => $this->countBySeverity($bySeverity, 'warning')
                ]
            ];
        } catch (\Exception $e) {
            log_error('Erro ao calcular estatísticas de erros', [
                'hours' => $hours,
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analisa logs de arquivo em tempo real
     */
    public function analyzeLogFile(string $filename = 'error.log', int $lines = 100): array
    {
        $filepath = $this->logPath . $filename;

        if (!file_exists($filepath)) {
            return ['error' => 'Arquivo de log não encontrado'];
        }

        $content = $this->tail($filepath, $lines);
        $errors = $this->parseLogContent($content);

        return [
            'file' => $filename,
            'lines_analyzed' => $lines,
            'errors_found' => count($errors),
            'errors' => $errors
        ];
    }

    /**
     * Limpa erros antigos
     */
    public function cleanOldErrors(int $days = 30): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM error_monitoring
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            log_error('Erro ao limpar erros antigos', [
                'days' => $days,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function countBySeverity(array $data, string $severity): int
    {
        foreach ($data as $row) {
            if ($row['severity'] === $severity) {
                return (int)$row['total'];
            }
        }
        return 0;
    }

    private function tail(string $filepath, int $lines): string
    {
        // Security: cast $lines to int to prevent command injection
        $safeLines = (int)$lines;
        $command = "tail -n {$safeLines} " . escapeshellarg($filepath);
        return shell_exec($command) ?? '';
    }

    private function parseLogContent(string $content): array
    {
        $lines = explode("\n", $content);
        $errors = [];
        $currentError = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[([^\]]+)\] PHP (Fatal error|Warning|Notice|Parse error):(.+)/', $line, $matches)) {
                if ($currentError) {
                    $errors[] = $currentError;
                }

                $currentError = [
                    'timestamp' => $matches[1],
                    'type' => trim($matches[2]),
                    'message' => trim($matches[3]),
                    'stacktrace' => []
                ];
            } elseif ($currentError && preg_match('/^Stack trace:/', $line)) {
                // Início do stack trace
                continue;
            } elseif ($currentError && preg_match('/^#\d+/', $line)) {
                // Linha do stack trace
                $currentError['stacktrace'][] = trim($line);
            } elseif ($currentError && trim($line) === '') {
                // Fim do erro atual
                $errors[] = $currentError;
                $currentError = null;
            }
        }

        if ($currentError) {
            $errors[] = $currentError;
        }

        return $errors;
    }

    // ==================== MONITORAMENTO PROATIVO ====================

    /**
     * Executa ciclo completo de monitoramento proativo.
     * Chamado via cron (bin/error-monitor.php) a cada 5 min.
     *
     * @return array{checks: array, alerts_sent: int, errors_found: int, timestamp: string}
     */
    public function runMonitoringCycle(): array
    {
        $state = $this->loadState();
        $results = [
            'checks' => [],
            'alerts_sent' => 0,
            'errors_found' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // 1. Erros recentes nos logs de arquivo
        $logCheck = $this->checkRecentLogErrors($state);
        $results['checks']['recent_errors'] = $logCheck;
        $results['errors_found'] += $logCheck['count'];

        // 2. Self-check HTTP (aplicação responde?)
        $healthCheck = $this->checkApplicationHealth();
        $results['checks']['app_health'] = $healthCheck;
        if (!$healthCheck['healthy']) {
            $results['errors_found']++;
        }

        // 3. Espaço em disco
        $diskCheck = $this->checkDiskSpace();
        $results['checks']['disk_space'] = $diskCheck;
        if ($diskCheck['critical'] ?? false) {
            $results['errors_found']++;
        }

        // 4. Tamanho dos logs (rotação necessária?)
        $logSizeCheck = $this->checkLogSizes();
        $results['checks']['log_sizes'] = $logSizeCheck;

        // 5. Conexão com banco de dados
        $dbCheck = $this->checkDatabaseHealth();
        $results['checks']['database'] = $dbCheck;
        if (!$dbCheck['connected']) {
            $results['errors_found']++;
        }

        // 6. Erros PHP recentes (php_errors.log)
        $phpErrorCheck = $this->checkPhpErrors($state);
        $results['checks']['php_errors'] = $phpErrorCheck;
        $results['errors_found'] += $phpErrorCheck['count'];

        // 7. Erros não resolvidos na tabela error_monitoring (DB)
        $unresolvedCheck = $this->checkUnresolvedErrors();
        $results['checks']['unresolved_db_errors'] = $unresolvedCheck;

        // Enviar alertas se houver problemas
        if ($results['errors_found'] > 0) {
            $results['alerts_sent'] = $this->sendAlerts($results, $state);
        }

        // Salvar estado
        $state['last_run'] = time();
        $state['last_result'] = $results;
        $this->saveState($state);

        return $results;
    }

    /**
     * Retorna resumo rápido para API de health/status.
     *
     * @return array{status: string, errors_last_run: int, last_check: ?string, app_healthy: ?bool, db_connected: ?bool}
     */
    public function getQuickStatus(): array
    {
        $state = $this->loadState();
        $lastResult = $state['last_result'] ?? [];

        return [
            'status' => ($lastResult['errors_found'] ?? 0) === 0 ? 'ok' : 'degraded',
            'errors_last_run' => $lastResult['errors_found'] ?? 0,
            'last_check' => isset($state['last_run']) && $state['last_run'] > 0
                ? date('Y-m-d H:i:s', (int) $state['last_run'])
                : null,
            'app_healthy' => $lastResult['checks']['app_health']['healthy'] ?? null,
            'db_connected' => $lastResult['checks']['database']['connected'] ?? null,
        ];
    }

    /**
     * Verifica erros recentes no error.log desde a última execução.
     */
    private function checkRecentLogErrors(array $state): array
    {
        $result = ['count' => 0, 'critical' => 0, 'errors' => [], 'warnings' => 0];
        $lastRun = (int) ($state['last_run'] ?? (time() - 300));

        $errorLog = $this->logPath . 'error.log';
        if (!file_exists($errorLog)) {
            return $result;
        }

        $fileSize = filesize($errorLog);
        if ($fileSize === false || $fileSize === 0) {
            return $result;
        }

        // Ler apenas os últimos 100KB (evitar ler gigabytes)
        $readSize = min($fileSize, 102400);
        $handle = fopen($errorLog, 'r');
        if ($handle === false) {
            return $result;
        }

        fseek($handle, max(0, $fileSize - $readSize));
        $content = fread($handle, $readSize);
        fclose($handle);

        if ($content === false) {
            return $result;
        }

        $lines = explode("\n", $content);
        $cutoffTime = date('Y-m-d H:i:s', $lastRun);
        $seenMessages = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Extrair timestamp [YYYY-MM-DD HH:MM:SS]
            if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $trimmed, $matches)) {
                continue;
            }

            if ($matches[1] < $cutoffTime) {
                continue;
            }

            $isCritical = stripos($trimmed, 'CRITICAL') !== false
                || stripos($trimmed, 'Fatal') !== false
                || stripos($trimmed, 'SQLSTATE') !== false;
            $isError = $isCritical
                || stripos($trimmed, 'ERROR') !== false
                || stripos($trimmed, 'Exception') !== false;

            if (!$isError) {
                if (stripos($trimmed, 'WARNING') !== false) {
                    $result['warnings']++;
                }
                continue;
            }

            // Dedup por mensagem normalizada
            $normalized = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', 'T', $trimmed) ?? $trimmed;
            $normalized = preg_replace('/\d+/', 'N', $normalized) ?? $normalized;
            $key = md5(substr($normalized, 0, 200));

            if (isset($seenMessages[$key])) {
                $seenMessages[$key]++;
                continue;
            }
            $seenMessages[$key] = 1;

            $result['count']++;
            if ($isCritical) {
                $result['critical']++;
            }

            if (count($result['errors']) < 10) {
                $result['errors'][] = [
                    'time' => $matches[1],
                    'message' => mb_substr($trimmed, 0, 500),
                    'critical' => $isCritical,
                    'occurrences' => 1,
                ];
            }
        }

        // Atualizar ocorrências
        foreach ($result['errors'] as &$error) {
            $normalized = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', 'T', $error['message']) ?? $error['message'];
            $normalized = preg_replace('/\d+/', 'N', $normalized) ?? $normalized;
            $key = md5(substr($normalized, 0, 200));
            $error['occurrences'] = $seenMessages[$key] ?? 1;
        }
        unset($error);

        return $result;
    }

    /**
     * Verifica se a aplicação responde via health endpoint.
     */
    private function checkApplicationHealth(): array
    {
        $appUrl = $_ENV['APP_URL'] ?? 'https://eskill.com.br';
        $healthUrl = rtrim($appUrl, '/') . '/api/health';

        $result = [
            'healthy' => false,
            'url' => $healthUrl,
            'status_code' => 0,
            'response_time_ms' => 0,
            'error' => null,
        ];

        $start = microtime(true);
        $ch = curl_init($healthUrl);
        if ($ch === false) {
            $result['error'] = 'curl_init failed';
            return $result;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'eskill-error-monitor/1.0',
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $elapsed = (microtime(true) - $start) * 1000;
        $result['status_code'] = $httpCode;
        $result['response_time_ms'] = round($elapsed, 1);

        if ($curlError !== '') {
            $result['error'] = $curlError;
            return $result;
        }

        $result['healthy'] = $httpCode >= 200 && $httpCode < 400;

        if ($elapsed > 5000) {
            $result['error'] = "Resposta lenta: {$result['response_time_ms']}ms";
        }

        return $result;
    }

    /**
     * Verifica espaço em disco.
     */
    private function checkDiskSpace(): array
    {
        $path = dirname(__DIR__, 2);
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false || $total === 0.0) {
            return ['critical' => false, 'error' => 'Não foi possível verificar disco'];
        }

        $usedPercent = round((1 - $free / $total) * 100, 1);
        $freeGB = round($free / (1024 ** 3), 2);

        return [
            'critical' => $usedPercent > 90,
            'warning' => $usedPercent > 80,
            'used_percent' => $usedPercent,
            'free_gb' => $freeGB,
        ];
    }

    /**
     * Verifica tamanho dos arquivos de log (alerta se algum > 50MB).
     */
    private function checkLogSizes(): array
    {
        $result = ['large_files' => [], 'total_mb' => 0.0];

        if (!is_dir($this->logPath)) {
            return $result;
        }

        $files = glob($this->logPath . '*.log');
        if ($files === false) {
            return $result;
        }

        foreach ($files as $file) {
            $size = filesize($file);
            if ($size === false) {
                continue;
            }

            $sizeMb = round($size / (1024 * 1024), 1);
            $result['total_mb'] += $sizeMb;

            if ($sizeMb > 50) {
                $result['large_files'][] = [
                    'file' => basename($file),
                    'size_mb' => $sizeMb,
                ];
            }
        }

        $result['total_mb'] = round($result['total_mb'], 1);
        return $result;
    }

    /**
     * Verifica saúde da conexão com o banco de dados.
     */
    private function checkDatabaseHealth(): array
    {
        $result = ['connected' => false, 'response_time_ms' => 0.0, 'error' => null];

        $start = microtime(true);
        try {
            $stmt = $this->db->query('SELECT 1');
            if ($stmt !== false) {
                $stmt->fetch();
            }
            $result['connected'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }
        $result['response_time_ms'] = round((microtime(true) - $start) * 1000, 1);

        return $result;
    }

    /**
     * Verifica erros PHP recentes em php_errors.log.
     */
    private function checkPhpErrors(array $state): array
    {
        $result = ['count' => 0, 'samples' => []];
        $lastRun = (int) ($state['last_run'] ?? (time() - 300));

        $phpErrorLog = $this->logPath . 'php_errors.log';
        if (!file_exists($phpErrorLog)) {
            return $result;
        }

        $fileSize = filesize($phpErrorLog);
        if ($fileSize === false || $fileSize === 0) {
            return $result;
        }

        $readSize = min($fileSize, 51200);
        $handle = fopen($phpErrorLog, 'r');
        if ($handle === false) {
            return $result;
        }

        fseek($handle, max(0, $fileSize - $readSize));
        $content = fread($handle, $readSize);
        fclose($handle);

        if ($content === false) {
            return $result;
        }

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            // PHP error format: [DD-Mon-YYYY HH:MM:SS TZ] PHP ...
            if (!preg_match('/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                continue;
            }

            $logTime = strtotime($matches[1]);
            if ($logTime === false || $logTime < $lastRun) {
                continue;
            }

            $isFatal = stripos($line, 'Fatal error') !== false
                || stripos($line, 'Parse error') !== false;

            $result['count']++;

            if (count($result['samples']) < 5) {
                $result['samples'][] = [
                    'message' => mb_substr(trim($line), 0, 300),
                    'fatal' => $isFatal,
                ];
            }
        }

        return $result;
    }

    /**
     * Verifica erros não resolvidos na tabela error_monitoring (últimas 2h).
     */
    private function checkUnresolvedErrors(): array
    {
        $result = ['count' => 0, 'critical' => 0];

        try {
            $stmt = $this->db->prepare("
                SELECT severity, COUNT(*) as total
                FROM error_monitoring
                WHERE resolved = 0
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                GROUP BY severity
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $result['count'] += (int) $row['total'];
                if ($row['severity'] === 'critical') {
                    $result['critical'] += (int) $row['total'];
                }
            }
        } catch (\Throwable $e) {
            // Se a tabela não existe ainda, ignora
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Envia alertas por e-mail quando problemas são detectados.
     *
     * @return int Número de alertas enviados
     */
    private function sendAlerts(array $results, array &$state): int
    {
        if ($this->alertRecipient === '') {
            // Fallback: gravar em arquivo
            $this->logAlertToFile($results);
            return 0;
        }

        // Verificar cooldown
        $lastAlerted = (int) ($state['last_alert_sent'] ?? 0);
        if ((time() - $lastAlerted) < self::ALERT_COOLDOWN_SECONDS) {
            return 0;
        }

        $checks = $results['checks'];
        $alertParts = [];
        $severity = 'WARNING';

        // Erros de log
        $errCount = $checks['recent_errors']['count'] ?? 0;
        if ($errCount > 0) {
            $critCount = $checks['recent_errors']['critical'] ?? 0;
            $alertParts[] = "Erros recentes: {$errCount} ({$critCount} critico(s))";
            foreach (($checks['recent_errors']['errors'] ?? []) as $err) {
                $icon = $err['critical'] ? '[CRIT]' : '[ERR]';
                $occ = $err['occurrences'] > 1 ? " (x{$err['occurrences']})" : '';
                $alertParts[] = "  {$icon} [{$err['time']}] " . mb_substr($err['message'], 0, 200) . $occ;
            }
            if ($critCount > 0) {
                $severity = 'CRITICAL';
            }
        }

        // App Health
        if (!($checks['app_health']['healthy'] ?? true)) {
            $severity = 'CRITICAL';
            $code = $checks['app_health']['status_code'] ?? 0;
            $error = $checks['app_health']['error'] ?? 'desconhecido';
            $alertParts[] = "Aplicacao INDISPONIVEL (HTTP {$code}): {$error}";
        }

        // Banco de dados
        if (!($checks['database']['connected'] ?? true)) {
            $severity = 'CRITICAL';
            $dbErr = $checks['database']['error'] ?? 'erro desconhecido';
            $alertParts[] = "Banco de dados INACESSIVEL: {$dbErr}";
        }

        // Disco
        if ($checks['disk_space']['critical'] ?? false) {
            $severity = 'CRITICAL';
            $pct = $checks['disk_space']['used_percent'] ?? '?';
            $free = $checks['disk_space']['free_gb'] ?? '?';
            $alertParts[] = "Disco CRITICO: {$pct}% usado ({$free}GB livre)";
        }

        // Erros PHP
        $phpCount = $checks['php_errors']['count'] ?? 0;
        if ($phpCount > 0) {
            $alertParts[] = "{$phpCount} erro(s) PHP recente(s)";
            foreach (($checks['php_errors']['samples'] ?? []) as $sample) {
                $icon = $sample['fatal'] ? '[FATAL]' : '[PHP]';
                $alertParts[] = "  {$icon} " . mb_substr($sample['message'], 0, 200);
            }
        }

        if (empty($alertParts)) {
            return 0;
        }

        // Construir e-mail
        $subject = "[eskill.com.br] [{$severity}] {$results['errors_found']} problema(s) detectado(s)";
        $body = "Relatorio de Monitoramento — eskill.com.br\n";
        $body .= str_repeat('=', 50) . "\n";
        $body .= "Horario: {$results['timestamp']}\n";
        $body .= "Severidade: {$severity}\n\n";
        $body .= implode("\n", $alertParts);
        $body .= "\n\n" . str_repeat('=', 50) . "\n";
        $body .= "Proximo check em 5 min (cooldown de alertas: 30 min)\n";

        try {
            $emailService = new EmailService();
            $sent = $emailService->send($this->alertRecipient, $subject, $body, 'text');
            if ($sent) {
                log_info('Alerta de monitoramento enviado', [
                    'severity' => $severity,
                    'errors_found' => $results['errors_found'],
                    'recipient' => $this->alertRecipient,
                ]);
                $state['last_alert_sent'] = time();
                return 1;
            }
        } catch (\Throwable $e) {
            log_error('Falha ao enviar alerta de monitoramento', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: gravar em arquivo
        $this->logAlertToFile($results);
        return 0;
    }

    /**
     * Grava alerta em arquivo de log (fallback quando e-mail falha).
     */
    private function logAlertToFile(array $results): void
    {
        $alertFile = $this->logPath . 'monitor-alerts.log';
        $line = "[{$results['timestamp']}] {$results['errors_found']} problema(s) detectado(s)";
        file_put_contents($alertFile, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    // ==================== PERSISTÊNCIA DE ESTADO ====================

    /**
     * Carrega estado persistido entre execuções.
     */
    private function loadState(): array
    {
        if (!file_exists($this->stateFile)) {
            return ['last_run' => 0, 'last_alert_sent' => 0, 'last_result' => []];
        }

        $content = file_get_contents($this->stateFile);
        if ($content === false) {
            return ['last_run' => 0, 'last_alert_sent' => 0, 'last_result' => []];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return ['last_run' => 0, 'last_alert_sent' => 0, 'last_result' => []];
        }

        return $data;
    }

    /**
     * Salva estado para a próxima execução.
     */
    private function saveState(array $state): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->stateFile,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
