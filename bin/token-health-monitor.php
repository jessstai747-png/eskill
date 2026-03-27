#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Token Health Monitor
 * 
 * Script para monitorar a saude dos tokens do Mercado Livre
 * Analisa taxa de falhas, contas expiradas, e metricas gerais
 * 
 * Uso:
 *   php bin/token-health-monitor.php                    - Relatorio completo
 *   php bin/token-health-monitor.php --json             - Output JSON
 *   php bin/token-health-monitor.php --alert            - Modo alerta (exit code 1 se problemas criticos)
 *   php bin/token-health-monitor.php --email            - Envia email se problemas criticos
 * 
 * Cron recomendado:
 *   Executar a cada 4 horas para monitoramento
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

class TokenHealthMonitor
{
    private PDO $db;
    private bool $jsonOutput = false;
    private bool $alertMode = false;
    private bool $emailMode = false;
    
    // Thresholds
    private const FAILURE_RATE_WARNING = 20;     // 20% de falhas = warning
    private const FAILURE_RATE_CRITICAL = 40;    // 40% de falhas = crítico
    private const EXPIRED_ACCOUNTS_WARNING = 3;
    private const EXPIRING_SOON_HOURS = 24;      // Alertar se expira em menos de 24h
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    public function run(): void
    {
        global $argv;
        
        // Parse arguments
        $this->jsonOutput = in_array('--json', $argv);
        $this->alertMode = in_array('--alert', $argv);
        $this->emailMode = in_array('--email', $argv);
        
        $metrics = $this->collectMetrics();
        $issues = $this->analyzeIssues($metrics);
        
        if ($this->jsonOutput) {
            $this->outputJson($metrics, $issues);
        } else {
            $this->outputHuman($metrics, $issues);
        }
        
        // Se em modo alerta e há problemas críticos, exit 1
        if ($this->alertMode && !empty($issues['critical'])) {
            exit(1);
        }
        
        // Enviar email se necessário
        if ($this->emailMode && !empty($issues['critical'])) {
            $this->sendAlertEmail($metrics, $issues);
        }
    }
    
    private function collectMetrics(): array
    {
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_accounts' => $this->getTotalAccounts(),
            'active_accounts' => $this->getActiveAccounts(),
            'expired_accounts' => $this->getExpiredAccounts(),
            'expiring_soon' => $this->getExpiringSoon(),
            'refresh_attempts_24h' => $this->getRefreshAttempts24h(),
            'refresh_successes_24h' => $this->getRefreshSuccesses24h(),
            'refresh_failures_24h' => $this->getRefreshFailures24h(),
            'failure_rate_24h' => 0,
            'accounts_with_consecutive_failures' => $this->getAccountsWithConsecutiveFailures(),
            'last_refresh_times' => $this->getLastRefreshTimes(),
        ];
        
        // Calcular taxa de falha
        if ($metrics['refresh_attempts_24h'] > 0) {
            $metrics['failure_rate_24h'] = round(
                ($metrics['refresh_failures_24h'] / $metrics['refresh_attempts_24h']) * 100,
                2
            );
        }
        
        return $metrics;
    }
    
    private function analyzeIssues(array $metrics): array
    {
        $issues = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];
        
        // Taxa de falha crítica
        if ($metrics['failure_rate_24h'] >= self::FAILURE_RATE_CRITICAL) {
            $issues['critical'][] = "Taxa de falha CRÍTICA: {$metrics['failure_rate_24h']}% (limite: " . self::FAILURE_RATE_CRITICAL . "%)";
        } elseif ($metrics['failure_rate_24h'] >= self::FAILURE_RATE_WARNING) {
            $issues['warning'][] = "Taxa de falha ELEVADA: {$metrics['failure_rate_24h']}% (limite: " . self::FAILURE_RATE_WARNING . "%)";
        }
        
        // Contas expiradas
        if ($metrics['expired_accounts'] >= self::EXPIRED_ACCOUNTS_WARNING) {
            $issues['critical'][] = "{$metrics['expired_accounts']} contas com tokens expirados (requerem reconexão manual)";
        }
        
        // Contas expirando em breve
        if ($metrics['expiring_soon'] > 0) {
            $issues['warning'][] = "{$metrics['expiring_soon']} contas com tokens expirando nas próximas " . self::EXPIRING_SOON_HOURS . "h";
        }
        
        // Contas com falhas consecutivas
        if ($metrics['accounts_with_consecutive_failures'] > 0) {
            $issues['warning'][] = "{$metrics['accounts_with_consecutive_failures']} contas com falhas consecutivas de renovação";
        }
        
        // Sistema saudável
        if (empty($issues['critical']) && empty($issues['warning'])) {
            $issues['info'][] = "Sistema de tokens operando normalmente";
        }
        
        return $issues;
    }
    
    private function getTotalAccounts(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) as total FROM ml_accounts');
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getActiveAccounts(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM ml_accounts WHERE status = 'active'");
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getExpiredAccounts(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM ml_accounts WHERE status = 'expired'");
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getExpiringSoon(): int
    {
        $threshold = date('Y-m-d H:i:s', time() + (self::EXPIRING_SOON_HOURS * 3600));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM ml_accounts 
             WHERE status = 'active' 
             AND token_expires_at <= :threshold"
        );
        $stmt->execute(['threshold' => $threshold]);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getRefreshAttempts24h(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) as total FROM token_refresh_audit 
             WHERE action = 'refresh_attempt' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getRefreshSuccesses24h(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) as total FROM token_refresh_audit 
             WHERE action = 'refresh_success' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getRefreshFailures24h(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) as total FROM token_refresh_audit 
             WHERE action = 'refresh_failed' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getAccountsWithConsecutiveFailures(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) as total FROM ml_accounts 
             WHERE refresh_failure_count >= 3"
        );
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getLastRefreshTimes(): array
    {
        $stmt = $this->db->query(
            "SELECT 
                id, 
                nickname, 
                last_refresh_at,
                token_expires_at,
                refresh_failure_count,
                TIMESTAMPDIFF(HOUR, last_refresh_at, NOW()) as hours_since_refresh
             FROM ml_accounts 
             WHERE status = 'active'
             ORDER BY hours_since_refresh DESC
             LIMIT 5"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function outputJson(array $metrics, array $issues): void
    {
        echo json_encode([
            'status' => empty($issues['critical']) ? 'healthy' : 'unhealthy',
            'metrics' => $metrics,
            'issues' => $issues,
        ], JSON_PRETTY_PRINT) . "\n";
    }
    
    private function outputHuman(array $metrics, array $issues): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║          Token Health Monitor - " . date('Y-m-d H:i:s') . "          ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        
        // Métricas gerais
        echo "📊 MÉTRICAS GERAIS\n";
        echo "───────────────────────────────────────────────────────────\n";
        echo "  Total de contas:          {$metrics['total_accounts']}\n";
        echo "  Contas ativas:            {$metrics['active_accounts']}\n";
        echo "  Contas expiradas:         {$metrics['expired_accounts']}\n";
        echo "  Expirando em 24h:         {$metrics['expiring_soon']}\n";
        echo "\n";
        
        // Métricas de renovação (últimas 24h)
        echo "🔄 RENOVAÇÕES (ÚLTIMAS 24 HORAS)\n";
        echo "───────────────────────────────────────────────────────────\n";
        echo "  Tentativas:               {$metrics['refresh_attempts_24h']}\n";
        echo "  Sucessos:                 {$metrics['refresh_successes_24h']}\n";
        echo "  Falhas:                   {$metrics['refresh_failures_24h']}\n";
        echo "  Taxa de falha:            {$metrics['failure_rate_24h']}%\n";
        echo "  Falhas consecutivas:      {$metrics['accounts_with_consecutive_failures']} contas\n";
        echo "\n";
        
        // Issues
        if (!empty($issues['critical'])) {
            echo "🚨 PROBLEMAS CRÍTICOS\n";
            echo "───────────────────────────────────────────────────────────\n";
            foreach ($issues['critical'] as $issue) {
                echo "  ❌ {$issue}\n";
            }
            echo "\n";
        }
        
        if (!empty($issues['warning'])) {
            echo "⚠️  AVISOS\n";
            echo "───────────────────────────────────────────────────────────\n";
            foreach ($issues['warning'] as $issue) {
                echo "  ⚠️  {$issue}\n";
            }
            echo "\n";
        }
        
        if (!empty($issues['info'])) {
            echo "✅ STATUS\n";
            echo "───────────────────────────────────────────────────────────\n";
            foreach ($issues['info'] as $info) {
                echo "  ✅ {$info}\n";
            }
            echo "\n";
        }
        
        // Top 5 contas com refresh mais antigo
        if (!empty($metrics['last_refresh_times'])) {
            echo "🕐 TOP 5 - RENOVAÇÕES MAIS ANTIGAS\n";
            echo "───────────────────────────────────────────────────────────\n";
            foreach ($metrics['last_refresh_times'] as $account) {
                $hours = $account['hours_since_refresh'] ?? 'N/A';
                echo "  • {$account['nickname']} (ID: {$account['id']}) - {$hours}h atrás\n";
            }
            echo "\n";
        }
    }
    
    private function sendAlertEmail(array $metrics, array $issues): void
    {
        try {
            // Verificar se existe serviço de email configurado
            if (!class_exists('App\Services\EmailService')) {
                error_log('[TokenHealthMonitor] EmailService não disponível');
                return;
            }
            
            $emailService = new \App\Services\EmailService();
            
            if (!$emailService->isEnabled()) {
                error_log('[TokenHealthMonitor] EmailService desabilitado no config');
                return;
            }
            
            $alertEmail = $_ENV['ALERT_EMAIL'] ?? $_ENV['ADMIN_EMAIL'] ?? null;
            
            if (!$alertEmail) {
                error_log('[TokenHealthMonitor] ALERT_EMAIL não configurado no .env');
                return;
            }
            
            // Buscar contas que necessitam atenção
            $db = \App\Database::getInstance();
            $stmt = $db->prepare("
                SELECT 
                    id,
                    nickname,
                    token_expires_at,
                    refresh_failure_count,
                    last_refresh_error,
                    CASE
                        WHEN token_expires_at < NOW() THEN 'expired'
                        WHEN token_expires_at < DATE_ADD(NOW(), INTERVAL 24 HOUR) THEN 'expiring'
                        ELSE 'active'
                    END as status
                FROM ml_accounts
                WHERE token_expires_at < DATE_ADD(NOW(), INTERVAL 48 HOUR)
                   OR refresh_failure_count > 2
                ORDER BY 
                    CASE
                        WHEN token_expires_at < NOW() THEN 1
                        WHEN token_expires_at < DATE_ADD(NOW(), INTERVAL 24 HOUR) THEN 2
                        ELSE 3
                    END,
                    token_expires_at ASC
                LIMIT 10
            ");
            $stmt->execute();
            $problematicAccounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Formatar contas para o email
            $accounts = array_map(function($account) {
                return [
                    'nickname' => $account['nickname'],
                    'status' => $account['status'],
                    'expires_at' => date('d/m/Y H:i', strtotime($account['token_expires_at'])),
                    'failure_count' => $account['refresh_failure_count']
                ];
            }, $problematicAccounts);
            
            // Enviar email usando o novo método
            $success = $emailService->sendTokenHealthAlert(
                $alertEmail,
                $metrics,
                $issues,
                $accounts
            );
            
            if ($success) {
                echo "✉️  Email de alerta enviado para {$alertEmail}\n";
                error_log('[TokenHealthMonitor] Email de alerta enviado com sucesso');
            } else {
                echo "⚠️  Falha ao enviar email de alerta\n";
                error_log('[TokenHealthMonitor] Falha ao enviar email de alerta');
            }
            
        } catch (\Throwable $e) {
            error_log('[TokenHealthMonitor] Exceção ao enviar email: ' . $e->getMessage());
            echo "❌ Erro ao enviar email: {$e->getMessage()}\n";
        }
    }
}

// Executar
$monitor = new TokenHealthMonitor();
$monitor->run();
