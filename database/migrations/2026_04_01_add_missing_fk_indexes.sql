-- ============================================================================
-- Migration: Adicionar índices faltando em colunas de Foreign Key
-- Data: 2026-04-01
-- Descrição: 26 colunas FK identificadas sem INDEX correspondente.
--            Usa verificação via INFORMATION_SCHEMA para idempotência total.
-- ============================================================================

SET @db = DATABASE();

-- ---------------------------------------------------------------------------
-- 010_pwa_push_notifications: pwa_settings.user_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pwa_settings' AND INDEX_NAME='idx_user_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_user_id ON pwa_settings(user_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 014_add_performance_tables: api_metrics.account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='api_metrics' AND INDEX_NAME='idx_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_account_id ON api_metrics(account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 015_whatsapp_integration: whatsapp_logs.user_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='whatsapp_logs' AND INDEX_NAME='idx_user_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_user_id ON whatsapp_logs(user_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 018_create_remember_tokens_table: remember_tokens.user_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='remember_tokens' AND INDEX_NAME='idx_user_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_user_id ON remember_tokens(user_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 030_create_seo_intelligence_tables: seo_automation_config.account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='seo_automation_config' AND INDEX_NAME='idx_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_account_id ON seo_automation_config(account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 2024_06_01_create_pricing_phase3_tables: notification_subscriptions.channel_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='notification_subscriptions' AND INDEX_NAME='idx_channel_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_channel_id ON notification_subscriptions(channel_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 2024_06_01_create_pricing_phase3_tables: push_notification_queue.campaign_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='push_notification_queue' AND INDEX_NAME='idx_campaign_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_campaign_id ON push_notification_queue(campaign_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- tech_sheet_item_summary.account_id  (fk_tech_sheet_summary_account)
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_item_summary' AND INDEX_NAME='idx_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_account_id ON tech_sheet_item_summary(account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- tech_sheet_suggestions.account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_suggestions' AND INDEX_NAME='idx_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_account_id ON tech_sheet_suggestions(account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- tech_sheet_suggestions.decided_by_user_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_suggestions' AND INDEX_NAME='idx_decided_by_user_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_decided_by_user_id ON tech_sheet_suggestions(decided_by_user_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- tech_sheet_alert_recipients.rule_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_alert_recipients' AND INDEX_NAME='idx_rule_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_rule_id ON tech_sheet_alert_recipients(rule_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- tech_sheet_alerts.rule_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tech_sheet_alerts' AND INDEX_NAME='idx_rule_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_rule_id ON tech_sheet_alerts(rule_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- clone_progress_tracking.job_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='clone_progress_tracking' AND INDEX_NAME='idx_job_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_job_id ON clone_progress_tracking(job_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- clone_progress_history.job_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='clone_progress_history' AND INDEX_NAME='idx_job_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_job_id ON clone_progress_history(job_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- performance_monitoring_schedule.execution_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='performance_monitoring_schedule' AND INDEX_NAME='idx_execution_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_execution_id ON performance_monitoring_schedule(execution_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- optimization_change_log.execution_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='optimization_change_log' AND INDEX_NAME='idx_execution_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_execution_id ON optimization_change_log(execution_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- autopilot_execution_errors.record_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='autopilot_execution_errors' AND INDEX_NAME='idx_record_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_record_id ON autopilot_execution_errors(record_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- scheduled_optimizations.execution_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='scheduled_optimizations' AND INDEX_NAME='idx_execution_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_execution_id ON scheduled_optimizations(execution_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 2026_02_06_create_reinforcement_learning_tables: training_history.network_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='training_history' AND INDEX_NAME='idx_network_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_network_id ON training_history(network_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 2026_02_06_create_reinforcement_learning_tables: replay_buffer.network_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='replay_buffer' AND INDEX_NAME='idx_network_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_network_id ON replay_buffer(network_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- ml_feedback.ml_account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ml_feedback' AND INDEX_NAME='idx_ml_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_ml_account_id ON ml_feedback(ml_account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- ml_payments.ml_account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ml_payments' AND INDEX_NAME='idx_ml_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_ml_account_id ON ml_payments(ml_account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- create_ean_tables: ean_purchases.package_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ean_purchases' AND INDEX_NAME='idx_package_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_package_id ON ean_purchases(package_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- create_ean_tables: ean_assignments.purchase_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ean_assignments' AND INDEX_NAME='idx_purchase_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_purchase_id ON ean_assignments(purchase_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- create_ean_tables: ean_balances.account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ean_balances' AND INDEX_NAME='idx_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_account_id ON ean_balances(account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- create_missing_tables: ai_optimization_logs.account_id
-- ---------------------------------------------------------------------------
SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ai_optimization_logs' AND INDEX_NAME='idx_account_id');
SET @sql = IF(@idx=0,
    'CREATE INDEX idx_account_id ON ai_optimization_logs(account_id)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
