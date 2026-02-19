#!/bin/bash

# Restore Test Script
# Monthly test to ensure backups can be restored

set -e

BACKUP_DIR="/var/backups/mysql"
DB_NAME="${DB_NAME:-meli}"
TEST_DB="${DB_NAME}_restore_test"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"

echo "🧪 Testing backup restore capability"

# Get latest backup
LATEST_BACKUP=$(ls -t ${BACKUP_DIR}/${DB_NAME}_*.sql.gz 2>/dev/null | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "❌ No backups found!"
    exit 1
fi

echo "📦 Testing backup: ${LATEST_BACKUP}"

# Create test database
echo "🗄️  Creating test database: ${TEST_DB}"
mysql --user=${DB_USER} --password=${DB_PASS} -e "DROP DATABASE IF EXISTS ${TEST_DB};"
mysql --user=${DB_USER} --password=${DB_PASS} -e "CREATE DATABASE ${TEST_DB};"

# Restore to test database
echo "🔄 Restoring to test database..."
gunzip < ${LATEST_BACKUP} | mysql --user=${DB_USER} --password=${DB_PASS} ${TEST_DB}

# Verify restore
TABLE_COUNT=$(mysql --user=${DB_USER} --password=${DB_PASS} -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TEST_DB}';")

if [ "$TABLE_COUNT" -gt 0 ]; then
    echo "✅ Restore test PASSED!"
    echo "   Tables restored: ${TABLE_COUNT}"
else
    echo "❌ Restore test FAILED!"
    echo "   No tables found in restored database"
    exit 1
fi

# Cleanup test database
echo "🧹 Cleaning up test database"
mysql --user=${DB_USER} --password=${DB_PASS} -e "DROP DATABASE ${TEST_DB};"

echo "✅ Restore test complete!"
