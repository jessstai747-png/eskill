<?php

namespace App\Services\AI\Core;

use Exception;

/**
 * Circuit Breaker Pattern
 * Previne chamadas repetidas a serviços que estão falhando
 */
class CircuitBreaker
{
    private string $serviceName;
    private CacheManagerService $cache;
    private LogService $logger;

    // Estados do Circuit Breaker
    const STATE_CLOSED = 'closed';     // Normal - todas as requisições passam
    const STATE_OPEN = 'open';         // Falhou muito - bloqueia requisições
    const STATE_HALF_OPEN = 'half_open'; // Teste - permite algumas requisições

    // Configurações
    const FAILURE_THRESHOLD = 5;       // Falhas antes de abrir
    const SUCCESS_THRESHOLD = 2;       // Sucessos para fechar
    const TIMEOUT = 60;                // Segundos antes de tentar novamente (half-open)
    const WINDOW_TIME = 300;           // Janela de tempo para contar falhas (5 min)

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
        $this->cache = new CacheManagerService();
        $this->logger = new LogService();
    }

    /**
     * Executa operação protegida pelo circuit breaker
     */
    public function call(callable $operation)
    {
        $state = $this->getState();

        switch ($state) {
            case self::STATE_OPEN:
                // Circuit está aberto - rejeita requisição
                if ($this->shouldAttemptReset()) {
                    // Tenta meio-aberto
                    $this->setState(self::STATE_HALF_OPEN);
                    return $this->executeAndHandle($operation);
                }
                
                throw new Exception("Circuit breaker is OPEN for {$this->serviceName}. Service temporarily unavailable.");

            case self::STATE_HALF_OPEN:
                // Testa se serviço voltou
                return $this->executeAndHandle($operation);

            case self::STATE_CLOSED:
            default:
                // Normal - executa
                return $this->executeAndHandle($operation);
        }
    }

    /**
     * Executa operação e trata resultado
     */
    private function executeAndHandle(callable $operation)
    {
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onFailure($e);
            throw $e;
        }
    }

    /**
     * Trata sucesso
     */
    private function onSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Incrementa sucessos consecutivos
            $successCount = $this->incrementSuccessCount();
            
            if ($successCount >= self::SUCCESS_THRESHOLD) {
                // Fecha circuit
                $this->setState(self::STATE_CLOSED);
                $this->resetCounters();
                
                $this->logger->info("Circuit breaker CLOSED for {$this->serviceName}");
            }
        } else {
            // Mantém fechado
            $this->resetFailureCount();
        }
    }

    /**
     * Trata falha
     */
    private function onFailure(Exception $e): void
    {
        $failureCount = $this->incrementFailureCount();
        $state = $this->getState();

        $this->logger->warning("Circuit breaker failure for {$this->serviceName}", [
            'failure_count' => $failureCount,
            'state' => $state,
            'error' => $e->getMessage()
        ]);

        if ($state === self::STATE_HALF_OPEN) {
            // Falhou no teste - volta para aberto
            $this->setState(self::STATE_OPEN);
            $this->resetSuccessCount();
        } elseif ($failureCount >= self::FAILURE_THRESHOLD) {
            // Atingiu limite - abre circuit
            $this->setState(self::STATE_OPEN);
            $this->setOpenTimestamp();
            
            $this->logger->error("Circuit breaker OPENED for {$this->serviceName}", [
                'failure_count' => $failureCount
            ]);
        }
    }

    /**
     * Verifica se deve tentar resetar
     */
    private function shouldAttemptReset(): bool
    {
        $openTimestamp = $this->getOpenTimestamp();
        
        if (!$openTimestamp) {
            return true;
        }

        return (time() - $openTimestamp) >= self::TIMEOUT;
    }

    // ========== Métodos de Estado ==========

    private function getState(): string
    {
        return $this->cache->get(
            $this->getStateKey(),
            'circuit_breaker'
        ) ?? self::STATE_CLOSED;
    }

    private function setState(string $state): void
    {
        $this->cache->set(
            $this->getStateKey(),
            $state,
            'circuit_breaker',
            3600 // 1 hora
        );
    }

    private function getFailureCount(): int
    {
        return (int)($this->cache->get(
            $this->getFailureKey(),
            'circuit_breaker'
        ) ?? 0);
    }

    private function incrementFailureCount(): int
    {
        $count = $this->getFailureCount() + 1;
        $this->cache->set(
            $this->getFailureKey(),
            $count,
            'circuit_breaker',
            self::WINDOW_TIME
        );
        return $count;
    }

    private function resetFailureCount(): void
    {
        $this->cache->delete(
            $this->getFailureKey(),
            'circuit_breaker'
        );
    }

    private function getSuccessCount(): int
    {
        return (int)($this->cache->get(
            $this->getSuccessKey(),
            'circuit_breaker'
        ) ?? 0);
    }

    private function incrementSuccessCount(): int
    {
        $count = $this->getSuccessCount() + 1;
        $this->cache->set(
            $this->getSuccessKey(),
            $count,
            'circuit_breaker',
            300 // 5 minutos
        );
        return $count;
    }

    private function resetSuccessCount(): void
    {
        $this->cache->delete(
            $this->getSuccessKey(),
            'circuit_breaker'
        );
    }

    private function getOpenTimestamp(): ?int
    {
        return $this->cache->get(
            $this->getOpenTimestampKey(),
            'circuit_breaker'
        );
    }

    private function setOpenTimestamp(): void
    {
        $this->cache->set(
            $this->getOpenTimestampKey(),
            time(),
            'circuit_breaker',
            3600 // 1 hora
        );
    }

    private function resetCounters(): void
    {
        $this->resetFailureCount();
        $this->resetSuccessCount();
        $this->cache->delete($this->getOpenTimestampKey(), 'circuit_breaker');
    }

    // ========== Chaves de Cache ==========

    private function getStateKey(): string
    {
        return "circuit_breaker_state_{$this->serviceName}";
    }

    private function getFailureKey(): string
    {
        return "circuit_breaker_failures_{$this->serviceName}";
    }

    private function getSuccessKey(): string
    {
        return "circuit_breaker_successes_{$this->serviceName}";
    }

    private function getOpenTimestampKey(): string
    {
        return "circuit_breaker_opened_at_{$this->serviceName}";
    }

    /**
     * Obtém estatísticas do circuit breaker
     */
    public function getStats(): array
    {
        return [
            'service' => $this->serviceName,
            'state' => $this->getState(),
            'failure_count' => $this->getFailureCount(),
            'success_count' => $this->getSuccessCount(),
            'open_timestamp' => $this->getOpenTimestamp(),
            'time_until_retry' => $this->getTimeUntilRetry()
        ];
    }

    private function getTimeUntilRetry(): ?int
    {
        $openTimestamp = $this->getOpenTimestamp();
        
        if (!$openTimestamp || $this->getState() !== self::STATE_OPEN) {
            return null;
        }

        $elapsed = time() - $openTimestamp;
        $remaining = self::TIMEOUT - $elapsed;
        
        return max(0, $remaining);
    }

    /**
     * Força reset do circuit breaker (admin)
     */
    public function forceReset(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetCounters();
        
        $this->logger->info("Circuit breaker FORCE RESET for {$this->serviceName}");
    }
}
