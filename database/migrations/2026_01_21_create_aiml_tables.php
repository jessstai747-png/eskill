<?php

/**
 * Migration: Create AI/ML Tables
 *
 * Tables:
 * - category_learning: Stores learned patterns by category
 * - keyword_classifications: Stores keyword classifications with cache
 * - keyword_trends: Stores historical trend data
 */

use App\Database;

$db = Database::getInstance();

// ========================================
// 📚 Category Learning Table
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS category_learning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(50) NOT NULL UNIQUE,
    patterns_json JSON NOT NULL,
    items_analyzed INT DEFAULT 0,
    learned_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX idx_category_id (category_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'category_learning' created successfully\n";

// ========================================
// 🏷️ Keyword Classifications Table
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS keyword_classifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword_hash VARCHAR(32) NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    category_context VARCHAR(100) NULL,
    type ENUM('CORE', 'BRANDED', 'SUPPORT', 'MODIFIER', 'LONG_TAIL') NOT NULL DEFAULT 'CORE',
    weight DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    confidence DECIMAL(3,2) NOT NULL DEFAULT 0.50,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,

    UNIQUE KEY uk_keyword_hash (keyword_hash),
    INDEX idx_keyword (keyword(100)),
    INDEX idx_type (type),
    INDEX idx_confidence (confidence),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'keyword_classifications' created successfully\n";

// ========================================
// 📈 Keyword Trends Table
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS keyword_trends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword_hash VARCHAR(32) NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    total_results INT DEFAULT 0,
    avg_price DECIMAL(10,2) DEFAULT 0,
    trend_score DECIMAL(3,2) DEFAULT 0.50,
    trend_direction ENUM('up', 'down', 'stable', 'unknown') DEFAULT 'unknown',
    recorded_at DATETIME NOT NULL,

    INDEX idx_keyword_hash (keyword_hash),
    INDEX idx_keyword (keyword(100)),
    INDEX idx_trend_score (trend_score),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'keyword_trends' created successfully\n";

// ========================================
// 📊 SEO Analysis Cache Table (if not exists)
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS seo_analysis_cache (
    item_id VARCHAR(64) NOT NULL,
    account_id INT NOT NULL,
    category_id VARCHAR(32) NULL,
    overall_score DECIMAL(6,2) DEFAULT 0,
    strategies_json LONGTEXT NULL,
    suggestions_json LONGTEXT NULL,
    title_analysis_json LONGTEXT NULL,
    description_analysis_json LONGTEXT NULL,
    item_title VARCHAR(255) NULL,
    item_price DECIMAL(10,2) NULL,
    analysis_version VARCHAR(20) NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (item_id, account_id),
    INDEX idx_seo_cache_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'seo_analysis_cache' created successfully\n";

echo "\n🎉 All AI/ML tables created successfully!\n";
echo "\nTables created:\n";
echo "- category_learning: Learned patterns by Mercado Livre category\n";
echo "- keyword_classifications: Keyword type classifications (CORE/SUPPORT/etc)\n";
echo "- keyword_trends: Historical trend data for keywords\n";
echo "- seo_analysis_cache: SEO analysis cache with TTL\n";
