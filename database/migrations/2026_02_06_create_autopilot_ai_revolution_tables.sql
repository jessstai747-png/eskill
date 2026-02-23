-- AutoPilot AI Revolution Database Schema
-- Database migrations for the autonomous optimization system

-- Main AutoPilot execution sessions
CREATE TABLE IF NOT EXISTS autopilot_execution_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    execution_id VARCHAR(100) UNIQUE NOT NULL,
    account_id VARCHAR(100),
    decisions_data JSON,
    status ENUM('running', 'completed', 'completed_with_errors', 'failed', 'paused') DEFAULT 'running',
    success_count INT DEFAULT 0,
    total_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    INDEX idx_account_id (account_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Individual execution records for each item
CREATE TABLE IF NOT EXISTS autopilot_execution_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id VARCHAR(100) UNIQUE NOT NULL,
    execution_id VARCHAR(100) NOT NULL,
    item_id VARCHAR(100) NOT NULL,
    decision_data JSON,
    execution_results JSON,
    baseline_metrics JSON,
    status ENUM('executing', 'completed', 'failed', 'rolled_back') DEFAULT 'executing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (execution_id) REFERENCES autopilot_execution_sessions(execution_id) ON DELETE CASCADE,
    INDEX idx_execution_id (execution_id),
    INDEX idx_item_id (item_id),
    INDEX idx_status (status)
);

-- Performance monitoring schedule
CREATE TABLE IF NOT EXISTS performance_monitoring_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL,
    execution_id VARCHAR(100) NOT NULL,
    check_time TIMESTAMP NOT NULL,
    baseline_metrics JSON,
    actual_metrics JSON,
    performance_delta JSON,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (execution_id) REFERENCES autopilot_execution_sessions(execution_id) ON DELETE CASCADE,
    INDEX idx_item_check_time (item_id, check_time),
    INDEX idx_status (status),
    INDEX idx_check_time (check_time)
);

-- Optimization change log
CREATE TABLE IF NOT EXISTS optimization_change_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    change_type ENUM('manual', 'autopilot', 'scheduled') DEFAULT 'autopilot',
    execution_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (execution_id) REFERENCES autopilot_execution_sessions(execution_id) ON DELETE SET NULL,
    INDEX idx_item_id (item_id),
    INDEX idx_field_name (field_name),
    INDEX idx_change_type (change_type),
    INDEX idx_created_at (created_at)
);

-- AutoPilot execution errors
CREATE TABLE IF NOT EXISTS autopilot_execution_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL,
    execution_id VARCHAR(100) NOT NULL,
    record_id VARCHAR(100),
    error_message TEXT NOT NULL,
    error_class VARCHAR(100),
    stack_trace TEXT,
    recovery_attempted BOOLEAN DEFAULT FALSE,
    recovery_successful BOOLEAN DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (execution_id) REFERENCES autopilot_execution_sessions(execution_id) ON DELETE CASCADE,
    FOREIGN KEY (record_id) REFERENCES autopilot_execution_records(record_id) ON DELETE SET NULL,
    INDEX idx_execution_id (execution_id),
    INDEX idx_error_class (error_class),
    INDEX idx_created_at (created_at)
);

-- Scheduled optimizations
CREATE TABLE IF NOT EXISTS scheduled_optimizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL,
    schedule_data JSON,
    status ENUM('scheduled', 'executing', 'completed', 'failed', 'cancelled') DEFAULT 'scheduled',
    scheduled_time TIMESTAMP NOT NULL,
    execution_time TIMESTAMP NULL,
    execution_id VARCHAR(100),
    account_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (execution_id) REFERENCES autopilot_execution_sessions(execution_id) ON DELETE SET NULL,
    INDEX idx_item_status (item_id, status),
    INDEX idx_scheduled_time (scheduled_time),
    INDEX idx_account_id (account_id)
);

-- AutoPilot cycles
CREATE TABLE IF NOT EXISTS autopilot_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id VARCHAR(100) UNIQUE NOT NULL,
    account_id VARCHAR(100),
    status ENUM('running', 'completed', 'failed', 'paused') DEFAULT 'running',
    decisions_made INT DEFAULT 0,
    executions_attempted INT DEFAULT 0,
    executions_successful INT DEFAULT 0,
    summary_data JSON,
    market_context JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_account_id (account_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
);

-- AutoPilot configuration
CREATE TABLE IF NOT EXISTS autopilot_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100) UNIQUE NOT NULL,
    config_data JSON NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_active (active)
);

-- AutoPilot status
CREATE TABLE IF NOT EXISTS autopilot_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('active', 'paused', 'disabled', 'maintenance') DEFAULT 'active',
    last_cycle_at TIMESTAMP NULL,
    next_cycle_at TIMESTAMP NULL,
    total_cycles INT DEFAULT 0,
    success_rate DECIMAL(5,4) DEFAULT 0.0000,
    total_optimizations INT DEFAULT 0,
    successful_optimizations INT DEFAULT 0,
    average_roi DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_status (status)
);

-- Learning insights
CREATE TABLE IF NOT EXISTS learning_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id VARCHAR(100) NOT NULL,
    account_id VARCHAR(100),
    insights_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cycle_id) REFERENCES autopilot_cycles(cycle_id) ON DELETE CASCADE,
    INDEX idx_cycle_id (cycle_id),
    INDEX idx_account_id (account_id),
    INDEX idx_created_at (created_at)
);

-- Optimization strategies
CREATE TABLE IF NOT EXISTS optimization_strategies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    strategy_name VARCHAR(200) NOT NULL,
    strategy_type ENUM('title', 'description', 'price', 'timing', 'comprehensive') NOT NULL,
    strategy_data JSON NOT NULL,
    success_rate DECIMAL(5,4) DEFAULT 0.0000,
    avg_roi DECIMAL(10,2) DEFAULT 0.00,
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_type (account_id, strategy_type),
    INDEX idx_success_rate (success_rate),
    INDEX idx_active (active)
);

-- Category insights
CREATE TABLE IF NOT EXISTS category_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    category_id VARCHAR(50) NOT NULL,
    category_name VARCHAR(200),
    total_optimizations INT DEFAULT 0,
    success_rate DECIMAL(5,4) DEFAULT 0.0000,
    avg_impact DECIMAL(5,4) DEFAULT 0.0000,
    best_strategies JSON,
    seasonal_patterns JSON,
    competition_level VARCHAR(20) DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_category (account_id, category_id),
    INDEX idx_success_rate (success_rate)
);

-- Market intelligence
CREATE TABLE IF NOT EXISTS market_intelligence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    condition_type VARCHAR(100) NOT NULL,
    condition_data JSON NOT NULL,
    avg_performance DECIMAL(5,4) DEFAULT 0.0000,
    sample_size INT DEFAULT 0,
    confidence DECIMAL(5,4) DEFAULT 0.0000,
    trend_direction ENUM('increasing', 'decreasing', 'stable') DEFAULT 'stable',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_condition (account_id, condition_type),
    INDEX idx_confidence (confidence),
    INDEX idx_trend_direction (trend_direction)
);

-- Adaptation rules
CREATE TABLE IF NOT EXISTS adaptation_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100) UNIQUE NOT NULL,
    rules_data JSON NOT NULL,
    version INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_version (version)
);

-- Model performance tracking
CREATE TABLE IF NOT EXISTS model_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    model_name VARCHAR(100) NOT NULL,
    model_version VARCHAR(50),
    accuracy_improvement DECIMAL(5,4) DEFAULT 0.0000,
    training_samples INT DEFAULT 0,
    validation_accuracy DECIMAL(5,4) DEFAULT 0.0000,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_model (account_id, model_name),
    INDEX idx_last_updated (last_updated)
);

-- Active optimizations tracking
CREATE TABLE IF NOT EXISTS active_optimizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    item_id VARCHAR(100) NOT NULL,
    optimization_type VARCHAR(50) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estimated_completion TIMESTAMP NULL,
    current_status ENUM('running', 'monitoring', 'completed', 'failed') DEFAULT 'running',
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    estimated_impact DECIMAL(10,2) DEFAULT 0.00,
    actual_impact DECIMAL(10,2) DEFAULT NULL,
    INDEX idx_account_item (account_id, item_id),
    INDEX idx_status (current_status),
    INDEX idx_started_at (started_at)
);

-- Performance metrics (enhanced for AutoPilot)
CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL,
    account_id VARCHAR(100),
    daily_views INT DEFAULT 0,
    daily_interactions INT DEFAULT 0,
    conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
    daily_sales INT DEFAULT 0,
    average_position DECIMAL(8,2) DEFAULT 0.00,
    click_rate DECIMAL(5,4) DEFAULT 0.0000,
    seo_score DECIMAL(5,4) DEFAULT 0.0000,
    revenue DECIMAL(12,2) DEFAULT 0.00,
    profit_margin DECIMAL(5,4) DEFAULT 0.0000,
    competitor_position INT DEFAULT 0,
    market_share DECIMAL(5,4) DEFAULT 0.0000,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_id (item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_last_updated (last_updated)
);

-- Insert default AutoPilot configuration
INSERT IGNORE INTO autopilot_config (account_id, config_data) VALUES 
('default', JSON_OBJECT(
    'risk_profile', 'moderate',
    'optimization_frequency', 'daily',
    'max_daily_optimizations', 50,
    'confidence_threshold', 0.7,
    'auto_execution_enabled', true,
    'learning_enabled', true,
    'notification_preferences', JSON_OBJECT(
        'email_notifications', true,
        'slack_notifications', false,
        'success_threshold', 0.8,
        'failure_threshold', 0.3
    )
));

-- Insert default adaptation rules
INSERT IGNORE INTO adaptation_rules (account_id, rules_data) VALUES 
('default', JSON_OBJECT(
    'min_confidence', 0.7,
    'min_roi', 0.5,
    'max_risk', 0.3,
    'learning_rate', 0.1,
    'adaptation_threshold', 0.05,
    'max_execution_time_minutes', 30,
    'pause_on_failure_rate', 0.3,
    'min_sample_size_for_learning', 10
));

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_execution_sessions_composite ON autopilot_execution_sessions(account_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_execution_records_composite ON autopilot_execution_records(execution_id, status, item_id);
CREATE INDEX IF NOT EXISTS idx_performance_metrics_composite ON performance_metrics(account_id, last_updated, seo_score);
CREATE INDEX IF NOT EXISTS idx_optimization_strategies_composite ON optimization_strategies(account_id, strategy_type, success_rate);

-- Create views for common queries
CREATE OR REPLACE VIEW autopilot_summary AS
SELECT 
    a.account_id,
    COUNT(DISTINCT ae.execution_id) as total_executions,
    COUNT(DISTINCT ac.cycle_id) as total_cycles,
    AVG(ae.success_count) / AVG(ae.total_count) as avg_success_rate,
    SUM(ae.total_count) as total_optimizations,
    MAX(ae.created_at) as last_execution,
    a.status as current_status
FROM autopilot_status a
LEFT JOIN autopilot_execution_sessions ae ON a.account_id = ae.account_id
LEFT JOIN autopilot_cycles ac ON a.account_id = ac.account_id
GROUP BY a.account_id, a.status;

CREATE OR REPLACE VIEW learning_performance AS
SELECT 
    li.account_id,
    COUNT(li.id) as total_learning_sessions,
    AVG(JSON_EXTRACT(li.insights_data, '$.successful_patterns_count')) as avg_patterns_found,
    AVG(JSON_EXTRACT(li.insights_data, '$.failure_insights_count')) as avg_failures_analyzed,
    MAX(li.created_at) as last_learning_session
FROM learning_insights li
GROUP BY li.account_id;

-- Triggers for automatic updates
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_autopilot_status_on_execution 
AFTER INSERT ON autopilot_execution_sessions
FOR EACH ROW
BEGIN
    INSERT INTO autopilot_status (account_id, last_cycle_at, total_cycles)
    VALUES (NEW.account_id, NEW.started_at, 1)
    ON DUPLICATE KEY UPDATE 
        last_cycle_at = NEW.started_at,
        total_cycles = total_cycles + 1,
        updated_at = CURRENT_TIMESTAMP;
END//

CREATE TRIGGER IF NOT EXISTS update_performance_metrics_timestamp
BEFORE UPDATE ON performance_metrics
FOR EACH ROW
BEGIN
    SET NEW.last_updated = CURRENT_TIMESTAMP;
END//

DELIMITER ;