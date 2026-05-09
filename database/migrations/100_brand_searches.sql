-- Migration 100: brand_searches
-- Módulo 20 — Marca e Posicionamento (BRAND-003)
-- Registra cada busca de anúncios por marca realizada no sistema

CREATE TABLE IF NOT EXISTS `brand_searches` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id`      BIGINT UNSIGNED NOT NULL COMMENT 'Conta ML associada (multi-conta)',
    `brand_id`        VARCHAR(20)     NOT NULL COMMENT 'ID da marca no ML (ex: 7297804)',
    `brand_name`      VARCHAR(100)    NOT NULL COMMENT 'Nome da marca (ex: AWA)',
    `site_id`         VARCHAR(10)     NOT NULL DEFAULT 'MLB' COMMENT 'Site ML (MLB, MLA...)',
    `category_id`     VARCHAR(20)     NULL COMMENT 'Filtro de categoria aplicado (NULL = todas)',
    `status`          ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    `total_items`     INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Total de anúncios encontrados',
    `total_sellers`   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Total de vendedores únicos',
    `progress`        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Progresso da coleta 0-100',
    `error_message`   TEXT            NULL COMMENT 'Mensagem de erro se status=failed',
    `started_at`      DATETIME        NULL,
    `completed_at`    DATETIME        NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_account_brand`  (`account_id`, `brand_id`),
    INDEX `idx_status`         (`status`),
    INDEX `idx_brand_name`     (`brand_name`),
    INDEX `idx_created_at`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Buscas de anúncios por marca no Mercado Livre';
