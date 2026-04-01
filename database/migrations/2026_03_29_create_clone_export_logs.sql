CREATE TABLE IF NOT EXISTS clone_export_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT NOT NULL,
    export_scope VARCHAR(50) NOT NULL,
    export_format VARCHAR(20) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    item_count INT NOT NULL DEFAULT 0,
    size_bytes BIGINT NOT NULL DEFAULT 0,
    filters_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_clone_export_logs_filename (filename),
    KEY idx_clone_export_logs_account_created (account_id, created_at),
    KEY idx_clone_export_logs_scope_format (export_scope, export_format)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
