-- Advanced Database Schema for Reinforcement Learning and Predictive Intelligence
-- Supports Q-learning, Deep Q-Networks, Multi-Agent Systems, and Meta-Learning

-- Q-Table for Reinforcement Learning
CREATE TABLE IF NOT EXISTS q_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_hash VARCHAR(64) NOT NULL,
    action VARCHAR(100) NOT NULL,
    q_value DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
    account_id VARCHAR(100),
    update_count INT DEFAULT 1,
    last_reward DECIMAL(10,6) DEFAULT 0.000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_state_action (state_hash, action, account_id),
    INDEX idx_state_hash (state_hash),
    INDEX idx_account_id (account_id),
    INDEX idx_updated_at (updated_at)
);

-- Learning Experiences Replay Buffer
CREATE TABLE IF NOT EXISTS learning_experiences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    state_hash VARCHAR(64) NOT NULL,
    action VARCHAR(100) NOT NULL,
    reward DECIMAL(10,6) NOT NULL,
    new_state_hash VARCHAR(64) NOT NULL,
    execution_data JSON,
    episode_number INT,
    step_number INT,
    done BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_state (account_id, state_hash),
    INDEX idx_created_at (created_at),
    INDEX idx_episode_step (episode_number, step_number)
);

-- Neural Network Models Storage
CREATE TABLE IF NOT EXISTS neural_networks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    network_type ENUM('dqn', 'actor_critic', 'ppo', 'meta_learning') NOT NULL,
    network_name VARCHAR(200),
    config_data JSON NOT NULL,
    weights_data LONGBLOB,
    architecture JSON,
    training_parameters JSON,
    performance_metrics JSON,
    version INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    target_network_updated TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_account_type (account_id, network_type),
    INDEX idx_is_active (is_active),
    INDEX idx_updated_at (updated_at)
);

-- Training History and Metrics
CREATE TABLE IF NOT EXISTS training_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    network_id INT,
    training_step INT NOT NULL,
    epoch INT,
    loss_value DECIMAL(10,8) NOT NULL,
    accuracy DECIMAL(5,4),
    reward_avg DECIMAL(10,6),
    exploration_rate DECIMAL(5,4),
    learning_rate DECIMAL(8,6),
    batch_size INT,
    training_time_ms INT,
    validation_loss DECIMAL(10,8),
    gradient_norm DECIMAL(10,6),
    metrics_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (network_id) REFERENCES neural_networks(id) ON DELETE SET NULL,
    INDEX idx_account_step (account_id, training_step),
    INDEX idx_network_step (network_id, training_step),
    INDEX idx_created_at (created_at)
);

-- Replay Buffer for Deep Q-Learning
CREATE TABLE IF NOT EXISTS replay_buffer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    network_id INT,
    experience_data JSON NOT NULL,
    priority DECIMAL(10,6) DEFAULT 1.000000,
    sample_count INT DEFAULT 0,
    last_sampled TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (network_id) REFERENCES neural_networks(id) ON DELETE SET NULL,
    INDEX idx_account_priority (account_id, priority DESC),
    INDEX idx_created_at (created_at)
);

-- Curriculum Learning Progress
CREATE TABLE IF NOT EXISTS curriculum_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    level_key VARCHAR(50) NOT NULL,
    level_name VARCHAR(200) NOT NULL,
    complexity DECIMAL(3,2) NOT NULL,
    episodes_completed INT DEFAULT 0,
    successes INT DEFAULT 0,
    success_rate DECIMAL(5,4) DEFAULT 0.0000,
    mastered BOOLEAN DEFAULT FALSE,
    mastered_at TIMESTAMP NULL,
    current_objectives JSON,
    progress_metrics JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_account_level (account_id, level_key),
    INDEX idx_account_mastered (account_id, mastered),
    INDEX idx_success_rate (success_rate)
);

-- Multi-Agent Coordination
CREATE TABLE IF NOT EXISTS multi_agent_coordination (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    coordination_id VARCHAR(100) NOT NULL,
    agent_type VARCHAR(50) NOT NULL,
    agent_id VARCHAR(100),
    agent_action VARCHAR(200),
    agent_reward DECIMAL(10,6),
    coordination_score DECIMAL(5,4),
    conflict_detected BOOLEAN DEFAULT FALSE,
    conflict_resolution JSON,
    final_action VARCHAR(200),
    coordination_efficiency DECIMAL(5,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_coordination_id (coordination_id),
    INDEX idx_account_agent (account_id, agent_type),
    INDEX idx_created_at (created_at)
);

-- Meta-Learning Knowledge Base
CREATE TABLE IF NOT EXISTS meta_learning_knowledge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    scenario_hash VARCHAR(64) NOT NULL,
    meta_features JSON NOT NULL,
    extracted_patterns JSON,
    adaptation_strategies JSON,
    transferability_score DECIMAL(5,4),
    success_probability DECIMAL(5,4),
    application_count INT DEFAULT 0,
    success_count INT DEFAULT 0,
    last_applied TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_scenario_hash (scenario_hash),
    INDEX idx_transferability (transferability_score DESC),
    INDEX idx_success_probability (success_probability DESC)
);

-- Transfer Learning Experiences
CREATE TABLE IF NOT EXISTS transfer_learning_experiences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_account_id VARCHAR(100),
    target_account_id VARCHAR(100),
    source_category VARCHAR(50),
    target_category VARCHAR(50),
    source_knowledge JSON,
    target_characteristics JSON,
    transferable_patterns JSON,
    adaptation_quality DECIMAL(5,4),
    knowledge_gain DECIMAL(5,4),
    transfer_effectiveness DECIMAL(5,4),
    fine_tuning_episodes INT,
    final_performance DECIMAL(5,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_source_target (source_account_id, target_account_id),
    INDEX idx_categories (source_category, target_category),
    INDEX idx_effectiveness (transfer_effectiveness DESC)
);

-- Predictive Intelligence Cache
CREATE TABLE IF NOT EXISTS predictive_intelligence_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    prediction_type ENUM('market_trends', 'competitor_behavior', 'pricing_optimization', 'seasonal_patterns', 'keyword_trends', 'algorithm_changes') NOT NULL,
    prediction_scope VARCHAR(200),
    insights_data JSON NOT NULL,
    confidence_level DECIMAL(5,4) NOT NULL,
    time_horizon_days INT,
    model_version VARCHAR(50),
    actual_outcome JSON,
    prediction_accuracy DECIMAL(5,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    
    INDEX idx_account_type (account_id, prediction_type),
    INDEX idx_confidence (confidence_level DESC),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
);

-- Competitor Intelligence Tracking
CREATE TABLE IF NOT EXISTS competitor_intelligence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    competitor_id VARCHAR(100),
    competitor_name VARCHAR(200),
    seller_id VARCHAR(100),
    tracking_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    historical_patterns JSON,
    prediction_confidence DECIMAL(5,4),
    threat_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    counter_strategies JSON,
    intelligence_quality DECIMAL(5,4),
    
    INDEX idx_account_competitor (account_id, competitor_id),
    INDEX idx_threat_level (threat_level),
    INDEX idx_last_update (last_update)
);

-- Algorithm Change Monitoring
CREATE TABLE IF NOT EXISTS algorithm_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    detection_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_type ENUM('ranking', 'search', 'recommendation', 'pricing', 'visibility') NOT NULL,
    severity ENUM('minor', 'moderate', 'major', 'critical') NOT NULL,
    confidence DECIMAL(5,4) NOT NULL,
    affected_categories JSON,
    detected_patterns JSON,
    impact_assessment JSON,
    adaptation_strategies JSON,
    monitoring_alerts JSON,
    resolved BOOLEAN DEFAULT FALSE,
    resolution_data JSON,
    
    INDEX idx_account_severity (account_id, severity),
    INDEX idx_change_type (change_type),
    INDEX idx_detection_time (detection_timestamp),
    INDEX idx_resolved (resolved)
);

-- Advanced Performance Metrics
CREATE TABLE IF NOT EXISTS advanced_performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    item_id VARCHAR(100),
    metric_date DATE NOT NULL,
    hour_of_day TINYINT,
    day_of_week TINYINT,
    week_of_year SMALLINT,
    month TINYINT,
    quarter TINYINT,
    year SMALLINT,
    is_holiday BOOLEAN DEFAULT FALSE,
    is_weekend BOOLEAN DEFAULT FALSE,
    seasonal_factor DECIMAL(5,4),
    
    -- Engagement metrics
    impressions BIGINT DEFAULT 0,
    clicks BIGINT DEFAULT 0,
    views BIGINT DEFAULT 0,
    unique_visitors BIGINT DEFAULT 0,
    click_through_rate DECIMAL(5,4),
    engagement_rate DECIMAL(5,4),
    
    -- Conversion metrics
    conversions INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0.00,
    profit DECIMAL(12,2) DEFAULT 0.00,
    conversion_rate DECIMAL(5,4),
    average_order_value DECIMAL(10,2),
    
    -- Competition metrics
    competitor_ranking INT,
    market_share DECIMAL(5,4),
    price_competitiveness DECIMAL(5,4),
    visibility_score DECIMAL(5,4),
    
    -- Optimization metrics
    seo_score DECIMAL(5,4),
    optimization_count INT DEFAULT 0,
    last_optimization TIMESTAMP NULL,
    optimization_impact DECIMAL(5,4),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_daily_metrics (account_id, item_id, metric_date),
    INDEX idx_date_range (metric_date, year, quarter, month),
    INDEX idx_performance (conversion_rate, revenue, seo_score),
    INDEX idx_time_patterns (hour_of_day, day_of_week, is_weekend, is_holiday)
);

-- Real-Time Market Signals
CREATE TABLE IF NOT EXISTS realtime_market_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    signal_type ENUM('trend_change', 'price_anomaly', 'demand_spike', 'competitor_action', 'algorithm_shift', 'seasonal_transition') NOT NULL,
    signal_source VARCHAR(100) NOT NULL,
    severity ENUM('info', 'warning', 'critical') NOT NULL,
    confidence DECIMAL(5,4) NOT NULL,
    affected_items JSON,
    market_conditions JSON,
    recommended_actions JSON,
    signal_data JSON,
    processed BOOLEAN DEFAULT FALSE,
    processing_result JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    
    INDEX idx_signal_type_severity (signal_type, severity),
    INDEX idx_confidence (confidence DESC),
    INDEX idx_processed (processed),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);

-- Autonomous Strategy Library
CREATE TABLE IF NOT EXISTS autonomous_strategies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id VARCHAR(100),
    strategy_name VARCHAR(200) NOT NULL,
    strategy_type ENUM('pricing', 'seo', 'timing', 'competition', 'comprehensive') NOT NULL,
    strategy_complexity ENUM('basic', 'intermediate', 'advanced', 'expert') NOT NULL,
    strategy_conditions JSON NOT NULL,
    strategy_actions JSON NOT NULL,
    success_criteria JSON,
    performance_metrics JSON,
    usage_count INT DEFAULT 0,
    success_count INT DEFAULT 0,
    average_reward DECIMAL(10,6) DEFAULT 0.000000,
    confidence_score DECIMAL(5,4) DEFAULT 0.0000,
    last_used TIMESTAMP NULL,
    last_success TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_account_type_complexity (account_id, strategy_type, strategy_complexity),
    INDEX idx_success_rate (success_count, usage_count),
    INDEX idx_confidence_score (confidence_score DESC),
    INDEX idx_is_active (is_active)
);

-- Cross-Account Learning Aggregation
CREATE TABLE IF NOT EXISTS cross_account_learning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_hash VARCHAR(64) NOT NULL,
    pattern_type VARCHAR(100) NOT NULL,
    category_id VARCHAR(50),
    market_conditions JSON,
    success_patterns JSON,
    failure_patterns JSON,
    contributing_accounts JSON,
    total_applications INT DEFAULT 0,
    success_applications INT DEFAULT 0,
    average_success_rate DECIMAL(5,4),
    confidence_score DECIMAL(5,4),
    generalizability_score DECIMAL(5,4),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_pattern (pattern_hash, pattern_type, category_id),
    INDEX idx_pattern_type (pattern_type),
    INDEX idx_category (category_id),
    INDEX idx_success_rate (average_success_rate DESC),
    INDEX idx_confidence (confidence_score DESC)
);

-- Insert default neural network configurations
INSERT IGNORE INTO neural_networks (account_id, network_type, network_name, config_data, architecture) VALUES 
('global', 'dqn', 'Default DQN', JSON_OBJECT(
    'input_size', 64,
    'hidden_layers', [128, 64, 32],
    'output_size', 11,
    'activation', 'relu',
    'optimizer', 'adam',
    'learning_rate', 0.001,
    'batch_size', 32,
    'target_update_frequency', 100
), JSON_OBJECT(
    'type', 'feedforward',
    'layers', [
        {'type': 'dense', 'units': 128, 'activation': 'relu'},
        {'type': 'dense', 'units': 64, 'activation': 'relu'},
        {'type': 'dense', 'units': 32, 'activation': 'relu'},
        {'type': 'dense', 'units': 11, 'activation': 'linear'}
    ]
)),
('global', 'meta_learning', 'Meta-Learning Network', JSON_OBJECT(
    'input_size', 128,
    'hidden_layers', [256, 128, 64],
    'output_size', 64,
    'activation', 'relu',
    'optimizer', 'adam',
    'learning_rate', 0.0005,
    'meta_learning_rate', 0.001
), JSON_OBJECT(
    'type', 'meta_network',
    'layers', [
        {'type': 'dense', 'units': 256, 'activation': 'relu'},
        {'type': 'dense', 'units': 128, 'activation': 'relu'},
        {'type': 'dense', 'units': 64, 'activation': 'linear'}
    ]
));

-- Initialize curriculum levels
INSERT IGNORE INTO curriculum_progress (account_id, level_key, level_name, complexity) VALUES 
('global', 'level_1', 'Basic Price Optimization', 0.2),
('global', 'level_2', 'Advanced SEO Optimization', 0.4),
('global', 'level_3', 'Multi-Objective Optimization', 0.6),
('global', 'level_4', 'Strategic Market Navigation', 0.8),
('global', 'level_5', 'Autonomous Business Intelligence', 1.0);

-- Create materialized views for complex queries
CREATE OR REPLACE VIEW learning_summary AS
SELECT 
    a.account_id,
    COUNT(DISTINCT le.id) as total_experiences,
    AVG(le.reward) as avg_reward,
    MAX(le.created_at) as last_experience,
    COUNT(DISTINCT le.state_hash) as unique_states,
    COUNT(DISTINCT le.action) as unique_actions,
    (SELECT COUNT(*) FROM q_table WHERE account_id = a.account_id) as q_table_entries,
    (SELECT AVG(q_value) FROM q_table WHERE account_id = a.account_id) as avg_q_value
FROM learning_experiences le
RIGHT JOIN (SELECT DISTINCT account_id FROM learning_experiences) a ON le.account_id = a.account_id
GROUP BY a.account_id;

CREATE OR REPLACE VIEW neural_network_summary AS
SELECT 
    nn.account_id,
    nn.network_type,
    nn.network_name,
    nn.version,
    nn.is_active,
    COUNT(th.id) as training_steps,
    AVG(th.loss_value) as avg_loss,
    MIN(th.loss_value) as best_loss,
    MAX(th.training_step) as latest_step,
    nn.target_network_updated
FROM neural_networks nn
LEFT JOIN training_history th ON nn.id = th.network_id
GROUP BY nn.id, nn.account_id, nn.network_type, nn.network_name, nn.version, nn.is_active, nn.target_network_updated;

-- Advanced indexes for optimized performance
CREATE INDEX IF NOT EXISTS idx_learning_experiences_composite ON learning_experiences(account_id, state_hash, action, created_at);
CREATE INDEX IF NOT EXISTS idx_q_table_composite ON q_table(account_id, state_hash, q_value DESC);
CREATE INDEX IF NOT EXISTS idx_training_history_composite ON training_history(account_id, network_id, training_step DESC);
CREATE INDEX IF NOT EXISTS idx_advanced_metrics_composite ON advanced_performance_metrics(account_id, metric_date, seo_score, conversion_rate);
CREATE INDEX IF NOT EXISTS idx_autonomous_strategies_composite ON autonomous_strategies(account_id, strategy_type, success_count DESC, confidence_score DESC);

-- Partitioning for large tables (if supported)
-- Note: This would require MySQL 8.0+ and appropriate configuration

-- Triggers for automatic data maintenance
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_q_table_timestamp 
BEFORE UPDATE ON q_table
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//

CREATE TRIGGER IF NOT EXISTS archive_old_experiences 
AFTER INSERT ON learning_experiences
FOR EACH ROW
BEGIN
    -- Archive old experiences (older than 6 months) to keep table size manageable
    DELETE FROM learning_experiences 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    AND account_id = NEW.account_id 
    LIMIT 1000;
END//

CREATE TRIGGER IF NOT EXISTS cleanup_expired_predictions 
AFTER INSERT ON predictive_intelligence_cache
FOR EACH ROW
BEGIN
    -- Clean up expired predictions
    DELETE FROM predictive_intelligence_cache 
    WHERE expires_at < NOW() 
    LIMIT 500;
END//

DELIMITER ;

-- Performance optimization settings
-- These would be executed separately based on MySQL configuration
/*
ALTER TABLE learning_experiences ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;
ALTER TABLE training_history ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;
ALTER TABLE advanced_performance_metrics ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;
ALTER TABLE q_table ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;
*/

-- Full-text indexes for text search (if needed)
-- ALTER TABLE autonomous_strategies ADD FULLTEXT(strategy_name, strategy_actions);