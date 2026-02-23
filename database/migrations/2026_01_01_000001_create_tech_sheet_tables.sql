-- @deprecated Use 20260101_create_tech_sheet_tables.php (versão PHP mais completa)
-- Tech Sheet (Ficha Técnica) - Summary + Suggestions
-- Data: 2026-01-01

CREATE TABLE IF NOT EXISTS tech_sheet_item_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    category_id VARCHAR(50) NULL,

    total_available INT DEFAULT 0,
    filled INT DEFAULT 0,
    missing INT DEFAULT 0,
    completeness_percent DECIMAL(5,1) DEFAULT 0.0,

    missing_required INT DEFAULT 0,
    missing_filter INT DEFAULT 0,
    missing_hidden INT DEFAULT 0,
    missing_recommended INT DEFAULT 0,

    last_analyzed_at DATETIME NULL,

    meta JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_account_item (account_id, item_id),
    INDEX idx_account_completeness (account_id, completeness_percent),
    INDEX idx_account_updated (account_id, updated_at),
    INDEX idx_account_category (account_id, category_id),
    CONSTRAINT fk_tech_sheet_summary_account FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Resumo de completude de ficha técnica por item (cache para listagem)';

CREATE TABLE IF NOT EXISTS tech_sheet_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    category_id VARCHAR(50) NULL,

    attribute_id VARCHAR(100) NOT NULL,
    attribute_name VARCHAR(255) NULL,

    suggested_value TEXT NOT NULL,
    source VARCHAR(50) NOT NULL COMMENT 'inference|ai|title|competitor|default',
    confidence TINYINT UNSIGNED NULL,

    status ENUM('pending', 'approved', 'rejected', 'applied') NOT NULL DEFAULT 'pending',

    decided_by_user_id INT NULL,
    decided_at DATETIME NULL,

    applied_at DATETIME NULL,

    meta JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_account_item_attr (account_id, item_id, attribute_id),
    INDEX idx_account_item_status (account_id, item_id, status),
    INDEX idx_account_status (account_id, status),
    INDEX idx_account_created (account_id, created_at),

    CONSTRAINT fk_tech_sheet_suggestions_account FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_tech_sheet_suggestions_user FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sugestões para preenchimento de atributos com fluxo de aprovação';
