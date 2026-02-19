<?php

/**
 * Migration: Create seo_optimization_history table
 *
 * This table stores snapshots/diffs for SEO optimizations (title/description/etc)
 * and supports rollback via App\Services\SEO\VersioningService.
 */

use App\Database;

$db = Database::getInstance();

// If the canonical SQL migration (030_create_seo_intelligence_tables.sql) already created this table,
// keep this migration as a safe no-op to avoid conflicting schemas.
$exists = false;
try {
    $row = $db->query("SHOW TABLES LIKE 'seo_optimization_history'")->fetch();
    $exists = !empty($row);
} catch (Throwable $e) {
    $exists = false;
}

if (!$exists) {
    // Schema aligned with database/migrations/030_create_seo_intelligence_tables.sql
    $db->exec("
    CREATE TABLE IF NOT EXISTS seo_optimization_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        item_id VARCHAR(50) NOT NULL,
        account_id INT NOT NULL,
        version INT UNSIGNED NOT NULL COMMENT 'Version number for this item',

        change_type ENUM('title', 'description', 'attributes', 'images', 'price', 'category', 'bulk') NOT NULL,
        changed_by ENUM('user', 'ai', 'automation') NOT NULL,
        user_id INT NULL COMMENT 'User who made the change (if applicable)',

        before_data JSON NOT NULL COMMENT 'State before change',
        after_data JSON NOT NULL COMMENT 'State after change',
        diff TEXT NULL COMMENT 'Human-readable diff description',

        estimated_impact VARCHAR(255) NULL COMMENT 'Estimated impact description',
        actual_impact JSON NULL COMMENT 'Measured impact after change (visits, conversions, etc.)',

        can_rollback BOOLEAN DEFAULT TRUE,
        rolled_back BOOLEAN DEFAULT FALSE,
        rolled_back_at TIMESTAMP NULL,
        rollback_reason TEXT NULL,

        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        snapshot_path VARCHAR(500) NULL COMMENT 'Path to full JSON snapshot file',

        INDEX idx_item_id (item_id),
        INDEX idx_account_id (account_id),
        INDEX idx_version (version),
        INDEX idx_change_type (change_type),
        INDEX idx_changed_by (changed_by),
        INDEX idx_applied_at (applied_at),
        INDEX idx_can_rollback (can_rollback),
        UNIQUE KEY unique_item_version (item_id, version)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Table 'seo_optimization_history' created (fallback migration).\n";
} else {
    echo "ℹ️ Table 'seo_optimization_history' already exists; skipping.\n";
}

// Best-effort quick verification
try {
    $db->query("SELECT 1 FROM seo_optimization_history LIMIT 1");
} catch (Throwable $e) {
    echo "⚠️ Could not verify seo_optimization_history: " . $e->getMessage() . "\n";
}
