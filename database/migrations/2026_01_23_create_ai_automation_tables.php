<?php
declare(strict_types=1);

/**
 * Migration: Create AI Automation Tables
 * 
 * Tables for AI optimization system:
 * - ai_optimization_jobs: Job queue for background processing
 * - ai_optimization_history: History of optimizations
 * - optimization_schedules: Cron-based scheduling
 * - monitored_keywords: Keywords being tracked
 * - realtime_alerts: SSE alerts queue
 * - seo_scores: SEO score tracking
 */

use App\Database;

$db = Database::getInstance();

// ========================================
// 🔄 AI Optimization Jobs Queue
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS ai_optimization_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    payload JSON,
    result JSON,
    priority INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error TEXT,
    scheduled_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME NOT NULL,
    
    INDEX idx_status (status),
    INDEX idx_account_id (account_id),
    INDEX idx_priority (priority),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'ai_optimization_jobs' created successfully\n";

// ========================================
// 📊 AI Optimization History
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS ai_optimization_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    optimization_type VARCHAR(50) NOT NULL,
    before_score INT DEFAULT 0,
    after_score INT DEFAULT 0,
    changes_json JSON,
    created_at DATETIME NOT NULL,
    
    INDEX idx_account_id (account_id),
    INDEX idx_item_id (item_id),
    INDEX idx_optimization_type (optimization_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'ai_optimization_history' created successfully\n";

// ========================================
// ⏰ Optimization Schedules
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS optimization_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    schedule_type ENUM('hourly', 'daily', 'weekly', 'monthly') NOT NULL,
    config_json JSON,
    enabled TINYINT(1) DEFAULT 1,
    last_run_at DATETIME,
    run_count INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    
    INDEX idx_account_id (account_id),
    INDEX idx_enabled (enabled),
    INDEX idx_schedule_type (schedule_type),
    INDEX idx_last_run_at (last_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'optimization_schedules' created successfully\n";

// ========================================
// 👁️ Monitored Keywords
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS monitored_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    category_id VARCHAR(50),
    priority INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    last_trend_score DECIMAL(3,2),
    last_check_at DATETIME,
    created_at DATETIME NOT NULL,
    
    UNIQUE KEY uk_account_keyword (account_id, keyword),
    INDEX idx_account_id (account_id),
    INDEX idx_active (active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'monitored_keywords' created successfully\n";

// ========================================
// 🔔 Real-time Alerts
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS realtime_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    alert_type ENUM('keyword', 'score', 'competitor', 'system') NOT NULL,
    message VARCHAR(500) NOT NULL,
    data_json JSON,
    sent TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    created_at DATETIME NOT NULL,
    
    INDEX idx_account_id (account_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_sent (sent),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'realtime_alerts' created successfully\n";

// ========================================
// 📈 SEO Scores
// ========================================
$db->exec("
CREATE TABLE IF NOT EXISTS seo_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    overall_score INT DEFAULT 0,
    title_score INT DEFAULT 0,
    description_score INT DEFAULT 0,
    attributes_score INT DEFAULT 0,
    images_score INT DEFAULT 0,
    strategies_json JSON,
    analyzed_at DATETIME NOT NULL,
    
    UNIQUE KEY uk_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_overall_score (overall_score),
    INDEX idx_analyzed_at (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ Table 'seo_scores' created successfully\n";

echo "\n🎉 All AI Automation tables created successfully!\n";
echo "\nTables created:\n";
echo "- ai_optimization_jobs: Background job queue\n";
echo "- ai_optimization_history: Optimization history for ML training\n";
echo "- optimization_schedules: Cron-based scheduling\n";
echo "- monitored_keywords: Keywords being tracked for trends\n";
echo "- realtime_alerts: SSE alerts queue\n";
echo "- seo_scores: SEO score tracking per item\n";

/*
 * DOWN — Para reverter esta migration manualmente:
 *
//   $db->exec('DROP TABLE IF EXISTS ai_optimization_jobs;');
//   $db->exec('DROP TABLE IF EXISTS ai_optimization_history;');
//   $db->exec('DROP TABLE IF EXISTS optimization_schedules;');
//   $db->exec('DROP TABLE IF EXISTS monitored_keywords;');
//   $db->exec('DROP TABLE IF EXISTS realtime_alerts;');
//   $db->exec('DROP TABLE IF EXISTS seo_scores;');
 *
 * ATENÇÃO: Isso apaga dados permanentemente. Faça backup antes.
 */
