#!/bin/bash

# ====================================
# SCRIPT DE CONFIGURAÇÃO DE PRODUÇÃO
# Mercado Livre Manager v1.2.0
# ====================================

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para log
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

echo "======================================"
echo "🚀 CONFIGURAÇÃO DE PRODUÇÃO"
echo "======================================"

# Verificar se está rodando como root
if [ "$EUID" -eq 0 ]; then
    error "NÃO execute este script como root!"
fi

# Verificar diretório
if [ ! -f "composer.json" ]; then
    error "Execute este script na raiz do projeto!"
fi

# 1. VERIFICAR PRÉ-REQUISITOS
log "Verificando pré-requisitos..."

# Verificar PHP
if ! command -v php &> /dev/null; then
    error "PHP não encontrado! Instale PHP 8.1+"
fi

PHP_VERSION=$(php -v | head -n1 | cut -d" " -f2 | cut -d"." -f1-2)
if [[ "$(printf '%s\n' "8.1" "$PHP_VERSION" | sort -V | head -n1)" != "8.1" ]]; then
    error "PHP 8.1+ necessário. Versão atual: $PHP_VERSION"
fi

# Verificar MySQL/MariaDB
if ! command -v mysql &> /dev/null; then
    warning "MySQL não encontrado! Certifique-se de que está instalado."
fi

# Verificar extensões PHP necessárias
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "curl" "json" "mbstring" "openssl")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "$ext"; then
        error "Extensão PHP '$ext' não encontrada!"
    fi
done

info "✅ Pré-requisitos verificados"

# 2. CONFIGURAR AMBIENTE
log "Configurando ambiente de produção..."

# Criar .env se não existir
if [ ! -f ".env" ]; then
    warning "Arquivo .env não encontrado. Copiando exemplo..."
    
    if [ -f ".env.production.example" ]; then
        cp .env.production.example .env
        info "📋 .env criado a partir do exemplo de produção"
    elif [ -f ".env.example" ]; then
        cp .env.example .env
        info "📋 .env criado a partir do exemplo padrão"
    else
        error "Nenhum arquivo de exemplo encontrado!"
    fi
    
    echo ""
    echo "⚠️  IMPORTANTE: Edite o arquivo .env com suas configurações!"
    echo "   - APP_KEY (gere com: openssl rand -hex 32)"
    echo "   - APP_URL (sua URL com HTTPS)"
    echo "   - Configurações do banco de dados"
    echo "   - Credenciais do Mercado Livre"
    echo ""
    read -p "Pressione ENTER após configurar o .env..."
fi

# 3. VALIDAR .env
log "Validando configurações..."

# Carregar .env
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Verificar configurações críticas
REQUIRED_VARS=("APP_ENV" "APP_KEY" "APP_URL" "DB_HOST" "DB_NAME" "DB_USER" "DB_PASS")
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        error "Variável '$var' não definida no .env!"
    fi
done

# Verificar se é produção
if [ "$APP_ENV" != "production" ]; then
    error "APP_ENV deve ser 'production'!"
fi

# Verificar APP_DEBUG
if [ "$APP_DEBUG" != "false" ]; then
    warning "APP_DEBUG deveria ser 'false' em produção"
fi

# Verificar HTTPS
if [[ ! "$APP_URL" =~ ^https:// ]]; then
    warning "APP_URL deveria usar HTTPS em produção!"
fi

info "✅ Configurações validadas"

# 4. CRIAR DIRETÓRIOS
log "Criando diretórios necessários..."

mkdir -p storage/logs
mkdir -p storage/cache
mkdir -p storage/backups
mkdir -p storage/sessions

# 5. CONFIGURAR PERMISSÕES
log "Configurando permissões..."

# Definir proprietário correto
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data public/

# Definir permissões
chmod -R 755 storage/
chmod -R 755 public/
chmod 644 .env

info "✅ Permissões configuradas"

# 6. INSTALAR DEPENDÊNCIAS
log "Instalando dependências..."

composer install --no-dev --optimize-autoloader

info "✅ Dependências instaladas"

# 7. EXECUTAR MIGRAÇÕES
log "Executando migrações do banco..."

# Testar conexão
php scripts/test_db_connection.php

if [ $? -eq 0 ]; then
    info "✅ Conexão com banco OK"
    
    # Executar migrações
    php scripts/migrate.php
    
    if [ $? -eq 0 ]; then
        info "✅ Migrações executadas com sucesso"
    else
        error "Falha nas migrações!"
    fi
else
    error "Falha na conexão com o banco!"
fi

# 8. CONFIGURAR CACHE
log "Configurando cache..."

# Limpar cache existente
php -r "
\$cacheDir = 'storage/cache';
if (is_dir(\$cacheDir)) {
    \$files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(\$cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach (\$files as \$file) {
        \$file->isDir() ? rmdir(\$file) : unlink(\$file);
    }
}
echo 'Cache limpo\n';
"

info "✅ Cache configurado"

# 9. TESTAR APLICAÇÃO
log "Testando aplicação..."

# Verificar se a aplicação responde
if command -v curl &> /dev/null; then
    BASE_URL=${APP_URL%/}
    
    # Testar página principal
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL" || echo "000")
    
    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 302 ]; then
        info "✅ Aplicação respondendo (HTTP $HTTP_CODE)"
    else
        warning "Aplicação não responde corretamente (HTTP $HTTP_CODE)"
    fi
else
    warning "curl não encontrado - pule teste de conectividade"
fi

# 10. CONFIGURAR SSL (se Let's Encrypt disponível)
if command -v certbot &> /dev/null; then
    log "Configurando SSL..."
    
    echo "Deseja configurar SSL automático com Let's Encrypt? (y/n)"
    read -r response
    
    if [[ "$response" =~ ^[Yy]$ ]]; then
        DOMAIN=$(echo "$APP_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')
        
        info "Configurando SSL para domínio: $DOMAIN"
        
        if command -v apache2 &> /dev/null; then
            sudo certbot --apache -d "$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN"
        elif command -v nginx &> /dev/null; then
            sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN"
        else
            warning "Servidor web não detectado. Configure SSL manualmente."
        fi
    fi
else
    warning "Certbot não encontrado. Configure SSL manualmente."
fi

# 11. RELATÓRIO FINAL
echo ""
echo "======================================"
echo "✅ CONFIGURAÇÃO CONCLUÍDA!"
echo "======================================"
echo ""
echo "📋 RESUMO:"
echo "   - Ambiente: $APP_ENV"
echo "   - URL: $APP_URL"
echo "   - Banco: $DB_HOST:$DB_PORT/$DB_NAME"
echo "   - PHP: $PHP_VERSION"
echo ""
echo "📁 ARQUIVOS IMPORTANTES:"
echo "   - Configuração: .env"
echo "   - Logs: storage/logs/"
echo "   - Cache: storage/cache/"
echo "   - Backups: storage/backups/"
echo ""
echo "🔒 SEGURANÇA:"
echo "   - [ ] Configure firewall (ufw/iptables)"
echo "   - [ ] Configure fail2ban"
echo "   - [ ] Teste SSL: https://www.ssllabs.com/ssltest/"
echo "   - [ ] Monitore logs regularmente"
echo ""
echo "🚀 PRÓXIMOS PASSOS:"
echo "   1. Execute: ./scripts/health_check.php"
echo "   2. Configure backup automático"
echo "   3. Configure monitoramento"
echo "   4. Teste funcionalidades completas"
echo ""
echo "📖 DOCUMENTAÇÃO:"
echo "   - SSL: docs/SSL_SETUP_GUIDE.md"
echo "   - Produção: PREPARAR_PRODUCAO.md"
echo ""

info "🎉 Produção configurada com sucesso!"