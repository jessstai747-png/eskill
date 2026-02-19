#!/bin/bash

# Backup Status Script
# Check backup health and statistics

echo "📊 Backup System Status"
echo "======================="
echo ""

# Database backups
echo "1. Database Backups"
DB_BACKUP_DIR="/var/backups/mysql"
if [ -d "$DB_BACKUP_DIR" ]; then
    DB_COUNT=$(ls -1 ${DB_BACKUP_DIR}/*.sql.gz 2>/dev/null | wc -l)
    echo "   Total backups: ${DB_COUNT}"
    
    if [ $DB_COUNT -gt 0 ]; then
        LATEST=$(ls -t ${DB_BACKUP_DIR}/*.sql.gz 2>/dev/null | head -1)
        LATEST_DATE=$(stat -c %y "$LATEST" | cut -d' ' -f1)
        LATEST_SIZE=$(du -h "$LATEST" | cut -f1)
        echo "   Latest: ${LATEST_DATE} (${LATEST_SIZE})"
        
        # Check if backup is recent (< 48h)
        LATEST_AGE=$(( ($(date +%s) - $(stat -c %Y "$LATEST")) / 3600 ))
        if [ $LATEST_AGE -lt 48 ]; then
            echo "   Status: ✅ Recent backup found"
        else
            echo "   Status: ⚠️  Backup is ${LATEST_AGE}h old"
        fi
    else
        echo "   Status: ❌ No backups found"
    fi
else
    echo "   Status: ❌ Backup directory not found"
fi

echo ""

# File backups
echo "2. File Backups"
FILE_BACKUP_DIR="/var/backups/files"
if [ -d "$FILE_BACKUP_DIR" ]; then
    FILE_COUNT=$(ls -1 ${FILE_BACKUP_DIR}/*.tar.gz 2>/dev/null | wc -l)
    echo "   Total backups: ${FILE_COUNT}"
    
    if [ $FILE_COUNT -gt 0 ]; then
        LATEST=$(ls -t ${FILE_BACKUP_DIR}/*.tar.gz 2>/dev/null | head -1)
        LATEST_DATE=$(stat -c %y "$LATEST" | cut -d' ' -f1)
        LATEST_SIZE=$(du -h "$LATEST" | cut -f1)
        echo "   Latest: ${LATEST_DATE} (${LATEST_SIZE})"
    fi
else
    echo "   Status: ❌ Backup directory not found"
fi

echo ""

# Cron jobs
echo "3. Scheduled Backups"
if crontab -l 2>/dev/null | grep -q "backup-database.sh"; then
    echo "   ✅ Database backup scheduled"
else
    echo "   ❌ Database backup not scheduled"
fi

if crontab -l 2>/dev/null | grep -q "backup-files.sh"; then
    echo "   ✅ File backup scheduled"
else
    echo "   ❌ File backup not scheduled"
fi

echo ""

# Disk space
echo "4. Disk Space"
df -h /var/backups 2>/dev/null || df -h /var

echo ""
echo "======================="
echo "Backup status check complete"
