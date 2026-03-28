CREATE TABLE IF NOT EXISTS `seller_catalog_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` varchar(100) NOT NULL,
  `filters_hash` varchar(64) NOT NULL COMMENT 'SHA-256 do JSON serializado dos filtros',
  `filters` json DEFAULT NULL COMMENT 'Filtros originais (categoria, marca, keyword, etc.)',
  `snapshot_data` longtext NOT NULL COMMENT 'JSON do resultado completo (items + facets + summary)',
  `item_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de itens no snapshot',
  `expires_at` datetime NOT NULL COMMENT 'TTL do cache',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seller_filters` (`seller_id`, `filters_hash`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cache de catálogos de sellers para grandes volumes';
