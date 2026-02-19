#!/bin/bash

# ====================================
# CONFIGURADOR DE SEGURANÇA
# Firewall UFW + Fail2ban
# ====================================

set -e

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

info() {
    echo -e "${BLUE}[NOTA]${NC} $1"
}

echo "======================================"
echo "🔒 CONFIGURADOR DE SEGURANÇA"
echo "======================================"

# Verificar se está rodando como root
if [ "$EUID" -ne 0 ]; then
    error "Execute este script como root: sudo $0"
fi

# Menu principal
echo ""
echo "Escolha o que configurar:"
echo ""
echo "1) Configurar tudo (UFW + Fail2ban + Headers)"
echo "2) Apenas Firewall (UFW)"
echo "3) Apenas Fail2ban"
echo "4) Apenas Headers de segurança"
echo "5) Verificar status atual"
echo "6) Sair"
echo ""
read -p "Opção [1-6]: " OPTION

case "$OPTION" in
    1)
        INSTALL_UFW=true
        INSTALL_FAIL2BAN=true
        CONFIGURE_HEADERS=true
        ;;
    2)
        INSTALL_UFW=true
        INSTALL_FAIL2BAN=false
        CONFIGURE_HEADERS=false
        ;;
    3)
        INSTALL_UFW=false
        INSTALL_FAIL2BAN=true
        CONFIGURE_HEADERS=false
        ;;
    4)
        INSTALL_UFW=false
        INSTALL_FAIL2BAN=false
        CONFIGURE_HEADERS=true
        ;;
    5)
        echo ""
        log "STATUS DE SEGURANÇA"
        echo "================================"
        
        echo ""
        echo "🔥 FIREWALL (UFW):"
        if command -v ufw &> /dev/null; then
            ufw status verbose
        else
            echo "   UFW não instalado"
        fi
        
        echo ""
        echo "🛡️ FAIL2BAN:"
        if command -v fail2ban-client &> /dev/null; then
            fail2ban-client status
        else
            echo "   Fail2ban não instalado"
        fi
        
        echo ""
        echo "📄 HEADERS (.htaccess):"
        HTACCESS_FILE="/home/eskill/htdocs/eskill.com.br/public/.htaccess"
        if [ -f "$HTACCESS_FILE" ]; then
            if grep -q "Strict-Transport-Security" "$HTACCESS_FILE"; then
                echo "   ✅ HSTS configurado"
            else
                echo "   ❌ HSTS não configurado"
            fi
            if grep -q "X-Frame-Options" "$HTACCESS_FILE"; then
                echo "   ✅ X-Frame-Options configurado"
            else
                echo "   ❌ X-Frame-Options não configurado"
            fi
        else
            echo "   .htaccess não encontrado"
        fi
        
        exit 0
        ;;
    6)
        log "Saindo..."
        exit 0
        ;;
    *)
        error "Opção inválida!"
        ;;
esac

# ====================================
# CONFIGURAR UFW (FIREWALL)
# ====================================

if [ "$INSTALL_UFW" = true ]; then
    log "Configurando UFW (Firewall)..."
    
    # Instalar UFW se não estiver instalado
    if ! command -v ufw &> /dev/null; then
        log "Instalando UFW..."
        apt-get update
        apt-get install -y ufw
    fi
    
    # Configuração básica
    log "Configurando regras do firewall..."
    
    # Resetar regras (mantém SSH para não se trancar fora)
    echo "y" | ufw reset
    
    # Política padrão
    ufw default deny incoming
    ufw default allow outgoing
    
    # Permitir SSH (CRÍTICO!)
    log "Permitindo SSH (porta 22)..."
    ufw allow ssh
    ufw allow 22/tcp
    
    # Permitir HTTP e HTTPS
    log "Permitindo HTTP (80) e HTTPS (443)..."
    ufw allow 80/tcp
    ufw allow 443/tcp
    
    # Permitir MySQL apenas localhost (não expor para fora)
    # ufw allow from 127.0.0.1 to any port 3306
    
    # Rate limiting para SSH (proteção contra brute force)
    log "Configurando rate limiting para SSH..."
    ufw limit ssh/tcp
    
    # Ativar firewall
    log "Ativando UFW..."
    echo "y" | ufw enable
    
    # Status
    ufw status verbose
    
    log "✅ UFW configurado com sucesso!"
fi

# ====================================
# CONFIGURAR FAIL2BAN
# ====================================

if [ "$INSTALL_FAIL2BAN" = true ]; then
    log "Configurando Fail2ban..."
    
    # Instalar Fail2ban se não estiver instalado
    if ! command -v fail2ban-client &> /dev/null; then
        log "Instalando Fail2ban..."
        apt-get update
        apt-get install -y fail2ban
    fi
    
    # Criar configuração local
    log "Criando configuração Fail2ban..."
    
    cat << 'EOF' > /etc/fail2ban/jail.local
# ====================================
# FAIL2BAN - CONFIGURAÇÃO LOCAL
# Mercado Livre Manager
# ====================================

[DEFAULT]
# Banimento padrão
bantime = 1h
findtime = 10m
maxretry = 5

# Email para notificações (opcional)
destemail = root@localhost
sender = fail2ban@localhost
mta = sendmail

# Ação padrão
action = %(action_)s

# Ignorar localhost
ignoreip = 127.0.0.1/8 ::1

# ====================================
# PROTEÇÃO SSH
# ====================================
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 6h

# ====================================
# PROTEÇÃO APACHE
# ====================================
[apache-auth]
enabled = true
port = http,https
filter = apache-auth
logpath = /var/log/apache*/*error.log
maxretry = 5

[apache-badbots]
enabled = true
port = http,https
filter = apache-badbots
logpath = /var/log/apache*/*access.log
maxretry = 2
bantime = 2d

[apache-noscript]
enabled = true
port = http,https
filter = apache-noscript
logpath = /var/log/apache*/*error.log
maxretry = 3

[apache-overflows]
enabled = true
port = http,https
filter = apache-overflows
logpath = /var/log/apache*/*error.log
maxretry = 2
bantime = 1d

# ====================================
# PROTEÇÃO NGINX (se usar)
# ====================================
[nginx-http-auth]
enabled = false
port = http,https
filter = nginx-http-auth
logpath = /var/log/nginx/error.log
maxretry = 5

[nginx-botsearch]
enabled = false
port = http,https
filter = nginx-botsearch
logpath = /var/log/nginx/access.log
maxretry = 2

# ====================================
# PROTEÇÃO PHP/APP CUSTOMIZADA
# ====================================
[ml-manager-auth]
enabled = true
port = http,https
filter = ml-manager-auth
logpath = /home/eskill/htdocs/eskill.com.br/storage/logs/*.log
maxretry = 5
bantime = 30m
findtime = 10m
EOF

    # Criar filtro customizado para a aplicação
    log "Criando filtro customizado para ML Manager..."
    
    cat << 'EOF' > /etc/fail2ban/filter.d/ml-manager-auth.conf
# Filtro Fail2ban para Mercado Livre Manager
# Detecta tentativas de login falhas

[Definition]

failregex = ^.*Login failed for .* from <HOST>.*$
            ^.*Authentication failed .* IP: <HOST>.*$
            ^.*Invalid credentials from <HOST>.*$
            ^.*Brute force attempt from <HOST>.*$
            ^.*Too many login attempts from <HOST>.*$

ignoreregex =
EOF

    # Reiniciar Fail2ban
    log "Reiniciando Fail2ban..."
    systemctl enable fail2ban
    systemctl restart fail2ban
    
    # Status
    fail2ban-client status
    
    log "✅ Fail2ban configurado com sucesso!"
fi

# ====================================
# CONFIGURAR HEADERS DE SEGURANÇA
# ====================================

if [ "$CONFIGURE_HEADERS" = true ]; then
    log "Configurando headers de segurança..."
    
    HTACCESS_FILE="/home/eskill/htdocs/eskill.com.br/public/.htaccess"
    HTACCESS_BACKUP="${HTACCESS_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Backup do .htaccess existente
    if [ -f "$HTACCESS_FILE" ]; then
        log "Fazendo backup do .htaccess existente..."
        cp "$HTACCESS_FILE" "$HTACCESS_BACKUP"
    fi
    
    # Adicionar headers de segurança
    log "Adicionando headers de segurança ao .htaccess..."
    
    cat << 'EOF' >> "$HTACCESS_FILE"

# ====================================
# HEADERS DE SEGURANÇA
# ====================================

<IfModule mod_headers.c>
    # HSTS - Força HTTPS por 1 ano
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    
    # Prevenir clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # Proteção XSS
    Header always set X-XSS-Protection "1; mode=block"
    
    # Prevenir MIME type sniffing
    Header always set X-Content-Type-Options "nosniff"
    
    # Política de referência
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Política de permissões
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    
    # Content Security Policy (básica)
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src 'self' https://api.mercadolibre.com https://api.mercadolivre.com.br"
    
    # Remover header de versão do servidor
    Header always unset X-Powered-By
    Header always unset Server
</IfModule>

# ====================================
# PROTEÇÃO DE ARQUIVOS SENSÍVEIS
# ====================================

# Bloquear acesso a arquivos de configuração
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

<FilesMatch "(^#.*#|\.(bak|config|dist|fla|in[ci]|log|psd|sh|sql|sw[op])|~)$">
    Require all denied
</FilesMatch>

# Bloquear .env
<Files ".env">
    Require all denied
</Files>

# Bloquear composer.json/lock
<Files "composer.*">
    Require all denied
</Files>

# ====================================
# PROTEÇÃO CONTRA ATAQUES
# ====================================

# Bloquear requisições com user-agents suspeitos
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Bloquear bots maliciosos conhecidos
    RewriteCond %{HTTP_USER_AGENT} (nikto|sqlmap|nmap|sqlninja|paros|w3af|acunetix|havij) [NC]
    RewriteRule .* - [F,L]
    
    # Bloquear tentativas de SQL injection na URL
    RewriteCond %{QUERY_STRING} (\<|%3C).*script.*(\>|%3E) [NC,OR]
    RewriteCond %{QUERY_STRING} GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
    RewriteCond %{QUERY_STRING} _REQUEST(=|\[|\%[0-9A-Z]{0,2})
    RewriteRule .* - [F,L]
</IfModule>

# ====================================
# FORÇA HTTPS (PRODUÇÃO)
# ====================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteCond %{HTTP_HOST} !^localhost
    RewriteCond %{HTTP_HOST} !^127\.0\.0\.1
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
EOF

    log "✅ Headers de segurança configurados!"
    
    # Habilitar mod_headers no Apache se necessário
    if command -v a2enmod &> /dev/null; then
        log "Habilitando mod_headers no Apache..."
        a2enmod headers
        systemctl reload apache2 2>/dev/null || true
    fi
fi

# ====================================
# CONFIGURAÇÕES ADICIONAIS
# ====================================

log "Aplicando configurações adicionais de segurança..."

# Desabilitar assinatura do servidor
if [ -f /etc/apache2/conf-available/security.conf ]; then
    sed -i 's/ServerTokens OS/ServerTokens Prod/' /etc/apache2/conf-available/security.conf
    sed -i 's/ServerSignature On/ServerSignature Off/' /etc/apache2/conf-available/security.conf
    systemctl reload apache2 2>/dev/null || true
fi

# ====================================
# RELATÓRIO FINAL
# ====================================

echo ""
echo "======================================"
echo "✅ CONFIGURAÇÃO DE SEGURANÇA CONCLUÍDA!"
echo "======================================"
echo ""
echo "📋 RESUMO:"

if [ "$INSTALL_UFW" = true ]; then
    echo "   🔥 UFW: Ativado"
    echo "      - SSH (22): Permitido com rate limit"
    echo "      - HTTP (80): Permitido"
    echo "      - HTTPS (443): Permitido"
fi

if [ "$INSTALL_FAIL2BAN" = true ]; then
    echo "   🛡️ Fail2ban: Ativado"
    echo "      - SSH: 3 tentativas, ban 6h"
    echo "      - Apache: 5 tentativas, ban 1h"
    echo "      - ML Manager: 5 tentativas, ban 30m"
fi

if [ "$CONFIGURE_HEADERS" = true ]; then
    echo "   📄 Headers: Configurados"
    echo "      - HSTS: Ativo (1 ano)"
    echo "      - X-Frame-Options: SAMEORIGIN"
    echo "      - CSP: Configurada"
fi

echo ""
echo "🔍 COMANDOS ÚTEIS:"
echo "   ufw status               # Ver status do firewall"
echo "   fail2ban-client status   # Ver status do Fail2ban"
echo "   fail2ban-client status sshd  # Ver bans SSH"
echo "   fail2ban-client unban IP # Desbanir IP"
echo ""
echo "📖 DOCUMENTAÇÃO:"
echo "   docs/SECURITY_HARDENING.md"
echo ""

log "🎉 Segurança configurada com sucesso!"