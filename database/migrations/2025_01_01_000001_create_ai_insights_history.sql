-- =====================================================================
-- AI Insights History Table
-- Armazena histórico de insights gerados pela IA
-- =====================================================================

CREATE TABLE IF NOT EXISTS ai_insights_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'strategic, ab_test, trends, sentiment, etc',
    insights JSON NOT NULL COMMENT 'Dados completos do insight gerado',
    metadata JSON DEFAULT NULL COMMENT 'Contexto adicional (options, filters, etc)',
    confidence_score DECIMAL(5,2) DEFAULT NULL COMMENT 'Nível de confiança 0-100',
    tokens_used INT DEFAULT NULL COMMENT 'Tokens consumidos da OpenAI API',
    processing_time_ms INT DEFAULT NULL COMMENT 'Tempo de processamento em ms',
    status ENUM('success', 'partial', 'error') DEFAULT 'success',
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_account_id (account_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    INDEX idx_account_type (account_id, type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários nas colunas
ALTER TABLE ai_insights_history COMMENT = 'Histórico de insights gerados pela IA (GPT-4)';
