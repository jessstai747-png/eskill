<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * 🔌 Circuit Breaker Service
 * 
 * Implementa o padrão Circuit Breaker para proteger o sistema
 * quando a API do Mercado Livre está instável ou fora do ar.
 * 
 * Estados:
 * - CLOSED: Operação normal, requisições passam
 * - OPEN: Circuito aberto, requisições falham imediatamente
 * - HALF_OPEN: Teste de recuperação, algumas requisições passam
 * 
 * @package App\Services
 */
class CircuitBreakerService
{
    private PDO $db;
    private string $serviceName;
    
    // Configurações
    private int $failureThreshold = 5;       // Falhas para abrir circuito
    private int $successThreshold = 3;       // Sucessos para fechar (half-open)
    private int $openTimeout = 60;           // Segundos para testar novamente
    private int $halfOpenMaxRequests = 3;    // Requisições permitidas em half-open
    
    // Estados
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    public function __construct(string $serviceName = 'mercadolivre_api')
    {
        $this->db = Database::getInstance();
        $this->serviceName = $serviceName;
        $this->ensureTableExists();
    }

    /**
     * Configura thresholds personalizados
     */
    public function configure(array $config): self
    {
        $this->failureThreshold = $config['failure_threshold'] ?? $this->failureThreshold;
        $this->successThreshold = $config['success_threshold'] ?? $this->successThreshold;
        $this->openTimeout = $config['open_timeout'] ?? $this->openTimeout;
        $this->halfOpenMaxRequests = $config['half_open_max_requests'] ?? $this->halfOpenMaxRequests;
        return $this;
    }

    /**
     * Verifica se a requisição pode prosseguir
     * 
     * @return bool true se pode fazer requisição
     * @throws CircuitOpenException se circuito está aberto
     */
    public function canRequest(): bool
    {
        $state = $this->getState();
        
        switch ($state['state']) {
            case self::STATE_CLOSED:
                return true;
                
            case self::STATE_OPEN:
                // Verificar se timeout expirou
                if ($this->hasOpenTimeoutExpired($state)) {
                    $this->transitionToHalfOpen();
                    return true;
                }
                return false;
                
            case self::STATE_HALF_OPEN:
                // Permitir algumas requisições de teste
                return $state['half_open_requests'] < $this->halfOpenMaxRequests;
                
            default:
                return true;
        }
    }

    /**
     * Registra sucesso de uma requisição
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();
        
        if ($state['state'] === self::STATE_HALF_OPEN) {
            $newSuccessCount = ($state['half_open_successes'] ?? 0) + 1;
            
            if ($newSuccessCount >= $this->successThreshold) {
                // Recuperação bem-sucedida, fecha o circuito
                $this->transitionToClosed();
                $this->logEvent('circuit_closed', 'Circuito fechado após recuperação');
            } else {
                $this->incrementHalfOpenSuccess();
            }
        } else {
            // Reset contadores de falha
            $this->resetFailureCount();
        }
    }

    /**
     * Registra falha de uma requisição
     */
    public function recordFailure(string $errorMessage = ''): void
    {
        $state = $this->getState();
        
        if ($state['state'] === self::STATE_HALF_OPEN) {
            // Falha em half-open volta para open
            $this->transitionToOpen($errorMessage);
            $this->logEvent('circuit_reopened', 'Circuito reaberto após falha em half-open');
        } else {
            $newFailureCount = $this->incrementFailureCount();
            
            if ($newFailureCount >= $this->failureThreshold) {
                $this->transitionToOpen($errorMessage);
                $this->logEvent('circuit_opened', "Circuito aberto após {$newFailureCount} falhas: {$errorMessage}");
            }
        }
    }

    /**
     * Obtém estado atual do circuito
     */
    public function getState(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM circuit_breaker_state 
            WHERE service_name = :service
        ");
        $stmt->execute(['service' => $this->serviceName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            $this->initializeState();
            return $this->getState();
        }
        
        return $row;
    }

    /**
     * Obtém estatísticas do circuit breaker
     */
    public function getStats(): array
    {
        $state = $this->getState();
        
        // Últimas 24h de eventos
        $stmt = $this->db->prepare("
            SELECT 
                event_type,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
            FROM circuit_breaker_log
            WHERE service_name = :service
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY event_type
        ");
        $stmt->execute(['service' => $this->serviceName]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Taxa de erro nas últimas 1h
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN event_type = 'failure' THEN 1 ELSE 0 END) as failures,
                SUM(CASE WHEN event_type = 'success' THEN 1 ELSE 0 END) as successes
            FROM circuit_breaker_log
            WHERE service_name = :service
              AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute(['service' => $this->serviceName]);
        $hourly = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalRequests = ($hourly['failures'] ?? 0) + ($hourly['successes'] ?? 0);
        $errorRate = $totalRequests > 0 
            ? round(($hourly['failures'] / $totalRequests) * 100, 2) 
            : 0;
        
        return [
            'service' => $this->serviceName,
            'current_state' => $state['state'],
            'failure_count' => $state['failure_count'],
            'last_failure_at' => $state['last_failure_at'],
            'last_success_at' => $state['last_success_at'],
            'state_changed_at' => $state['state_changed_at'],
            'error_rate_1h' => $errorRate,
            'requests_1h' => $totalRequests,
            'events_24h' => $events,
            'thresholds' => [
                'failure_threshold' => $this->failureThreshold,
                'success_threshold' => $this->successThreshold,
                'open_timeout' => $this->openTimeout,
            ],
        ];
    }

    /**
     * Força reset do circuito (manual)
     */
    public function forceReset(): void
    {
        $this->transitionToClosed();
        $this->logEvent('force_reset', 'Circuito resetado manualmente');
    }

    /**
     * Executa uma função com proteção do circuit breaker
     * 
     * @param callable $fn Função a executar
     * @param callable|null $fallback Fallback se circuito aberto
     * @return mixed
     */
    public function execute(callable $fn, ?callable $fallback = null)
    {
        if (!$this->canRequest()) {
            if ($fallback) {
                return $fallback();
            }
            throw new \RuntimeException(
                "Circuit breaker is OPEN for service: {$this->serviceName}. " .
                "Try again later."
            );
        }

        try {
            $result = $fn();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure($e->getMessage());
            
            if ($fallback) {
                return $fallback();
            }
            throw $e;
        }
    }

    // ========================================================================
    // MÉTODOS PRIVADOS
    // ========================================================================

    private function transitionToOpen(string $reason = ''): void
    {
        $stmt = $this->db->prepare("
            UPDATE circuit_breaker_state 
            SET state = :state,
                state_changed_at = NOW(),
                last_failure_reason = :reason,
                half_open_requests = 0,
                half_open_successes = 0
            WHERE service_name = :service
        ");
        $stmt->execute([
            'state' => self::STATE_OPEN,
            'reason' => substr($reason, 0, 500),
            'service' => $this->serviceName,
        ]);
    }

    private function transitionToHalfOpen(): void
    {
        $stmt = $this->db->prepare("
            UPDATE circuit_breaker_state 
            SET state = :state,
                state_changed_at = NOW(),
                half_open_requests = 0,
                half_open_successes = 0
            WHERE service_name = :service
        ");
        $stmt->execute([
            'state' => self::STATE_HALF_OPEN,
            'service' => $this->serviceName,
        ]);
        $this->logEvent('circuit_half_open', 'Circuito em modo de teste');
    }

    private function transitionToClosed(): void
    {
        $stmt = $this->db->prepare("
            UPDATE circuit_breaker_state 
            SET state = :state,
                state_changed_at = NOW(),
                failure_count = 0,
                half_open_requests = 0,
                half_open_successes = 0,
                last_failure_reason = NULL
            WHERE service_name = :service
        ");
        $stmt->execute([
            'state' => self::STATE_CLOSED,
            'service' => $this->serviceName,
        ]);
    }

    private function incrementFailureCount(): int
    {
        $stmt = $this->db->prepare("
            UPDATE circuit_breaker_state 
            SET failure_count = failure_count + 1,
                last_failure_at = NOW()
            WHERE service_name = :service
        ");
        $stmt->execute(['service' => $this->serviceName]);
        
        return $this->getState()['failure_count'];
    }

    private function incrementHalfOpenSuccess(): void
    {
        $stmt = $this->db->prepare("
            UPDATE circuit_breaker_state 
            SET half_open_successes = half_open_successes + 1,
                half_open_requests = half_open_requests + 1,
                last_success_at = NOW()
            WHERE service_name = :service
        ");
        $stmt->execute(['service' => $this->serviceName]);
    }

    private function resetFailureCount(): void
    {
        $stmt = $this->db->prepare("
            UPDATE circuit_breaker_state 
            SET failure_count = 0,
                last_success_at = NOW()
            WHERE service_name = :service
        ");
        $stmt->execute(['service' => $this->serviceName]);
    }

    private function hasOpenTimeoutExpired(array $state): bool
    {
        if (empty($state['state_changed_at'])) {
            return true;
        }
        
        $changedAt = strtotime($state['state_changed_at']);
        return (time() - $changedAt) >= $this->openTimeout;
    }

    private function logEvent(string $eventType, string $message): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO circuit_breaker_log 
                (service_name, event_type, message, created_at)
                VALUES (:service, :event, :message, NOW())
            ");
            $stmt->execute([
                'service' => $this->serviceName,
                'event' => $eventType,
                'message' => substr($message, 0, 1000),
            ]);
        } catch (\Exception $e) {
            // Log silencioso
            log_warning('CircuitBreaker log error', ['service' => 'CircuitBreakerService', 'error' => $e->getMessage()]);
        }
    }

    private function initializeState(): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO circuit_breaker_state 
            (service_name, state, failure_count, state_changed_at)
            VALUES (:service, :state, 0, NOW())
        ");
        $stmt->execute([
            'service' => $this->serviceName,
            'state' => self::STATE_CLOSED,
        ]);
    }

    private function ensureTableExists(): void
    {
        // Verificar se tabelas existem
        try {
            $this->db->query("SELECT 1 FROM circuit_breaker_state LIMIT 1");
        } catch (\Exception $e) {
            // Criar tabelas
            $this->createTables();
        }
    }

    private function createTables(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS circuit_breaker_state (
                service_name VARCHAR(100) PRIMARY KEY,
                state ENUM('closed', 'open', 'half_open') NOT NULL DEFAULT 'closed',
                failure_count INT UNSIGNED NOT NULL DEFAULT 0,
                half_open_requests INT UNSIGNED NOT NULL DEFAULT 0,
                half_open_successes INT UNSIGNED NOT NULL DEFAULT 0,
                last_failure_at DATETIME NULL,
                last_success_at DATETIME NULL,
                last_failure_reason VARCHAR(500) NULL,
                state_changed_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS circuit_breaker_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                service_name VARCHAR(100) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                message TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_service_time (service_name, created_at),
                INDEX idx_event_time (event_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
