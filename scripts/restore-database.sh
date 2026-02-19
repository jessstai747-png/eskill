#!/bin/bash

# Database Restore Script
# Restore database from backup with safety checks

set -e

# Configuration
BACKUP_DIR="/var/backups/mysql"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"
DB_NAME="${DB_NAME:-meli}"

# Function to list available backups
list_backups() {
    echo "📋 Available backups:"
    ls -lh ${BACKUP_DIR}/${DB_NAME}_*.sql.gz 2>/dev/null | awk '{print NR". "$9" ("$5")"}'
}

# Check if backup file provided
if [ -z "$1" ]; then
    list_backups
    echo ""
    echo "Usage: $0 <backup_file>"
    echo "Example: $0 ${BACKUP_DIR}/${DB_NAME}_20231223_120000.sql.gz"
    exit 1
fi

BACKUP_FILE=$1

# Verify backup file exists
if [ ! -f "${BACKUP_FILE}" ]; then
    echo "❌ Backup file not found: ${BACKUP_FILE}"
    list_backups
    exit 1
fi

# Safety confirmation
echo "⚠️  WARNING: This will REPLACE the current database!"
echo "Database: ${DB_NAME}"
echo "Backup: ${BACKUP_FILE}"
echo ""
read -p "Are you sure? (type 'yes' to confirm): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Restore cancelled"
    exit 1
fi

# Create safety backup of current database
SAFETY_BACKUP="${BACKUP_DIR}/${DB_NAME}_before_restore_$(date +%Y%m%d_%H%M%S).sql.gz"
echo "📦 Creating safety backup: ${SAFETY_BACKUP}"
mysqldump --user=${DB_USER} --password=${DB_PASS} ${DB_NAME} | gzip > ${SAFETY_BACKUP}

# Restore database
echo "🔄 Restoring database from ${BACKUP_FILE}..."
gunzip < ${BACKUP_FILE} | mysql --user=${DB_USER} --password=${DB_PASS} ${DB_NAME}

echo "✅ Database restored successfully!"
echo "💾 Safety backup saved at: ${SAFETY_BACKUP}"
