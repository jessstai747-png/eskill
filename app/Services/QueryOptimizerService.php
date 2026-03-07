<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class QueryOptimizerService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Analisa queries lentas e sugere otimizações
     */
    public function analyzeSlowQueries(): array
    {
        // Verificar se slow query log está habilitado
        $stmt = $this->db->query("SHOW VARIABLES LIKE 'slow_query_log'");
        $slowLogEnabled = $stmt->fetch();

        $analysis = [
            'slow_query_log_enabled' => ($slowLogEnabled['Value'] ?? 'OFF') === 'ON',
            'recommendations' => [],
        ];

        // Verificar índices faltantes em tabelas principais
        $tables = ['ml_orders', 'items', 'alerts', 'price_history', 'ml_accounts'];

        foreach ($tables as $table) {
            try {
                $indexes = $this->getTableIndexes($table);
                $recommendations = $this->suggestIndexes($table, $indexes);

                if (!empty($recommendations)) {
                    $analysis['recommendations'][$table] = $recommendations;
                }
            } catch (\Exception $e) {
                // Tabela pode não existir ainda
                continue;
            }
        }

        return $analysis;
    }

    /**
     * Obtém índices de uma tabela
     */
    private function getTableIndexes(string $table): array
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $this->db->query("SHOW INDEXES FROM `{$safeTable}`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sugere índices baseado na estrutura da tabela
     */
    private function suggestIndexes(string $table, array $existingIndexes): array
    {
        $suggestions = [];
        $indexNames = array_column($existingIndexes, 'Key_name');

        // Sugestões específicas por tabela
        switch ($table) {
            case 'ml_orders':
                if (!in_array('idx_status_date', $indexNames)) {
                    $suggestions[] = [
                        'index' => 'idx_status_date',
                        'columns' => ['status', 'date_created'],
                        'reason' => 'Melhora consultas filtradas por status e data',
                    ];
                }
                if (!in_array('idx_account_status', $indexNames)) {
                    $suggestions[] = [
                        'index' => 'idx_account_status',
                        'columns' => ['ml_account_id', 'status'],
                        'reason' => 'Melhora consultas por conta e status',
                    ];
                }
                break;

            case 'items':
                if (!in_array('idx_category_status', $indexNames)) {
                    $suggestions[] = [
                        'index' => 'idx_category_status',
                        'columns' => ['category_id', 'status'],
                        'reason' => 'Melhora consultas por categoria e status',
                    ];
                }
                break;

            case 'alerts':
                if (!in_array('idx_account_type', $indexNames)) {
                    $suggestions[] = [
                        'index' => 'idx_account_type',
                        'columns' => ['ml_account_id', 'type'],
                        'reason' => 'Melhora consultas de alertas por conta e tipo',
                    ];
                }
                break;

            case 'price_history':
                if (!in_array('idx_category_brand_date', $indexNames)) {
                    $suggestions[] = [
                        'index' => 'idx_category_brand_date',
                        'columns' => ['category_id', 'brand', 'date'],
                        'reason' => 'Melhora consultas de histórico por categoria, marca e data',
                    ];
                }
                break;
        }

        return $suggestions;
    }

    /**
     * Executa ANALYZE TABLE em todas as tabelas principais
     */
    public function analyzeTables(): array
    {
        $tables = ['ml_orders', 'items', 'alerts', 'price_history', 'ml_accounts', 'notifications', 'jobs'];
        $results = [];

        foreach ($tables as $table) {
            try {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                $this->db->exec("ANALYZE TABLE `{$safeTable}`");
                $results[$table] = ['status' => 'analyzed'];
            } catch (\Exception $e) {
                $results[$table] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Otimiza uma tabela específica
     */
    public function optimizeTable(string $table): array
    {
        // Security: validate table name against whitelist to prevent SQL injection
        $allowedTables = [
            'ml_orders',
            'items',
            'alerts',
            'price_history',
            'ml_accounts',
            'notifications',
            'jobs',
            'seo_analysis_cache',
            'categories',
            'orders',
            'products',
            'settings',
            'logs',
            'analytics',
            'rate_limits',
            'users',
            'account_health_history'
        ];

        if (!in_array($table, $allowedTables, true)) {
            return [
                'status' => 'error',
                'table' => $table,
                'error' => 'Table not in allowed list for optimization',
            ];
        }

        try {
            $safeName = str_replace('`', '``', $table);
            $this->db->exec("OPTIMIZE TABLE `{$safeName}`");
            return [
                'status' => 'success',
                'table' => $table,
                'message' => "Tabela {$table} otimizada com sucesso",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'table' => $table,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtém estatísticas de uso de índices
     */
    public function getIndexUsageStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    TABLE_NAME,
                    INDEX_NAME,
                    SEQ_IN_INDEX,
                    COLUMN_NAME,
                    CARDINALITY
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('ml_orders', 'items', 'alerts', 'price_history', 'ml_accounts')
                ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
