-- Migration: Add shipping_cost to ml_orders
-- Date: 2026-02-25
-- Context: SettlementService references o.shipping_cost in 6 SQL queries
--          but the column was never created. The expand migration (2026_02_15)
--          tried to place marketplace_fee AFTER shipping_cost, which fails
--          without this column.

-- shipping_cost (extracted from order_data JSON at sync time)
ALTER TABLE ml_orders ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10,2) DEFAULT 0 COMMENT 'Shipping cost from ML order' AFTER total_amount;

-- Backfill from order_data JSON where available
UPDATE ml_orders 
SET shipping_cost = COALESCE(
    CAST(JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.shipping.cost')) AS DECIMAL(10,2)),
    0
)
WHERE shipping_cost IS NULL OR shipping_cost = 0;

-- Index for settlement reconciliation queries
ALTER TABLE ml_orders ADD INDEX IF NOT EXISTS idx_shipping_cost (shipping_cost);
