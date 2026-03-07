<?php
declare(strict_types=1);

namespace App\Services;

class CreateMLAdvancedTables
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \App\Database::getInstance();
    }

    /**
     * Run all migrations
     */
    public function up(): void
    {
        try {
            // Create tables in order of dependencies
            $this->createMLAdsTables();
            $this->createSmartQATables();
            $this->createPricingEngineTables();
            $this->createCompetitorIntelligenceTables();
            $this->createMLAnalyticsTables();
            $this->createMLUnifiedTables();

            echo "ML Advanced tables created successfully!\n";
        } catch (\Exception $e) {
            echo "Error creating ML Advanced tables: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Create ML Ads related tables
     */
    private function createMLAdsTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ml_ad_campaigns_advanced (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_id INT NOT NULL,
                campaign_id VARCHAR(100) NOT NULL,
                campaign_name VARCHAR(255) NOT NULL,
                status ENUM('active', 'paused', 'ended') DEFAULT 'active',
                daily_budget DECIMAL(10,2) DEFAULT 0.00,
                total_budget DECIMAL(10,2) DEFAULT 0.00,
                start_date DATETIME,
                end_date DATETIME NULL,
                optimization_level ENUM('basic', 'advanced', 'expert') DEFAULT 'basic',
                auto_optimization BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_account_id (account_id),
                INDEX idx_status (status),
                INDEX idx_campaign_id (campaign_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_ad_items_advanced (
                id INT PRIMARY KEY AUTO_INCREMENT,
                campaign_id VARCHAR(100) NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                current_bid DECIMAL(10,2) DEFAULT 0.00,
                optimal_bid DECIMAL(10,2) DEFAULT 0.00,
                target_position ENUM('top', 'middle', 'bottom') DEFAULT 'middle',
                bid_adjustment_percentage DECIMAL(5,2) DEFAULT 0.00,
                conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
                impressions INT DEFAULT 0,
                clicks INT DEFAULT 0,
                cost DECIMAL(10,2) DEFAULT 0.00,
                revenue DECIMAL(10,2) DEFAULT 0.00,
                roas DECIMAL(8,2) DEFAULT 0.00,
                optimization_score DECIMAL(5,2) DEFAULT 0.00,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES ml_ad_campaigns_advanced(campaign_id) ON DELETE CASCADE,
                INDEX idx_campaign_id (campaign_id),
                INDEX idx_item_id (item_id),
                INDEX idx_roas (roas DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_ad_targeting (
                id INT PRIMARY KEY AUTO_INCREMENT,
                campaign_id VARCHAR(100) NOT NULL,
                targeting_type ENUM('behavioral', 'demographic', 'geographic', 'interest_based') NOT NULL,
                targeting_data JSON NOT NULL,
                audience_size INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES ml_ad_campaigns_advanced(campaign_id) ON DELETE CASCADE,
                INDEX idx_campaign_id (campaign_id),
                INDEX idx_targeting_type (targeting_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_ad_performance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                campaign_id VARCHAR(100) NOT NULL,
                date DATE NOT NULL,
                impressions INT DEFAULT 0,
                clicks INT DEFAULT 0,
                cost DECIMAL(10,2) DEFAULT 0.00,
                revenue DECIMAL(10,2) DEFAULT 0.00,
                conversions INT DEFAULT 0,
                roas DECIMAL(8,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES ml_ad_campaigns_advanced(campaign_id) ON DELETE CASCADE,
                INDEX idx_campaign_date (campaign_id, date),
                INDEX idx_date (date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->executeSQL($sql);
    }

    /**
     * Create Smart Q&A related tables
     */
    private function createSmartQATables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ml_qa_automation (
                id INT PRIMARY KEY AUTO_INCREMENT,
                question_id VARCHAR(50) NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                analysis_data JSON,
                auto_respond BOOLEAN DEFAULT FALSE,
                escalate BOOLEAN DEFAULT FALSE,
                confidence_score DECIMAL(5,2) DEFAULT 0.00,
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                category VARCHAR(100),
                sentiment ENUM('positive', 'neutral', 'negative') DEFAULT 'neutral',
                complexity ENUM('low', 'medium', 'high') DEFAULT 'medium',
                intent VARCHAR(100),
                answer_text TEXT,
                answer_template_id VARCHAR(50),
                processing_time_ms INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (question_id) REFERENCES ml_questions(question_id) ON DELETE CASCADE,
                INDEX idx_question_id (question_id),
                INDEX idx_auto_respond (auto_respond),
                INDEX idx_priority (priority),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_qa_knowledge_base (
                id INT PRIMARY KEY AUTO_INCREMENT,
                category VARCHAR(100) NOT NULL,
                keywords JSON NOT NULL,
                question_pattern VARCHAR(500),
                answer_template TEXT NOT NULL,
                confidence_score DECIMAL(5,2) DEFAULT 0.00,
                usage_count INT DEFAULT 0,
                success_count INT DEFAULT 0,
                last_used TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_keywords (keywords(100)),
                FULLTEXT idx_answer (answer_template)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_qa_proactive (
                id INT PRIMARY KEY AUTO_INCREMENT,
                item_id VARCHAR(50) NOT NULL,
                question_text VARCHAR(500) NOT NULL,
                question_type VARCHAR(100) NOT NULL,
                status ENUM('draft', 'published', 'inactive') DEFAULT 'draft',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES ml_items(id) ON DELETE CASCADE,
                INDEX idx_item_id (item_id),
                INDEX idx_status (status),
                INDEX idx_question_type (question_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_qa_batch_processing (
                id INT PRIMARY KEY AUTO_INCREMENT,
                batch_id VARCHAR(50) NOT NULL,
                question_id VARCHAR(50) NOT NULL,
                processing_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                result_data JSON,
                error_message TEXT,
                processing_time_ms INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (question_id) REFERENCES ml_questions(question_id) ON DELETE CASCADE,
                INDEX idx_batch_id (batch_id),
                INDEX idx_processing_status (processing_status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->executeSQL($sql);
    }

    /**
     * Create Advanced Pricing Engine related tables
     */
    private function createPricingEngineTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ml_pricing_rules (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_id INT NOT NULL,
                rule_name VARCHAR(255) NOT NULL,
                rule_type ENUM('dynamic_pricing', 'psychological', 'elasticity', 'competitor_based') NOT NULL,
                rule_conditions JSON NOT NULL,
                rule_actions JSON NOT NULL,
                priority INT DEFAULT 1,
                enabled BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_account_id (account_id),
                INDEX idx_rule_type (rule_type),
                INDEX idx_enabled (enabled)
            ) ENGINE=noDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_pricing_optimizations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id VARCHAR(50) NOT NULL,
                rule_id INT NOT NULL,
                current_price DECIMAL(10,2) NOT NULL,
                optimal_price DECIMAL(10,2) NOT NULL,
                adjustment_percentage DECIMAL(5,2) NOT NULL,
                adjustment_reason VARCHAR(255),
                confidence_score DECIMAL(5,2) DEFAULT 0.00,
                elasticity_coefficient DECIMAL(8,4) DEFAULT 1.0000,
                psychological_type VARCHAR(100),
                market_factors JSON,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                status ENUM('active', 'expired', 'reverted') DEFAULT 'active',
                FOREIGN KEY (rule_id) REFERENCES ml_pricing_rules(id) ON DELETE CASCADE,
                INDEX idx_product_id (product_id),
                INDEX idx_rule_id (rule_id),
                INDEX idx_status (status),
                INDEX idx_applied_at (applied_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_pricing_competitors (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id VARCHAR(50) NOT NULL,
                competitor_id VARCHAR(50) NOT NULL,
                competitor_name VARCHAR(255),
                current_price DECIMAL(10,2) NOT NULL,
                price_change DECIMAL(5,2) NOT NULL,
                price_change_percentage DECIMAL(5,2) NOT NULL,
                market_position INT,
                stock_level ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
                last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_product_id (product_id),
                INDEX idx_competitor_id (competitor_id),
                INDEX idx_price_change_percentage (price_change_percentage DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_pricing_elasticity (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id VARCHAR(50) NOT NULL,
                price_point DECIMAL(10,2) NOT NULL,
                demand_quantity INT DEFAULT 0,
                time_period_days INT DEFAULT 30,
                price_sensitivity DECIMAL(8,4) DEFAULT 1.0000,
                confidence_interval_lower DECIMAL(10,2) DEFAULT 0.00,
                confidence_interval_upper DECIMAL(10,2) DEFAULT 0.00,
                sample_size INT DEFAULT 0,
                calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                INDEX idx_product_id (product_id),
                INDEX idx_calculated_at (calculated_at),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->executeSQL($sql);
    }

    /**
     * Create Competitor Intelligence related tables
     */
    private function createCompetitorIntelligenceTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ml_competitor_monitoring (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_id INT NOT NULL,
                competitor_id VARCHAR(50) NOT NULL,
                competitor_name VARCHAR(255) NOT NULL,
                monitoring_config JSON,
                last_scan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                alert_preferences JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_account_id (account_id),
                INDEX idx_competitor_id (competitor_id),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_competitor_data (
                id INT PRIMARY KEY AUTO_INCREMENT,
                competitor_id VARCHAR(50) NOT NULL,
                data_type ENUM('listings', 'prices', 'advertising', 'reputation') NOT NULL,
                raw_data JSON NOT NULL,
                extracted_data JSON,
                confidence_score DECIMAL(5,2) DEFAULT 0.00,
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (competitor_id) REFERENCES ml_competitor_monitoring(competitor_id) ON DELETE CASCADE,
                INDEX idx_competitor_id (competitor_id),
                INDEX idx_data_type (data_type),
                INDEX idx_analyzed_at (analyzed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_competitor_alerts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                competitor_id VARCHAR(50) NOT NULL,
                alert_type ENUM('price_change', 'new_product', 'ad_change', 'reputation_change') NOT NULL,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                alert_data JSON,
                message TEXT NOT NULL,
                action_required BOOLEAN DEFAULT FALSE,
                action_taken BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acknowledged_at TIMESTAMP NULL,
                FOREIGN KEY (competitor_id) REFERENCES ml_competitor_monitoring(competitor_id) ON DELETE CASCADE,
                INDEX idx_competitor_id (competitor_id),
                INDEX idx_severity (severity),
                INDEX idx_action_required (action_required),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_market_opportunities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                category_id VARCHAR(50) NOT NULL,
                opportunity_type ENUM('price_gap', 'product_gap', 'service_gap', 'market_untapped') NOT NULL,
                description TEXT NOT NULL,
                potential_impact ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                estimated_roi DECIMAL(5,2) DEFAULT 0.00,
                confidence_score DECIMAL(5,2) DEFAULT 0.00,
                market_size_estimate DECIMAL(10,2) DEFAULT 0.00,
                competition_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                INDEX idx_category_id (category_id),
                INDEX idx_opportunity_type (opportunity_type),
                INDEX idx_potential_impact (potential_impact DESC),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->executeSQL($sql);
    }

    /**
     * Create ML Analytics related tables
     */
    private function createMLAnalyticsTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ml_search_analytics (
                id INT PRIMARY KEY AUTO_INCREMENT,
                search_term VARCHAR(500) NOT NULL,
                search_date DATE NOT NULL,
                search_time TIME NOT NULL,
                user_segment JSON,
                category_id VARCHAR(50),
                results_count INT DEFAULT 0,
                click_rate DECIMAL(5,4) DEFAULT 0.0000,
                conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
                avg_position DECIMAL(5,2) DEFAULT 0.00,
                search_volume INT DEFAULT 0,
                trends_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_search_term (search_term(100)),
                INDEX idx_search_date (search_date),
                INDEX idx_category_id (category_id),
                INDEX idx_search_volume (search_volume DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_customer_journey (
                id INT PRIMARY KEY AUTO_INCREMENT,
                customer_id VARCHAR(100) NOT NULL,
                session_id VARCHAR(100),
                touchpoint_type ENUM('search', 'view', 'cart_add', 'purchase', 'question', 'review') NOT NULL,
                product_id VARCHAR(50),
                touchpoint_data JSON,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                sequence_order INT DEFAULT 0,
                journey_id VARCHAR(100),
                converted BOOLEAN DEFAULT FALSE,
                path_to_conversion JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_customer_id (customer_id),
                INDEX idx_session_id (session_id),
                INDEX idx_touchpoint_type (touchpoint_type),
                INDEX idx_timestamp (timestamp),
                INDEX idx_journey_id (journey_id),
                INDEX idx_converted (converted)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_conversion_funnel (
                id INT PRIMARY KEY AUTO_INCREMENT,
                funnel_name VARCHAR(255) NOT NULL,
                stage_name VARCHAR(100) NOT NULL,
                stage_order INT NOT NULL,
                users_at_stage INT DEFAULT 0,
                users_completed_stage INT DEFAULT 0,
                conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
                avg_time_to_conversion INT DEFAULT 0,
                drop_off_rate DECIMAL(5,4) DEFAULT 0.0000,
                date DATE NOT NULL,
                funnel_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_funnel_name (funnel_name),
                INDEX idx_date (date),
                INDEX idx_stage_order (stage_order)
            ) ENGINE=noDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_roi_attribution (
                id INT PRIMARY KEY AUTO_INCREMENT,
                customer_id VARCHAR(100),
                touchpoints JSON NOT NULL,
                attribution_model ENUM('first_touch', 'last_touch', 'linear', 'time_decay') DEFAULT 'time_decay',
                channel VARCHAR(100),
                campaign_id VARCHAR(100),
                ad_group_id VARCHAR(100),
                total_value DECIMAL(10,2) DEFAULT 0.00,
                attributed_value DECIMAL(10,2) DEFAULT 0.00,
                attribution_weight DECIMAL(5,4) DEFAULT 0.0000,
                conversion_date DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_customer_id (customer_id),
                INDEX idx_attribution_model (attribution_model),
                INDEX idx_conversion_date (conversion_date),
                INDEX idx_attributed_value (attributed_value DESC)
            ) ENGINE=noDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_predictive_analytics (
                id INT PRIMARY KEY AUTO_INCREMENT,
                prediction_type ENUM('demand', 'pricing', 'market_trends', 'customer_behavior', 'competitor_actions') NOT NULL,
                target_id VARCHAR(50),
                prediction_data JSON NOT NULL,
                confidence_score DECIMAL(5,2) DEFAULT 0.00,
                prediction_horizon_days INT DEFAULT 30,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP,
                actual_outcome JSON,
                accuracy_score DECIMAL(5,2),
                status ENUM('pending', 'verified', 'expired') DEFAULT 'pending',
                INDEX idx_prediction_type (prediction_type),
                INDEX idx_target_id (target_id),
                INDEX idx_confidence_score (confidence_score DESC),
                INDEX idx_expires_at (expires_at),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->executeSQL($sql);
    }

    /**
     * Create unified ML tables
     */
    private function createMLUnifiedTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS ml_service_executions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                execution_id VARCHAR(50) NOT NULL,
                execution_plan ENUM('conservative', 'balanced', 'aggressive', 'comprehensive') NOT NULL,
                services_executed JSON NOT NULL,
                execution_results JSON NOT NULL,
                unified_insights JSON,
                summary_data JSON,
                total_execution_time_ms INT DEFAULT 0,
                status ENUM('running', 'completed', 'failed', 'cancelled') DEFAULT 'running',
                error_message TEXT,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                INDEX idx_execution_id (execution_id),
                INDEX idx_status (status),
                INDEX idx_started_at (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_service_status (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_name ENUM('ads', 'qa', 'pricing', 'competitor', 'analytics') NOT NULL,
                status ENUM('active', 'inactive', 'error', 'maintenance') DEFAULT 'active',
                health_score DECIMAL(5,2) DEFAULT 100.00,
                last_execution TIMESTAMP NULL,
                error_count INT DEFAULT 0,
                last_error TEXT,
                uptime_percentage DECIMAL(5,2) DEFAULT 100.00,
                performance_metrics JSON,
                configuration JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_service_name (service_name),
                INDEX idx_status (status),
                INDEX idx_health_score (health_score)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS ml_service_alerts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                service_name ENUM('ads', 'qa', 'pricing', 'competitor', 'analytics') NOT NULL,
                alert_type ENUM('error', 'warning', 'info', 'success') NOT NULL,
                alert_message TEXT NOT NULL,
                alert_data JSON,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                requires_action BOOLEAN DEFAULT FALSE,
                action_taken BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acknowledged_at TIMESTAMP NULL,
                expires_at TIMESTAMP,
                INDEX idx_service_name (service_name),
                INDEX idx_alert_type (alert_type),
                INDEX idx_severity (severity),
                INDEX idx_requires_action (requires_action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->executeSQL($sql);
    }

    /**
     * Execute SQL statement
     */
    private function executeSQL(string $sql): void
    {
        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            echo "SQL Error: " . $e->getMessage() . "\n";
            echo "SQL: " . $sql . "\n";
        }
    }
}

// Run migrations
if (basename(__FILE__) === 'CreateMLAdvancedTables.php') {
    $migration = new CreateMLAdvancedTables();
    $migration->up();
}
