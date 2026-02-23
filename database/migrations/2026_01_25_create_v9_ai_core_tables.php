<?php

/**
 * 🗄️ V9 AI Core Migration
 *
 * Creates tables for:
 * - AI Decisions (DecisionEngine)
 * - Learning Pipeline (outcomes, models)
 * - Automation Orchestrator (workflows, tasks, states)
 */

use App\Database;

// Get database connection
$db = Database::getInstance();

echo "🚀 Starting V9 AI Core Migration...\n\n";

try {
    // ===========================================
    // AI DECISIONS TABLE
    // ===========================================
    echo "📊 Creating ai_decisions table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_decisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            decision_type VARCHAR(50) NOT NULL,
            context_json JSON,
            decision_json JSON,
            confidence DECIMAL(5,4) DEFAULT 0.5,
            status ENUM('pending', 'approved', 'executed', 'rejected', 'failed') DEFAULT 'pending',
            rejection_reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            executed_at DATETIME,
            INDEX idx_account_status (account_id, status),
            INDEX idx_account_type (account_id, decision_type),
            INDEX idx_created (created_at),
            INDEX idx_confidence (confidence DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ ai_decisions created\n\n";

    // ===========================================
    // LEARNING OUTCOMES TABLE
    // ===========================================
    echo "📚 Creating learning_outcomes table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS learning_outcomes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            learning_type VARCHAR(50) NOT NULL,
            outcome_data JSON,
            success_score DECIMAL(5,4) DEFAULT 0.5,
            processed TINYINT(1) DEFAULT 0,
            processed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_type (account_id, learning_type),
            INDEX idx_processed (processed, learning_type),
            INDEX idx_success (success_score DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ learning_outcomes created\n\n";

    // ===========================================
    // LEARNING MODELS TABLE
    // ===========================================
    echo "🧠 Creating learning_models table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS learning_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            model_type VARCHAR(50) NOT NULL,
            model_data JSON,
            accuracy DECIMAL(5,4) DEFAULT 0.5,
            trained_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_type (account_id, model_type),
            INDEX idx_accuracy (accuracy DESC),
            INDEX idx_trained (trained_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ learning_models created\n\n";

    // ===========================================
    // AUTOMATION WORKFLOWS TABLE
    // ===========================================
    echo "🎭 Creating automation_workflows table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS automation_workflows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            tasks_json JSON,
            graph_json JSON,
            options_json JSON,
            status ENUM('pending', 'running', 'paused', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            completed_at DATETIME,
            INDEX idx_account_status (account_id, status),
            INDEX idx_created (created_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ automation_workflows created\n\n";

    // ===========================================
    // WORKFLOW TASKS TABLE
    // ===========================================
    echo "📋 Creating workflow_tasks table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS workflow_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            workflow_id INT NOT NULL,
            task_id VARCHAR(100) NOT NULL,
            task_type VARCHAR(50) NOT NULL,
            task_data JSON,
            dependencies JSON,
            result_json JSON,
            status ENUM('pending', 'running', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            INDEX idx_workflow (workflow_id),
            INDEX idx_status (status),
            INDEX idx_workflow_status (workflow_id, status),
            FOREIGN KEY (workflow_id) REFERENCES automation_workflows(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ workflow_tasks created\n\n";

    // ===========================================
    // TASK STATES TABLE (for rollback)
    // ===========================================
    echo "💾 Creating task_states table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS task_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_db_id INT NOT NULL,
            before_state JSON,
            after_state JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task (task_db_id),
            FOREIGN KEY (task_db_id) REFERENCES workflow_tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ task_states created\n\n";

    // ===========================================
    // SSE CONNECTIONS TABLE
    // ===========================================
    echo "📡 Creating sse_connections table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS sse_connections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            connection_id VARCHAR(100) NOT NULL,
            stream_type VARCHAR(50) NOT NULL,
            client_ip VARCHAR(45),
            connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_heartbeat DATETIME,
            disconnected_at DATETIME,
            INDEX idx_account (account_id),
            INDEX idx_active (disconnected_at),
            UNIQUE KEY unique_connection (connection_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ sse_connections created\n\n";

    // ===========================================
    // AI METRICS TABLE
    // ===========================================
    echo "📈 Creating ai_metrics table...\n";

    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id INT NOT NULL,
            metric_type VARCHAR(50) NOT NULL,
            metric_name VARCHAR(100) NOT NULL,
            metric_value DECIMAL(15,4) NOT NULL,
            dimensions JSON,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_type (account_id, metric_type),
            INDEX idx_recorded (recorded_at DESC),
            INDEX idx_metric_name (metric_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ ai_metrics created\n\n";

    echo "✨ V9 AI Core Migration completed successfully!\n";
    echo "   Tables created: 7\n";
    echo "   - ai_decisions\n";
    echo "   - learning_outcomes\n";
    echo "   - learning_models\n";
    echo "   - automation_workflows\n";
    echo "   - workflow_tasks\n";
    echo "   - task_states\n";
    echo "   - sse_connections\n";
    echo "   - ai_metrics\n";
} catch (\PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
