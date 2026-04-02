-- ============================================================================
-- Migration: create_awa_seller_tracking_tables
-- Data: 2026-04-02
-- Módulo: AWA Sellers — registry persistente de lojas que anunciam produtos AWA
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. awa_scan_runs — execuções de varredura por conta
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS awa_scan_runs (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id    INT NOT NULL,
    scope_json    JSON NULL COMMENT 'Categorias e parâmetros da varredura',
    status        ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'running',
    sellers_found INT UNSIGNED NOT NULL DEFAULT 0,
    items_found   INT UNSIGNED NOT NULL DEFAULT 0,
    started_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at   TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_awa_scan_runs_account_created (account_id, created_at),
    INDEX idx_awa_scan_runs_account_status (account_id, status),
    CONSTRAINT fk_awa_scan_runs_account FOREIGN KEY (account_id) REFERENCES ml_accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Execuções de varredura AWA de sellers no Mercado Livre por conta';

-- ----------------------------------------------------------------------------
-- 2. awa_seller_registry — entidade seller consolidada por conta
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS awa_seller_registry (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id          INT NOT NULL,
    seller_id           BIGINT UNSIGNED NOT NULL COMMENT 'ML user id',
    nickname            VARCHAR(255) NOT NULL DEFAULT '',
    permalink           VARCHAR(512) NULL,
    city                VARCHAR(100) NULL,
    state               VARCHAR(120) NULL,
    user_type           VARCHAR(50) NULL COMMENT 'normal, brand',
    reputation_level    VARCHAR(50) NULL COMMENT 'ex: 5_green, 4_light_green',
    power_seller_status VARCHAR(50) NULL,
    items_count         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Anúncios AWA detectados',
    categories_json     JSON NULL COMMENT 'Categorias em que aparece',
    first_seen_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_scan_id        INT UNSIGNED NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_awa_seller_registry_account_seller (account_id, seller_id),
    INDEX idx_awa_seller_registry_account_last_seen (account_id, last_seen_at),
    INDEX idx_awa_seller_registry_account_active (account_id, is_active),
    INDEX idx_awa_seller_registry_reputation (reputation_level),
    CONSTRAINT fk_awa_seller_registry_account FOREIGN KEY (account_id) REFERENCES ml_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_awa_seller_registry_last_scan FOREIGN KEY (last_scan_id) REFERENCES awa_scan_runs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registry consolidado de sellers AWA detectados no Mercado Livre';

-- ----------------------------------------------------------------------------
-- 3. awa_seller_items — anúncios observados por seller e conta
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS awa_seller_items (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id          INT NOT NULL,
    seller_registry_id  INT UNSIGNED NOT NULL,
    ml_item_id          VARCHAR(50) NOT NULL COMMENT 'ex: MLB123456789',
    title               VARCHAR(512) NOT NULL DEFAULT '',
    category_id         VARCHAR(50) NULL,
    price               DECIMAL(12,2) NULL,
    status              VARCHAR(30) NULL COMMENT 'active, paused, closed',
    brand_match_type    VARCHAR(50) NOT NULL DEFAULT 'unclassified',
    has_brand_attribute TINYINT(1) NOT NULL DEFAULT 0,
    evidence_json       JSON NULL COMMENT 'Dados brutos de evidência',
    first_seen_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_awa_seller_items_account_item (account_id, ml_item_id),
    INDEX idx_awa_seller_items_registry (seller_registry_id),
    INDEX idx_awa_seller_items_account_last_seen (account_id, last_seen_at),
    INDEX idx_awa_seller_items_account_match (account_id, brand_match_type),
    INDEX idx_awa_seller_items_category (category_id),
    CONSTRAINT fk_awa_seller_items_account FOREIGN KEY (account_id) REFERENCES ml_accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_awa_seller_items_registry FOREIGN KEY (seller_registry_id) REFERENCES awa_seller_registry (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Anúncios AWA observados por seller no Mercado Livre';

-- ============================================================================
-- Para reverter:
-- DROP TABLE IF EXISTS awa_seller_items;
-- DROP TABLE IF EXISTS awa_seller_registry;
-- DROP TABLE IF EXISTS awa_scan_runs;
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 4. awa_seller_identification — enriquecimento jurídico/comercial
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS awa_seller_identification (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seller_registry_id  INT UNSIGNED NOT NULL,
    cnpj                VARCHAR(20) NULL COMMENT 'Formatado: XX.XXX.XXX/XXXX-XX',
    razao_social        VARCHAR(255) NULL,
    source_type         ENUM('manual','authorized_ml_account','internal_registry','external_registry','website_review','legal_team_validation') NOT NULL DEFAULT 'manual',
    source_reference    VARCHAR(255) NULL,
    confidence_score    TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100',
    verification_status ENUM('verified','pending','not_available','conflict') NOT NULL DEFAULT 'pending',
    verified_at         TIMESTAMP NULL,
    notes               TEXT NULL,
    created_by          VARCHAR(100) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_asi_registry (seller_registry_id),
    INDEX idx_asi_status (verification_status),
    CONSTRAINT fk_asi_registry FOREIGN KEY (seller_registry_id) REFERENCES awa_seller_registry (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Identificação jurídica e comercial dos sellers AWA';

-- ============================================================================
-- Para reverter (4 tabelas):
-- DROP TABLE IF EXISTS awa_seller_identification;
-- DROP TABLE IF EXISTS awa_seller_items;
-- DROP TABLE IF EXISTS awa_seller_registry;
-- DROP TABLE IF EXISTS awa_scan_runs;
-- ============================================================================
