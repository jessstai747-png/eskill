#!/bin/bash

# ====================================
# SCRIPT DE CONFIGURAÇÃO PHP PRODUÇÃO
# ====================================

set -e

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo "======================================"
echo "🐘 CONFIGURAÇÃO PHP PARA PRODUÇÃO"
echo "======================================"

# Detectar versão do PHP
PHP_VERSION=$(php -v | head -n1 | cut -d" " -f2 | cut -d"." -f1-2)
log "PHP Version: $PHP_VERSION"

# Detectar SAPI (apache2 ou fpm)
if [ -d "/etc/php/$PHP_VERSION/apache2" ]; then
    SAPI="apache2"
    PHP_INI="/etc/php/$PHP_VERSION/apache2/php.ini"
elif [ -d "/etc/php/$PHP_VERSION/fpm" ]; then
    SAPI="fpm"
    PHP_INI="/etc/php/$PHP_VERSION/fpm/php.ini"
else
    error "SAPI não detectado! Verifique instalação do PHP."
fi

log "SAPI detectado: $SAPI"
log "Arquivo php.ini: $PHP_INI"

# Verificar se o arquivo existe
if [ ! -f "$PHP_INI" ]; then
    error "Arquivo php.ini não encontrado: $PHP_INI"
fi

# Fazer backup do php.ini original
BACKUP_FILE="/tmp/php.ini.backup.$(date +%Y%m%d_%H%M%S)"
log "Fazendo backup: $BACKUP_FILE"
sudo cp "$PHP_INI" "$BACKUP_FILE"

# Função para configurar valor no php.ini
set_php_config() {
    local key="$1"
    local value="$2"
    local file="$PHP_INI"
    
    # Verificar se a configuração já existe
    if grep -q "^$key\s*=" "$file"; then
        # Atualizar valor existente
        sudo sed -i "s/^$key\s*=.*/$key = $value/" "$file"
        log "Atualizado: $key = $value"
    elif grep -q "^;\s*$key\s*=" "$file"; then
        # Descomentar e definir valor
        sudo sed -i "s/^;\s*$key\s*=.*/$key = $value/" "$file"
        log "Ativado: $key = $value"
    else
        # Adicionar nova configuração
        echo "$key = $value" | sudo tee -a "$file" > /dev/null
        log "Adicionado: $key = $value"
    fi
}

echo ""
log "Aplicando configurações de produção..."

# ====================================
# CONFIGURAÇÕES DE SEGURANÇA
# ====================================

# Ocultar versão do PHP
set_php_config "expose_php" "Off"

# Desabilitar exibição de erros
set_php_config "display_errors" "Off"
set_php_config "display_startup_errors" "Off"

# Habilitar log de erros
set_php_config "log_errors" "On"
set_php_config "error_log" "/var/log/php_errors.log"

# Configurar nível de erros
set_php_config "error_reporting" "E_ALL & ~E_DEPRECATED & ~E_STRICT"

# ====================================
# CONFIGURAÇÕES DE PERFORMANCE
# ====================================

# Memória
set_php_config "memory_limit" "256M"

# Timeouts
set_php_config "max_execution_time" "60"
set_php_config "max_input_time" "60"

# Upload
set_php_config "upload_max_filesize" "10M"
set_php_config "post_max_size" "10M"

# ====================================
# CONFIGURAÇÕES DE SESSÃO
# ====================================

# Segurança de sessão
set_php_config "session.cookie_httponly" "1"
set_php_config "session.cookie_secure" "1"
set_php_config "session.use_strict_mode" "1"
set_php_config "session.cookie_samesite" "Strict"

# ====================================
# EXTENSÕES NECESSÁRIAS
# ====================================

log "Verificando extensões..."

REQUIRED_EXTENSIONS=(
    "pdo"
    "pdo_mysql" 
    "curl"
    "json"
    "mbstring"
    "openssl"
    "xml"
    "zip"
    "gd"
)

MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "$ext"; then
        log "✅ $ext"
    else
        error "❌ $ext (FALTANDO)"
        MISSING_EXTENSIONS+=("$ext")
    fi
done

# ====================================
# INSTALAR EXTENSÕES FALTANDO
# ====================================

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    warning "Extensões faltando: ${MISSING_EXTENSIONS[*]}"
    echo "Deseja instalar automaticamente? (y/n)"
    read -r response
    
    if [[ "$response" =~ ^[Yy]$ ]]; then
        log "Instalando extensões..."
        
        for ext in "${MISSING_EXTENSIONS[@]}"; do
            case "$ext" in
                "pdo_mysql")
                    sudo apt install -y php$PHP_VERSION-mysql
                    ;;
                "curl")
                    sudo apt install -y php$PHP_VERSION-curl
                    ;;
                "mbstring")
                    sudo apt install -y php$PHP_VERSION-mbstring
                    ;;
                "xml")
                    sudo apt install -y php$PHP_VERSION-xml
                    ;;
                "zip")
                    sudo apt install -y php$PHP_VERSION-zip
                    ;;
                "gd")
                    sudo apt install -y php$PHP_VERSION-gd
                    ;;
                *)
                    warning "Não sei como instalar: $ext"
                    ;;
            esac
        done
    fi
fi

# ====================================
# CONFIGURAR LOG
# ====================================

log "Configurando arquivo de log..."

# Criar arquivo de log se não existir
sudo touch /var/log/php_errors.log
sudo chown www-data:www-data /var/log/php_errors.log
sudo chmod 640 /var/log/php_errors.log

# Configurar logrotate
cat << 'EOF' | sudo tee /etc/logrotate.d/php-errors > /dev/null
/var/log/php_errors.log {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    create 640 www-data www-data
    postrotate
        # Recarregar serviço web
        if [ -f /var/run/apache2/apache2.pid ]; then
            service apache2 reload > /dev/null
        fi
        if [ -f /var/run/php/php8.1-fpm.pid ]; then
            service php8.1-fpm reload > /dev/null
        fi
    endscript
}
EOF

log "Configuração de logrotate criada"

# ====================================
# REINICIAR SERVIÇOS
# ====================================

echo ""
log "Reiniciando serviços..."

if [ "$SAPI" = "apache2" ]; then
    sudo systemctl reload apache2
    log "Apache recarregado"
elif [ "$SAPI" = "fpm" ]; then
    sudo systemctl reload php$PHP_VERSION-fpm
    log "PHP-FPM recarregado"
    
    # Se Nginx estiver rodando
    if systemctl is-active --quiet nginx; then
        sudo systemctl reload nginx
        log "Nginx recarregado"
    fi
fi

# ====================================
# VERIFICAÇÃO FINAL
# ====================================

echo ""
log "Verificação final..."

# Verificar se as configurações foram aplicadas
echo "Configurações aplicadas:"
echo "  expose_php = $(php -r "echo ini_get('expose_php') ? 'On' : 'Off';")"
echo "  display_errors = $(php -r "echo ini_get('display_errors') ? 'On' : 'Off';")" 
echo "  log_errors = $(php -r "echo ini_get('log_errors') ? 'On' : 'Off';")"
echo "  memory_limit = $(php -r "echo ini_get('memory_limit');")"
echo "  session.cookie_secure = $(php -r "echo ini_get('session.cookie_secure');")"

echo ""
echo "======================================"
echo "✅ CONFIGURAÇÃO PHP CONCLUÍDA!"
echo "======================================"
echo ""
echo "📋 RESUMO:"
echo "   - Backup original: $BACKUP_FILE"
echo "   - PHP.ini configurado: $PHP_INI"
echo "   - Log de erros: /var/log/php_errors.log"
echo "   - Logrotate configurado"
echo ""
echo "🔍 VERIFICAÇÃO:"
echo "   - Teste se o site continua funcionando"
echo "   - Monitore logs: tail -f /var/log/php_errors.log"
echo "   - Verifique headers: curl -I https://seudominio.com"
echo ""

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    warning "⚠️  Algumas extensões ainda podem estar faltando!"
fi

log "🎉 Configuração PHP finalizada!"