-- Migration: Account X-Ray Reports System
-- Data: 2026-03-08

CREATE TABLE IF NOT EXISTS account_xray_reports (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id    INT NOT NULL,
    seller_id     VARCHAR(50) NULL,
    nickname      VARCHAR(100) NULL,
    status        ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    score_overall TINYINT UNSIGNED NULL COMMENT '0-100 overall health score',
    account_status VARCHAR(30) NULL COMMENT 'TRAVADA|PENALIZADA|EM_RECUPERACAO|ESTAVEL|FORTE',
    items_total   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    items_analyzed SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    critical_issues TINYINT UNSIGNED NOT NULL DEFAULT 0,
    report_json   LONGTEXT NULL COMMENT 'Full X-Ray report (JSON)',
    options_json  TEXT NULL COMMENT 'Options used for this run',
    error_message TEXT NULL,
    started_at    DATETIME NULL,
    completed_at  DATETIME NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_account_id (account_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Raio X — Account diagnostic reports';

CREATE TABLE IF NOT EXISTS xray_item_scores (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id    INT UNSIGNED NOT NULL,
    item_id      VARCHAR(30) NOT NULL,
    title        VARCHAR(255) NOT NULL,
    category_id  VARCHAR(30) NULL,
    classification VARCHAR(30) NULL COMMENT 'ANCHOR|SAUDAVEL|EM_RISCO|FRACO|MORTO|TOXICO|POLUIDOR|SEM_ESTOQUE',
    score_overall TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_seo     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_semantic TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score_longtail TINYINT UNSIGNED NOT NULL DEFAULT 0,
    missing_keywords_json TEXT NULL,
    gap_keywords_json     TEXT NULL,
    actions_json          TEXT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report_id (report_id),
    INDEX idx_item_id (item_id),
    INDEX idx_classification (classification),
    CONSTRAINT fk_xray_item_report FOREIGN KEY (report_id) REFERENCES account_xray_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Raio X — Per-item SEO scores';
