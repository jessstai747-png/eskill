-- Migration 101: brand_sellers
-- Módulo 20 — Marca e Posicionamento (BRAND-003)
-- Vendedores únicos encontrados em cada busca de marca

CREATE TABLE IF NOT EXISTS `brand_sellers` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `search_id`           BIGINT UNSIGNED NOT NULL COMMENT 'FK → brand_searches.id',
    `seller_id`           BIGINT UNSIGNED NOT NULL COMMENT 'ID numérico do vendedor no ML',
    `nickname`            VARCHAR(100)    NOT NULL COMMENT 'Nickname público da loja',
    `seller_type`         VARCHAR(30)     NULL COMMENT 'normal, brand, real_estate_agency...',
    `permalink`           VARCHAR(255)    NULL COMMENT 'URL do perfil no ML',
    `reputation_level`    VARCHAR(20)     NULL COMMENT 'platinum, gold, silver, bronze, new',
    `reputation_score`    TINYINT UNSIGNED NULL COMMENT 'Score 0-100 calculado',
    `power_seller_status` VARCHAR(20)     NULL COMMENT 'gold, gold_special, null',
    `total_items_brand`   INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Anúncios desta marca neste seller',
    `avg_price`           DECIMAL(12,2)   NULL COMMENT 'Preço médio dos anúncios da marca',
    `site_status`         VARCHAR(20)     NULL COMMENT 'active, paused, suspended...',
    `country_id`          CHAR(2)         NULL DEFAULT 'BR',
    `city`                VARCHAR(100)    NULL,
    `state`               VARCHAR(50)     NULL,
    `trend`               ENUM('up','down','stable') NOT NULL DEFAULT 'stable',
    `last_synced_at`      DATETIME        NULL COMMENT 'Última sincronização deste vendedor',
    `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_search_seller`    (`search_id`, `seller_id`),
    INDEX  `idx_seller_id`           (`seller_id`),
    INDEX  `idx_search_id`           (`search_id`),
    INDEX  `idx_reputation`          (`reputation_level`),
    INDEX  `idx_total_items`         (`total_items_brand`),

    CONSTRAINT `fk_bs_search`
        FOREIGN KEY (`search_id`) REFERENCES `brand_searches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vendedores únicos por busca de marca';
