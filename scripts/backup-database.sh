#!/bin/bash

# Database Backup Script
# Automated MySQL backup with compression and retention policy

set -e

# Configuration
BACKUP_DIR="/var/backups/mysql"
RETENTION_DAYS=7
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"
DB_NAME="${DB_NAME:-meli}"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${DATE}.sql.gz"

echo "🗄️  Starting database backup: ${DB_NAME}"

# Create backup directory if not exists
mkdir -p ${BACKUP_DIR}

# Dump database with compression
mysqldump \
    --user=${DB_USER} \
    --password=${DB_PASS} \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --routines \
    --triggers \
    --events \
    ${DB_NAME} | gzip > ${BACKUP_FILE}

# Check if backup was successful
if [ -f "${BACKUP_FILE}" ]; then
    SIZE=$(du -h ${BACKUP_FILE} | cut -f1)
    echo "✅ Backup created: ${BACKUP_FILE} (${SIZE})"
else
    echo "❌ Backup failed!"
    exit 1
fi

# Apply retention policy (delete old backups)
echo "🧹 Cleaning old backups (retention: ${RETENTION_DAYS} days)"
find ${BACKUP_DIR} -name "${DB_NAME}_*.sql.gz" -mtime +${RETENTION_DAYS} -delete

# Count remaining backups
BACKUP_COUNT=$(ls -1 ${BACKUP_DIR}/${DB_NAME}_*.sql.gz 2>/dev/null | wc -l)
echo "📦 Total backups: ${BACKUP_COUNT}"

# Optional: Upload to remote storage (S3, etc)
# if command -v aws &> /dev/null; then
#     aws s3 cp ${BACKUP_FILE} s3://your-bucket/backups/mysql/
#     echo "☁️  Uploaded to S3"
# fi

echo "✅ Database backup complete!"
