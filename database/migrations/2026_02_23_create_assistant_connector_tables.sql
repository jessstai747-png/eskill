CREATE TABLE IF NOT EXISTS assistant_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NULL,
  source VARCHAR(50) NOT NULL,
  external_event_id VARCHAR(128) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  occurred_at DATETIME NULL,
  payload JSON NOT NULL,
  status ENUM('received', 'processed', 'failed') NOT NULL DEFAULT 'received',
  error_message TEXT NULL,
  processed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_source_external_event (source, external_event_id),
  KEY idx_account_type_created (account_id, event_type, created_at),
  KEY idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS assistant_action_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  user_id INT NULL,
  api_token_id INT NULL,
  job_id INT NULL,
  action VARCHAR(50) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  status ENUM('queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'queued',
  attempts INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 3,
  parameters JSON NOT NULL,
  result JSON NULL,
  error_message TEXT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_account_idempotency (account_id, idempotency_key),
  KEY idx_status_created (status, created_at),
  KEY idx_job_id (job_id),
  KEY idx_account_action_created (account_id, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
