#!/bin/bash

# File Backup Script
# Backup uploads, logs, and configuration files

set -e

# Configuration
BACKUP_DIR="/var/backups/files"
RETENTION_DAYS=7
APP_DIR="/home/eskill/htdocs/eskill.com.br"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/files_${DATE}.tar.gz"

echo "📁 Starting file backup"

# Create backup directory
mkdir -p ${BACKUP_DIR}

# Directories to backup
DIRS_TO_BACKUP=(
    "storage/uploads"
    "storage/logs"
    ".env"
    "config"
)

# Create tar archive with compression
cd ${APP_DIR}
tar -czf ${BACKUP_FILE} \
    --exclude='*.log' \
    --exclude='cache/*' \
    --exclude='sessions/*' \
    ${DIRS_TO_BACKUP[@]} 2>/dev/null || true

# Check if backup was successful
if [ -f "${BACKUP_FILE}" ]; then
    SIZE=$(du -h ${BACKUP_FILE} | cut -f1)
    echo "✅ Backup created: ${BACKUP_FILE} (${SIZE})"
else
    echo "❌ Backup failed!"
    exit 1
fi

# Apply retention policy
echo "🧹 Cleaning old backups (retention: ${RETENTION_DAYS} days)"
find ${BACKUP_DIR} -name "files_*.tar.gz" -mtime +${RETENTION_DAYS} -delete

# Count remaining backups
BACKUP_COUNT=$(ls -1 ${BACKUP_DIR}/files_*.tar.gz 2>/dev/null | wc -l)
echo "📦 Total backups: ${BACKUP_COUNT}"

echo "✅ File backup complete!"
