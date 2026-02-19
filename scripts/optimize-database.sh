#!/bin/bash

# Database Query Optimization Script
# Analyzes slow queries and suggests indexes

set -e

DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"
DB_NAME="${DB_NAME:-meli}"

echo "🔍 Database Query Optimization"
echo "=============================="
echo ""

# 1. Enable slow query log
echo "1. Enabling slow query log..."
mysql --user=${DB_USER} --password=${DB_PASS} -e "
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
"
echo "   ✅ Slow query log enabled (queries > 1s)"

# 2. Analyze table sizes
echo ""
echo "2. Table Sizes:"
mysql --user=${DB_USER} --password=${DB_PASS} ${DB_NAME} -e "
SELECT 
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    table_rows AS 'Rows'
FROM information_schema.TABLES
WHERE table_schema = '${DB_NAME}'
ORDER BY (data_length + index_length) DESC
LIMIT 10;
"

# 3. Check for missing indexes
echo ""
echo "3. Tables without Primary Key:"
mysql --user=${DB_USER} --password=${DB_PASS} ${DB_NAME} -e "
SELECT t.table_name
FROM information_schema.tables t
LEFT JOIN information_schema.table_constraints tc
    ON t.table_schema = tc.table_schema
    AND t.table_name = tc.table_name
    AND tc.constraint_type = 'PRIMARY KEY'
WHERE t.table_schema = '${DB_NAME}'
    AND tc.constraint_name IS NULL
    AND t.table_type = 'BASE TABLE';
"

# 4. Suggested indexes
echo ""
echo "4. Optimization Suggestions:"
echo "   Run these commands to add recommended indexes:"
echo ""
echo "   -- Items table"
echo "   CREATE INDEX idx_status_account ON items(status, account_id);"
echo "   CREATE INDEX idx_created ON items(created_at);"
echo ""
echo "   -- Orders table (if exists)"
echo "   CREATE INDEX idx_account_status ON orders(account_id, status);"
echo "   CREATE INDEX idx_created ON orders(created_at);"
echo ""
echo "   -- Error logs"
echo "   CREATE INDEX idx_type_created ON error_logs(type, created_at);"
echo ""

# 5. Analyze tables
echo "5. Analyzing tables..."
mysql --user=${DB_USER} --password=${DB_PASS} ${DB_NAME} -e "
SELECT CONCAT('ANALYZE TABLE ', table_name, ';') as 'Run these commands:'
FROM information_schema.tables
WHERE table_schema = '${DB_NAME}'
AND table_type = 'BASE TABLE';
" | grep -v "Run these commands" | mysql --user=${DB_USER} --password=${DB_PASS} ${DB_NAME}
echo "   ✅ Tables analyzed"

echo ""
echo "=============================="
echo "✅ Optimization analysis complete!"
echo ""
echo "Monitor slow queries: tail -f /var/log/mysql/slow-query.log"
