<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Sistema de Feature Flags para controlar funcionalidades do sistema
 */
class FeatureFlagService
{
    private \PDO $db;
    private array $cache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    /**
     * Cria tabela de feature flags se não existir
     */
    private function ensureTable(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS feature_flags (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    flag_name VARCHAR(100) NOT NULL UNIQUE,
                    is_enabled BOOLEAN DEFAULT TRUE,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_flag_name (flag_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Inserir flags padrão se não existirem
            $this->initializeDefaultFlags();
        } catch (\Exception $e) {
            log_error('Erro ao criar tabela feature_flags', [
                'service' => 'FeatureFlagService',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Inicializa flags padrão do sistema de clonagem
     */
    private function initializeDefaultFlags(): void
    {
        $defaultFlags = [
            'catalog_clone_enabled' => [
                'enabled' => true,
                'description' => 'Habilita/desabilita todo o sistema de clonagem de catálogo'
            ],
            'catalog_clone_batch_enabled' => [
                'enabled' => true,
                'description' => 'Habilita/desabilita clonagem em lote'
            ],
            'catalog_clone_smart_pricing' => [
                'enabled' => true,
                'description' => 'Habilita estratégias inteligentes de preço'
            ],
            'catalog_clone_duplicate_check' => [
                'enabled' => true,
                'description' => 'Habilita verificação de duplicidade'
            ],
            'catalog_clone_auto_retry' => [
                'enabled' => true,
                'description' => 'Habilita retry automático para falhas temporárias'
            ],
            'catalog_clone_monitoring' => [
                'enabled' => true,
                'description' => 'Habilita monitoramento e alertas'
            ],
            'catalog_clone_rate_limit' => [
                'enabled' => true,
                'description' => 'Habilita controle de rate limiting'
            ]
        ];

        foreach ($defaultFlags as $flagName => $config) {
            $this->createFlagIfNotExists($flagName, $config['enabled'], $config['description']);
        }
    }

    /**
     * Cria flag se não existir
     */
    private function createFlagIfNotExists(string $flagName, bool $enabled, string $description): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO feature_flags (flag_name, is_enabled, description)
                VALUES (:flag_name, :enabled, :description)
            ");
            $stmt->execute([
                'flag_name' => $flagName,
                'enabled' => $enabled,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao criar flag', [
                'service' => 'FeatureFlagService',
                'flag_name' => $flagName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verifica se uma feature está habilitada
     */
    public function isEnabled(string $flagName): bool
    {
        // Cache para evitar queries repetidas
        if (isset($this->cache[$flagName])) {
            return $this->cache[$flagName];
        }

        try {
            $stmt = $this->db->prepare("SELECT is_enabled FROM feature_flags WHERE flag_name = :flag_name");
            $stmt->execute(['flag_name' => $flagName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $enabled = $result ? (bool)$result['is_enabled'] : false;
            $this->cache[$flagName] = $enabled;

            return $enabled;
        } catch (\Exception $e) {
            log_warning('Erro ao verificar flag', [
                'service' => 'FeatureFlagService',
                'flag_name' => $flagName,
                'error' => $e->getMessage(),
            ]);
            return false; // Fail-safe: desabilitado em caso de erro
        }
    }

    /**
     * Habilita/desabilita uma feature
     */
    public function setEnabled(string $flagName, bool $enabled): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE feature_flags 
                SET is_enabled = :enabled, updated_at = CURRENT_TIMESTAMP
                WHERE flag_name = :flag_name
            ");
            $result = $stmt->execute([
                'flag_name' => $flagName,
                'enabled' => $enabled
            ]);

            if ($result) {
                $this->cache[$flagName] = $enabled;
                $this->logFlagChange($flagName, $enabled);
            }

            return $result;
        } catch (\Exception $e) {
            log_error('Erro ao atualizar flag', [
                'service' => 'FeatureFlagService',
                'flag_name' => $flagName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Lista todas as flags
     */
    public function getAllFlags(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT flag_name, is_enabled, description, updated_at
                FROM feature_flags
                ORDER BY flag_name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            log_warning('Erro ao listar flags', [
                'service' => 'FeatureFlagService',
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Cria nova flag
     */
    public function createFlag(string $flagName, bool $enabled = false, string $description = ''): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO feature_flags (flag_name, is_enabled, description)
                VALUES (:flag_name, :enabled, :description)
            ");
            return $stmt->execute([
                'flag_name' => $flagName,
                'enabled' => $enabled,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao criar flag', [
                'service' => 'FeatureFlagService',
                'flag_name' => $flagName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove flag
     */
    public function deleteFlag(string $flagName): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM feature_flags WHERE flag_name = :flag_name");
            $result = $stmt->execute(['flag_name' => $flagName]);
            
            if ($result) {
                unset($this->cache[$flagName]);
            }
            
            return $result;
        } catch (\Exception $e) {
            log_error('Erro ao deletar flag', [
                'service' => 'FeatureFlagService',
                'flag_name' => $flagName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Limpa cache de flags
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Verifica se clonagem está habilitada globalmente
     */
    public function isCloningEnabled(): bool
    {
        return $this->isEnabled('catalog_clone_enabled');
    }

    /**
     * Verifica se clonagem em lote está habilitada
     */
    public function isBatchCloningEnabled(): bool
    {
        return $this->isCloningEnabled() && $this->isEnabled('catalog_clone_batch_enabled');
    }

    /**
     * Verifica se precificação inteligente está habilitada
     */
    public function isSmartPricingEnabled(): bool
    {
        return $this->isCloningEnabled() && $this->isEnabled('catalog_clone_smart_pricing');
    }

    /**
     * Verifica se verificação de duplicidade está habilitada
     */
    public function isDuplicateCheckEnabled(): bool
    {
        return $this->isCloningEnabled() && $this->isEnabled('catalog_clone_duplicate_check');
    }

    /**
     * Verifica se retry automático está habilitado
     */
    public function isAutoRetryEnabled(): bool
    {
        return $this->isCloningEnabled() && $this->isEnabled('catalog_clone_auto_retry');
    }

    /**
     * Verifica se monitoramento está habilitado
     */
    public function isMonitoringEnabled(): bool
    {
        return $this->isEnabled('catalog_clone_monitoring');
    }

    /**
     * Verifica se rate limiting está habilitado
     */
    public function isRateLimitEnabled(): bool
    {
        return $this->isCloningEnabled() && $this->isEnabled('catalog_clone_rate_limit');
    }

    /**
     * Log de mudanças em flags
     */
    private function logFlagChange(string $flagName, bool $enabled): void
    {
        $action = $enabled ? 'ENABLED' : 'DISABLED';
        log_info('Feature flag alterada', [
            'service' => 'FeatureFlagService',
            'flag_name' => $flagName,
            'action' => $action,
        ]);
    }

    /**
     * Desabilita emergencialmente todo o sistema de clonagem
     */
    public function emergencyDisable(): bool
    {
        $flags = [
            'catalog_clone_enabled',
            'catalog_clone_batch_enabled',
            'catalog_clone_smart_pricing'
        ];

        $success = true;
        foreach ($flags as $flag) {
            if (!$this->setEnabled($flag, false)) {
                $success = false;
            }
        }

        if ($success) {
            log_warning('EMERGÊNCIA: Sistema de clonagem desabilitado', [
                'service' => 'FeatureFlagService',
                'action' => 'emergency_disable',
            ]);
        }

        return $success;
    }

    /**
     * Reabilita sistema após emergência
     */
    public function emergencyRestore(): bool
    {
        $flags = [
            'catalog_clone_enabled' => true,
            'catalog_clone_batch_enabled' => true,
            'catalog_clone_smart_pricing' => true
        ];

        $success = true;
        foreach ($flags as $flag => $enabled) {
            if (!$this->setEnabled($flag, $enabled)) {
                $success = false;
            }
        }

        if ($success) {
            log_info('Sistema de clonagem reabilitado', [
                'service' => 'FeatureFlagService',
                'action' => 'emergency_restore',
            ]);
        }

        return $success;
    }
}