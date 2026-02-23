-- ============================================================================
-- Migration: Expand ml_orders table with fields required by Services
-- Date: 2026-02-15
-- Description: Adds account_id alias, pack_id, buyer_id, payment_reconciled,
--              external_reference, marketplace_fee, category_id columns
--              that are referenced by SettlementService, BrandCentralService,
--              AIRecommendationEngineService, PredictiveAnalyticsService, etc.
-- ============================================================================

-- account_id as alias for ml_account_id (Services use both names)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'account_id');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN account_id INT AFTER ml_account_id', 
    'SELECT ''Column account_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sync account_id from ml_account_id where null
UPDATE ml_orders SET account_id = ml_account_id WHERE account_id IS NULL;

-- Add trigger to keep account_id in sync
DROP TRIGGER IF EXISTS ml_orders_sync_account_id;
CREATE TRIGGER ml_orders_sync_account_id BEFORE INSERT ON ml_orders
FOR EACH ROW
BEGIN
    IF NEW.account_id IS NULL THEN
        SET NEW.account_id = NEW.ml_account_id;
    END IF;
END;

-- pack_id (for pack/cart orders)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'pack_id');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN pack_id BIGINT NULL COMMENT ''ML pack/cart ID'' AFTER status', 
    'SELECT ''Column pack_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- buyer_id
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'buyer_id');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN buyer_id BIGINT NULL COMMENT ''ML buyer ID'' AFTER pack_id', 
    'SELECT ''Column buyer_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- external_reference
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'external_reference');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN external_reference VARCHAR(255) NULL COMMENT ''External reference string'' AFTER buyer_id', 
    'SELECT ''Column external_reference already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- marketplace_fee
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'marketplace_fee');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN marketplace_fee DECIMAL(10,2) DEFAULT 0 COMMENT ''Marketplace fee total'' AFTER shipping_cost', 
    'SELECT ''Column marketplace_fee already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- payment_reconciled
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'payment_reconciled');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN payment_reconciled TINYINT(1) DEFAULT 0 COMMENT ''Settlement reconciled flag''', 
    'SELECT ''Column payment_reconciled already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- category_id (for cross-queries with items)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'category_id');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN category_id VARCHAR(30) NULL COMMENT ''Primary category from order items''', 
    'SELECT ''Column category_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- created_at alias (some services expect created_at instead of date_created)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND COLUMN_NAME = 'created_at');
SET @sqlstmt = IF(@col_exists = 0, 
    'ALTER TABLE ml_orders ADD COLUMN created_at DATETIME NULL COMMENT ''Alias for date_created for service compatibility''', 
    'SELECT ''Column created_at already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sync created_at from date_created where null
UPDATE ml_orders SET created_at = date_created WHERE created_at IS NULL;

-- Indexes for new columns
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_pack_id');
SET @sqlstmt = IF(@idx_exists = 0, 
    'ALTER TABLE ml_orders ADD INDEX idx_pack_id (pack_id)', 
    'SELECT ''Index idx_pack_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_buyer_id');
SET @sqlstmt = IF(@idx_exists = 0, 
    'ALTER TABLE ml_orders ADD INDEX idx_buyer_id (buyer_id)', 
    'SELECT ''Index idx_buyer_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_account_id');
SET @sqlstmt = IF(@idx_exists = 0, 
    'ALTER TABLE ml_orders ADD INDEX idx_account_id (account_id)', 
    'SELECT ''Index idx_account_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ml_orders' AND INDEX_NAME = 'idx_category_id');
SET @sqlstmt = IF(@idx_exists = 0, 
    'ALTER TABLE ml_orders ADD INDEX idx_category_id (category_id)', 
    'SELECT ''Index idx_category_id already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
