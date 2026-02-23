CREATE TABLE IF NOT EXISTS `user_api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `client_id` varchar(64) NOT NULL,
  `client_secret_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Key identifier/name',
  `permissions` json DEFAULT NULL COMMENT 'JSON array of scopes',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active', 'revoked') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
