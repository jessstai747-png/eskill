-- =====================================================================
-- Pricing Rules Table
-- Armazena regras de precificação dinâmica
-- =====================================================================

CREATE TABLE IF NOT EXISTS pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL COMMENT 'MLB ID do produto',
    rules JSON NOT NULL COMMENT 'Array de regras de precificação',
    active BOOLEAN DEFAULT TRUE,
    last_evaluation_at TIMESTAMP NULL COMMENT 'Última vez que regras foram avaliadas',
    last_applied_at TIMESTAMP NULL COMMENT 'Última vez que regra foi aplicada',
    evaluation_frequency_minutes INT DEFAULT 60 COMMENT 'Frequência de avaliação',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_account_item (account_id, item_id),
    INDEX idx_account_id (account_id),
    INDEX idx_item_id (item_id),
    INDEX idx_active (active),
    INDEX idx_last_evaluation (last_evaluation_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários nas colunas
ALTER TABLE pricing_rules COMMENT = 'Regras de precificação dinâmica configuradas por item';

-- =====================================================================
-- Pricing Rule Executions (histórico de aplicações)
-- =====================================================================

CREATE TABLE IF NOT EXISTS pricing_rule_executions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    rule_triggered JSON NOT NULL COMMENT 'Regra específica que disparou',
    old_price DECIMAL(10,2) NOT NULL,
    new_price DECIMAL(10,2) NOT NULL,
    price_change_percentage DECIMAL(5,2) NOT NULL,
    reason VARCHAR(255) NOT NULL COMMENT 'Motivo da mudança (ex: competitor_price_below)',
    applied_successfully BOOLEAN DEFAULT FALSE,
    error_message TEXT DEFAULT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_rule_id (rule_id),
    INDEX idx_account_id (account_id),
    INDEX idx_item_id (item_id),
    INDEX idx_executed_at (executed_at),
    FOREIGN KEY (rule_id) REFERENCES pricing_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pricing_rule_executions COMMENT = 'Histórico de execuções de regras de precificação';

-- =====================================================================
-- Price Elasticity Analysis (armazena análises de elasticidade)
-- =====================================================================

CREATE TABLE IF NOT EXISTS price_elasticity_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    elasticity_coefficient DECIMAL(5,2) NOT NULL COMMENT 'Coeficiente de elasticidade',
    interpretation ENUM('highly_elastic', 'elastic', 'moderately_elastic', 'inelastic') NOT NULL,
    data_points_count INT NOT NULL COMMENT 'Quantidade de dados históricos usados',
    scenarios JSON NOT NULL COMMENT 'Cenários simulados (+10%, -10%, etc)',
    recommendations TEXT DEFAULT NULL,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_id (account_id),
    INDEX idx_item_id (item_id),
    INDEX idx_analyzed_at (analyzed_at),
    UNIQUE KEY unique_item_analysis (item_id, analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE price_elasticity_analysis COMMENT = 'Análises de elasticidade de preço por produto';
