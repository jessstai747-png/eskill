-- Migration 102: brand_items
-- Módulo 20 — Marca e Posicionamento (BRAND-003)
-- Anúncios individuais coletados em cada busca de marca

CREATE TABLE IF NOT EXISTS `brand_items` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `search_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK → brand_searches.id',
    `seller_id`     BIGINT UNSIGNED NOT NULL COMMENT 'FK → brand_sellers.seller_id',
    `item_id`       VARCHAR(20)     NOT NULL COMMENT 'ID do anúncio no ML (ex: MLB123456)',
    `title`         VARCHAR(255)    NOT NULL,
    `category_id`   VARCHAR(20)     NULL,
    `category_name` VARCHAR(100)    NULL,
    `price`         DECIMAL(12,2)   NULL,
    `currency_id`   CHAR(3)         NULL DEFAULT 'BRL',
    `condition`     ENUM('new','used','not_specified') NOT NULL DEFAULT 'new',
    `listing_type`  VARCHAR(30)     NULL COMMENT 'gold_pro, gold_special, gold...',
    `permalink`     VARCHAR(500)    NULL,
    `thumbnail`     VARCHAR(500)    NULL,
    `available_qty` INT UNSIGNED    NULL,
    `status`        VARCHAR(20)     NULL DEFAULT 'active',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_search_item`  (`search_id`, `item_id`),
    INDEX  `idx_search_id`       (`search_id`),
    INDEX  `idx_seller_id`       (`seller_id`),
    INDEX  `idx_item_id`         (`item_id`),
    INDEX  `idx_category`        (`category_id`),
    INDEX  `idx_price`           (`price`),

    CONSTRAINT `fk_bi_search`
        FOREIGN KEY (`search_id`) REFERENCES `brand_searches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Anúncios coletados por busca de marca';
