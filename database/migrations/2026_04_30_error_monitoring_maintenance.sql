-- Migration: Error Monitoring — maintenance event + index for pruning
-- Adds a scheduled MySQL event that purges error_monitoring rows older than 30 days.
-- Also adds a covering index on (created_at, resolved) to make the cleanup fast.
-- Run once; safe to re-run (IF NOT EXISTS guards).

-- 1. Index to speed up date-range deletes
ALTER TABLE `error_monitoring`
    ADD INDEX IF NOT EXISTS `idx_created_at_resolved` (`created_at`, `resolved`);

-- 2. Scheduled event: purge rows older than 30 days every day at 02:30
-- Requires event scheduler ON: SET GLOBAL event_scheduler = ON;
-- Or add event_scheduler=ON in /etc/mysql/my.cnf under [mysqld]
DROP EVENT IF EXISTS `evt_prune_error_monitoring`;

CREATE EVENT `evt_prune_error_monitoring`
    ON SCHEDULE EVERY 1 DAY
    STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 2 HOUR + INTERVAL 30 MINUTE)
    ON COMPLETION PRESERVE
    ENABLE
    COMMENT 'Purge error_monitoring rows older than 30 days to prevent table bloat'
DO
    DELETE FROM `error_monitoring`
    WHERE `created_at` < NOW() - INTERVAL 30 DAY
    LIMIT 5000;
-- LIMIT 5000 per run avoids long table locks; re-runs daily until caught up.
