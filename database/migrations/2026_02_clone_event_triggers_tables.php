<?php

/**
 * Migration: Clone Event Trigger Tables
 *
 * Cria tabelas para sistema de triggers de eventos:
 * - clone_event_triggers: Configurações de triggers
 * - clone_event_trigger_items: Itens monitorados
 * - clone_event_trigger_competitors: Concorrentes monitorados
 * - clone_event_trigger_logs: Histórico de eventos
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;

$db = Database::getInstance();

echo "=== Migration: Clone Event Trigger Tables ===\n\n";

try {
    // Nota: DDL (CREATE TABLE) causa commit implícito no MySQL,
    // então não usamos transação explícita aqui

    // 1. Tabela de triggers de eventos
    echo "Criando tabela clone_event_triggers...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS clone_event_triggers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_id VARCHAR(20) NOT NULL UNIQUE,
            account_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            event_type ENUM('new_items', 'price_drop', 'stock_available', 'competitor_out') NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_value VARCHAR(255) NOT NULL,
            conditions JSON NULL,
            actions JSON NULL,
            is_active TINYINT(1) DEFAULT 1,
            check_interval_minutes INT UNSIGNED DEFAULT 30,
            last_check_at DATETIME NULL,
            total_events_detected INT UNSIGNED DEFAULT 0,
            total_actions_executed INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,

            INDEX idx_account_active (account_id, is_active),
            INDEX idx_event_type (event_type),
            INDEX idx_last_check (last_check_at),
            INDEX idx_trigger_id (trigger_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ clone_event_triggers criada\n";

    // 2. Tabela de itens monitorados
    echo "Criando tabela clone_event_trigger_items...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS clone_event_trigger_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_id VARCHAR(20) NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            first_seen_at DATETIME NOT NULL,
            last_check_at DATETIME NULL,
            last_price DECIMAL(15,2) NULL,
            has_stock TINYINT(1) DEFAULT 1,
            metadata JSON NULL,

            UNIQUE KEY uk_trigger_item (trigger_id, item_id),
            INDEX idx_trigger_id (trigger_id),
            INDEX idx_item_id (item_id),
            INDEX idx_last_check (last_check_at),

            FOREIGN KEY (trigger_id) REFERENCES clone_event_triggers(trigger_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ clone_event_trigger_items criada\n";

    // 3. Tabela de concorrentes monitorados
    echo "Criando tabela clone_event_trigger_competitors...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS clone_event_trigger_competitors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_id VARCHAR(20) NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            seller_id VARCHAR(50) NULL,
            is_active TINYINT(1) DEFAULT 1,
            first_seen_at DATETIME NOT NULL,
            inactive_since DATETIME NULL,
            last_price DECIMAL(15,2) NULL,
            last_quantity INT UNSIGNED NULL,

            UNIQUE KEY uk_trigger_competitor (trigger_id, item_id),
            INDEX idx_trigger_id (trigger_id),
            INDEX idx_is_active (is_active),
            INDEX idx_seller_id (seller_id),

            FOREIGN KEY (trigger_id) REFERENCES clone_event_triggers(trigger_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ clone_event_trigger_competitors criada\n";

    // 4. Tabela de logs de eventos
    echo "Criando tabela clone_event_trigger_logs...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS clone_event_trigger_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_id VARCHAR(20) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            item_id VARCHAR(50) NULL,
            event_data JSON NULL,
            action_result JSON NULL,
            created_at DATETIME NOT NULL,

            INDEX idx_trigger_id (trigger_id),
            INDEX idx_event_type (event_type),
            INDEX idx_created_at (created_at),
            INDEX idx_item_id (item_id),

            FOREIGN KEY (trigger_id) REFERENCES clone_event_triggers(trigger_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ clone_event_trigger_logs criada\n";

    // 5. Atualizar tabela clone_schedules para suportar triggers
    echo "Atualizando tabela clone_schedules...\n";

    // Verificar se colunas existem antes de adicionar
    $stmt = $db->query("SHOW COLUMNS FROM clone_schedules LIKE 'trigger_id'");
    $hasTrigId = $stmt->rowCount() > 0;
    $stmt = $db->query("SHOW COLUMNS FROM clone_schedules LIKE 'trigger_type'");
    $hasTrigType = $stmt->rowCount() > 0;

    if (!$hasTrigId && !$hasTrigType) {
        $db->exec("
            ALTER TABLE clone_schedules
            ADD COLUMN trigger_id VARCHAR(20) NULL AFTER template_id,
            ADD COLUMN trigger_type ENUM('scheduled', 'new_items', 'price_drop', 'stock_available', 'competitor_out') DEFAULT 'scheduled' AFTER trigger_id,
            ADD INDEX idx_trigger_id (trigger_id)
        ");
        echo "  ✓ Colunas de trigger adicionadas\n";
    } elseif (!$hasTrigId) {
        try {
            $db->exec("ALTER TABLE clone_schedules ADD COLUMN trigger_id VARCHAR(20) NULL AFTER template_id, ADD INDEX idx_trigger_id (trigger_id)");
            echo "  ✓ trigger_id adicionada\n";
        } catch (\PDOException $e) {
            echo "  - trigger_id: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  - Colunas já existem\n";
    }

    // 6. Tabela de cache de trends para gráficos
    echo "Criando tabela clone_trend_cache...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS clone_trend_cache (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            account_id INT UNSIGNED NOT NULL,
            chart_type VARCHAR(50) NOT NULL,
            cache_key VARCHAR(100) NOT NULL,
            chart_data JSON NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,

            UNIQUE KEY uk_account_chart_key (account_id, chart_type, cache_key),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ clone_trend_cache criada\n";

    // DDL auto-commit no MySQL, sem necessidade de commit explícito

    echo "\n=== Migration concluída com sucesso! ===\n";
    echo "\nTabelas criadas:\n";
    echo "  - clone_event_triggers\n";
    echo "  - clone_event_trigger_items\n";
    echo "  - clone_event_trigger_competitors\n";
    echo "  - clone_event_trigger_logs\n";
    echo "  - clone_trend_cache\n";
    echo "\nPróximos passos:\n";
    echo "  1. Adicionar worker ao crontab:\n";
    echo "     */5 * * * * php bin/clone-event-trigger-worker.php --once\n";
    echo "  2. Configurar triggers via API ou dashboard\n";
} catch (\Exception $e) {
    // DDL auto-commit - rollback não é possível para DDL
    // Tolerar erros de "already exists" (coluna/tabela/índice já criado)
    $errCode = $e instanceof \PDOException ? ($e->errorInfo[1] ?? 0) : 0;
    if (in_array((int)$errCode, [1060, 1061, 1050], true)) {
        echo "\n⚠ Aviso tolerável: " . $e->getMessage() . "\n";
        echo "=== Migration concluída com avisos ===\n";
    } else {
        echo "\n❌ Erro na migration: " . $e->getMessage() . "\n";
        throw $e;
    }
    throw $e;
}
