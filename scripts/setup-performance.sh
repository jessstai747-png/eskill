#!/bin/bash

# Performance Optimization Setup Script
# Run with sudo

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}⚡ Setting up performance optimizations${NC}"

if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Please run as root (sudo)${NC}"
  exit 1
fi

# Function to backup file
backup_file() {
    local file=$1
    if [ -f "$file" ]; then
        echo -e "   📦 Backing up $(basename $file)..."
        cp "$file" "${file}.bak.$(date +%F_%T)"
    fi
}

# 1. Enable OPcache
echo -e "\n${YELLOW}1. Configuring OPcache (PHP 8.4)...${NC}"
OPCACHE_CONF="/etc/php/8.4/fpm/conf.d/10-opcache.ini"
if [ -f "config/opcache.ini" ]; then
    # Ensure directory exists
    if [ -d "/etc/php/8.4/fpm/conf.d" ]; then
        backup_file "$OPCACHE_CONF"
        cp config/opcache.ini "$OPCACHE_CONF"
        echo -e "   ✅ OPcache configured"
    else
        echo -e "   ${RED}❌ PHP 8.4 FPM conf.d not found${NC}"
    fi
else
    echo -e "   ${RED}❌ config/opcache.ini source not found${NC}"
fi

# 2. Configure Redis for sessions
echo -e "\n${YELLOW}2. Configuring Redis for sessions (PHP 8.4)...${NC}"
PHP_INI="/etc/php/8.4/fpm/php.ini"
if [ -f "$PHP_INI" ]; then
    backup_file "$PHP_INI"
    
    # Check if already configured
    if grep -q "session.save_handler = redis" "$PHP_INI"; then
        echo -e "   ℹ️  Redis session handler already configured"
    else
        echo -e "\n; Redis Session Handler" >> "$PHP_INI"
        echo "session.save_handler = redis" >> "$PHP_INI"
        echo 'session.save_path = "tcp://127.0.0.1:6379?database=1"' >> "$PHP_INI"
        echo -e "   ✅ Redis session handler added to php.ini"
    fi
else
    echo -e "   ${RED}❌ php.ini not found${NC}"
fi

# 3. PHP-FPM Optimization
echo -e "\n${YELLOW}3. Optimizing PHP-FPM (Pool: eskill.com.br)...${NC}"
POOL_CONF="/etc/php/8.4/fpm/pool.d/eskill.com.br.conf"

if [ -f "$POOL_CONF" ]; then
    backup_file "$POOL_CONF"
    
    # We will append optimizations if not present, avoiding complete overwrite 
    # to preserve user/group/listen settings which are custom here.
    
    # Check if we already tuned it
    if grep -q "php_value[memory_limit]" "$POOL_CONF"; then
         echo -e "   ℹ️  Pool seems already tuned (memory_limit found)"
    else
         echo -e "\n; Performance Tuning" >> "$POOL_CONF"
         echo "php_value[memory_limit] = 512M" >> "$POOL_CONF"
         echo "php_value[opcache.jit_buffer_size] = 100M" >> "$POOL_CONF"
         echo "php_value[opcache.jit] = 1255" >> "$POOL_CONF"
         echo -e "   ✅ Added memory and JIT settings to pool"
    fi
else
    echo -e "   ${RED}❌ Pool config $POOL_CONF not found${NC}"
fi

# 4. Restart services
echo -e "\n${YELLOW}4. Restarting services...${NC}"
if systemctl restart php8.4-fpm; then
    echo -e "   ✅ PHP 8.4 FPM restarted"
else
    echo -e "   ${RED}❌ Failed to restart PHP-FPM${NC}"
fi

if systemctl restart nginx; then
    echo -e "   ✅ Nginx restarted"
else
    echo -e "   ${RED}❌ Failed to restart Nginx${NC}"
fi

echo -e "\n${GREEN}✅ Performance optimization complete!${NC}\n"
