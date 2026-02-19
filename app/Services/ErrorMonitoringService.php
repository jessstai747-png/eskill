<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de Monitoramento de Erros em Tempo Real
 * Captura, rastreia e analisa erros do sistema
 */
class ErrorMonitoringService
{
    private PDO $db;
    private string $logPath;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logPath = __DIR__ . '/../../storage/logs/';
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
}
