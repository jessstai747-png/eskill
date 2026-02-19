#!/bin/bash

# Installation script for Mercado Livre Manager
# Validates environment, installs dependencies, runs migrations, configures crons.

set -e

echo "🚀 Installing Mercado Livre Manager..."
echo

# ============================================================================
# 1. PRE-REQUISITES
# ============================================================================

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.0 or higher."
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION_ID;")
if [ "$PHP_VERSION" -lt 80000 ]; then
    echo "❌ PHP version is too low. Please upgrade to PHP 8.0 or higher."
    exit 1
fi

echo "✅ PHP version $(php -v | head -n1 | cut -d' ' -f2) is installed."

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer."
    echo "Installation guide: https://getcomposer.org/download/"
    exit 1
fi

echo "✅ Composer is installed."

# ============================================================================
# 2. ENVIRONMENT
# ============================================================================

# Check if .env file exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from example..."
    cp .env.example .env
    echo "⚠️  .env file created. Configure your credentials before continuing."
    echo "   Edit .env and re-run this script."
    exit 1
fi

echo "✅ .env file exists."

# Validate APP_KEY
APP_KEY=$(grep -oP '^APP_KEY=\K.*' .env 2>/dev/null || echo "")
APP_KEY_LEN=${#APP_KEY}
if [ "$APP_KEY_LEN" -lt 32 ]; then
    echo "❌ APP_KEY deve ter pelo menos 32 caracteres (atual: ${APP_KEY_LEN})."
    echo "   Gere uma chave: php -r \"echo bin2hex(random_bytes(32)) . PHP_EOL;\""
    echo "   Adicione ao .env: APP_KEY=<chave_gerada>"
    exit 1
fi

echo "✅ APP_KEY validada (${APP_KEY_LEN} chars)."

# Validate DB credentials
DB_PASS=$(grep -oP '^DB_PASS(WORD)?=\K.*' .env 2>/dev/null | head -1 || echo "")
if [ -z "$DB_PASS" ] || [ "$DB_PASS" = "CHANGE_ME" ]; then
    echo "❌ DB_PASSWORD/DB_PASS não configurado no .env (ou ainda é 'CHANGE_ME')."
    exit 1
fi

echo "✅ Credenciais de DB configuradas."

# ============================================================================
# 3. DEPENDENCIES
# ============================================================================

echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo "✅ Dependencies installed successfully."
else
    echo "❌ Failed to install dependencies."
    exit 1
fi

# ============================================================================
# 4. STORAGE DIRECTORIES
# ============================================================================

echo "📁 Ensuring storage directories..."
mkdir -p storage/logs storage/cache storage/sessions
chmod -R 775 storage
echo "✅ Storage directories ready."

# ============================================================================
# 5. DATABASE MIGRATIONS
# ============================================================================

echo "💾 Running database migrations..."
php bin/migrate.php

if [ $? -eq 0 ]; then
    echo "✅ Migrations applied successfully."
else
    echo "❌ Migration errors found. Check output above."
    exit 1
fi

# ============================================================================
# 6. CRONTAB
# ============================================================================

echo "⏰ Crontab configuration..."
if [ -f update_crontab.sh ]; then
    echo "   Found update_crontab.sh — run it manually to install crons:"
    echo "   bash update_crontab.sh"
else
    echo "   Install crons manually from current_crontab:"
    echo "   crontab current_crontab"
fi

# ============================================================================
# 7. TESTS (optional)
# ============================================================================

if [ "$1" = "--with-tests" ]; then
    echo "🧪 Running tests..."
    php vendor/bin/phpunit
    if [ $? -eq 0 ]; then
        echo "✅ All tests passed."
    else
        echo "⚠️  Some tests failed. Review output above."
    fi
fi

# ============================================================================
# DONE
# ============================================================================

echo
echo "🎉 Installation completed!"
echo
echo "📋 Next steps:"
echo "1. Review storage/logs/ for any migration warnings"
echo "2. Install crontab: crontab current_crontab"
echo "3. Configure HTTPS and secure cookies in production"
echo "4. Run security scan: php vendor/bin/phpunit && trivy fs --severity HIGH,CRITICAL ."
echo "5. Start the dev server: php -S localhost:8000 -t public"
echo