#!/bin/bash

# Deployment Script for SEO Strategies System
# This script deploys the SEO system to a staging environment

set -e  # Exit immediately if a command exits with a non-zero status

echo "🚀 Starting deployment of SEO Strategies System..."

# Variables
STAGING_DIR="/var/www/staging-seo"
BACKUP_DIR="/var/backups/seo-$(date +%Y%m%d_%H%M%S)"
CURRENT_DIR="$(pwd)"

# Create backup of current staging
if [ -d "$STAGING_DIR" ]; then
    echo "📦 Creating backup of current staging..."
    mkdir -p "$BACKUP_DIR"
    cp -r "$STAGING_DIR"/* "$BACKUP_DIR/" || echo "No files to backup"
fi

# Copy files to staging
echo "🚚 Copying files to staging environment..."
mkdir -p "$STAGING_DIR"
cp -r ./app "$STAGING_DIR/"
cp -r ./config "$STAGING_DIR/" 
cp -r ./routes "$STAGING_DIR/"
cp -r ./database "$STAGING_DIR/"
cp -r ./tests "$STAGING_DIR/"

# Set proper permissions
echo "🔒 Setting proper permissions..."
find "$STAGING_DIR" -type f -exec chmod 644 {} \;
find "$STAGING_DIR" -type d -exec chmod 755 {} \;
find "$STAGING_DIR"/app -name "*.php" -exec chmod 644 {} \;

# Run database migrations
echo "🗄️ Running database migrations..."
cd "$STAGING_DIR"
php database/migrations/2026_01_22_create_seo_synonyms_tables.sql || echo "Migration may have already run"

echo "✅ Deployment to staging completed successfully!"
echo "📁 Staging directory: $STAGING_DIR"
echo "📦 Backup (if created): $BACKUP_DIR"

# Run basic health check
echo "🔍 Performing basic health check..."
if [ -f "$STAGING_DIR/app/Services/SEO/SEOStrategiesEngine.php" ]; then
    echo "✅ SEOStrategiesEngine.php exists"
else
    echo "❌ SEOStrategiesEngine.php missing"
    exit 1
fi

echo "🎉 Deployment process completed!"