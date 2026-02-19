#!/bin/bash

# ====================================
# CONFIGURADOR DE MONITORAMENTO
# Configura health checks automáticos
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

# Configurações
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HEALTH_SCRIPT="$SCRIPT_DIR/health_check_advanced.php"
USER_CRONTAB="/tmp/monitoring_crontab_$(date +%Y%m%d_%H%M%S)"

echo "======================================"
echo "📊 CONFIGURADOR DE MONITORAMENTO"
echo "======================================"

# Verificar se script de health check existe
if [ ! -f "$HEALTH_SCRIPT" ]; then
    error "Script de health check não encontrado: $HEALTH_SCRIPT"
fi

# Verificar se PHP CLI está disponível
if ! command -v php &> /dev/null; then
    error "PHP CLI não encontrado!"
fi

echo ""
echo "Configuração de monitoramento automático:"
echo ""
echo "Opções de frequência:"
echo ""
echo "1) A cada 5 minutos (recomendado para produção)"
echo "2) A cada 15 minutos"
echo "3) A cada 30 minutos" 
echo "4) A cada hora"
echo "5) Configuração customizada"
echo "6) Mostrar monitoramento atual e sair"
echo "7) Remover monitoramento automático"
echo ""

read -p "Escolha uma opção [1-7]: " OPTION

case "$OPTION" in
    1)
        CRON_SCHEDULE="*/5 * * * *"
        DESCRIPTION="Health check a cada 5 minutos"
        ;;
    2)
        CRON_SCHEDULE="*/15 * * * *"
        DESCRIPTION="Health check a cada 15 minutos"
        ;;
    3)
        CRON_SCHEDULE="*/30 * * * *"
        DESCRIPTION="Health check a cada 30 minutos"
        ;;
    4)
        CRON_SCHEDULE="0 * * * *"
        DESCRIPTION="Health check a cada hora"
        ;;
    5)
        echo ""
        echo "Digite a configuração CRON personalizada:"
        echo "Formato: minuto hora dia mês dia_da_semana"
        echo "Exemplo: '*/10 * * * *' para a cada 10 minutos"
        echo ""
        read -p "CRON: " CRON_SCHEDULE
        DESCRIPTION="Health check personalizado: $CRON_SCHEDULE"
        
        # Validação básica
        if [ $(echo "$CRON_SCHEDULE" | wc -w) -ne 5 ]; then
            error "Formato CRON inválido!"
        fi
        ;;
    6)
        echo ""
        log "Configurações atuais de monitoramento:"
        echo "----------------------------------------"
        crontab -l 2>/dev/null | grep -E "(health_check|monitoring)" || echo "Nenhum monitoramento configurado"
        echo "----------------------------------------"
        exit 0
        ;;
    7)
        echo ""
        log "Removendo monitoramento automático..."
        
        # Obter crontab atual
        crontab -l 2>/dev/null > "$USER_CRONTAB" || touch "$USER_CRONTAB"
        
        # Remover linhas relacionadas ao monitoramento
        grep -v -E "(health_check|monitoring)" "$USER_CRONTAB" > "${USER_CRONTAB}.new" || touch "${USER_CRONTAB}.new"
        
        # Aplicar nova crontab
        crontab "${USER_CRONTAB}.new"
        
        # Limpar arquivos temporários
        rm -f "$USER_CRONTAB" "${USER_CRONTAB}.new"
        
        log "✅ Monitoramento automático removido do crontab"
        exit 0
        ;;
    *)
        error "Opção inválida!"
        ;;
esac

echo ""
echo "📋 Configuração de alertas:"
echo ""

# Configurar alertas por email
echo "Configurar alertas por email? (y/n)"
read -p "Email [n]: " EMAIL_ALERTS
EMAIL_ALERTS=${EMAIL_ALERTS:-n}

EMAIL_ADDRESS=""
if [[ "$EMAIL_ALERTS" =~ ^[Yy]$ ]]; then
    read -p "Digite o email para alertas: " EMAIL_ADDRESS
    
    # Validação básica de email
    if [[ ! "$EMAIL_ADDRESS" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
        warning "Email pode estar inválido, mas continuando..."
    fi
fi

# Configurar alertas Telegram
echo "Configurar alertas Telegram? (y/n)"
read -p "Telegram [n]: " TELEGRAM_ALERTS
TELEGRAM_ALERTS=${TELEGRAM_ALERTS:-n}

BOT_TOKEN=""
CHAT_ID=""
if [[ "$TELEGRAM_ALERTS" =~ ^[Yy]$ ]]; then
    echo ""
    echo "Para configurar Telegram, você precisa:"
    echo "1. Criar um bot (@BotFather no Telegram)"
    echo "2. Obter o token do bot"
    echo "3. Obter o chat ID"
    echo ""
    
    read -p "Token do bot: " BOT_TOKEN
    read -p "Chat ID: " CHAT_ID
    
    if [ -z "$BOT_TOKEN" ] || [ -z "$CHAT_ID" ]; then
        warning "Token ou Chat ID vazios - alertas Telegram não funcionarão"
    fi
fi

echo ""
echo "📋 Configuração escolhida:"
echo "   Frequência: $DESCRIPTION"
echo "   Script: $HEALTH_SCRIPT"
echo "   Email: ${EMAIL_ADDRESS:-Não configurado}"
echo "   Telegram: ${BOT_TOKEN:+Configurado}"
echo ""

read -p "Confirma a configuração? (y/n): " CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    log "Configuração cancelada"
    exit 0
fi

log "Configurando monitoramento automático..."

# Obter crontab atual
crontab -l 2>/dev/null > "$USER_CRONTAB" || touch "$USER_CRONTAB"

# Remover configurações antigas de monitoramento (se existirem)
grep -v -E "(health_check|monitoring)" "$USER_CRONTAB" > "${USER_CRONTAB}.new" || touch "${USER_CRONTAB}.new"

# Adicionar nova configuração
echo "" >> "${USER_CRONTAB}.new"
echo "# Monitoramento automático ML Manager - $DESCRIPTION" >> "${USER_CRONTAB}.new"

# Preparar comando CRON
CRON_COMMAND="$CRON_SCHEDULE /usr/bin/php $HEALTH_SCRIPT --json >> /var/log/ml_health.log 2>&1"

echo "$CRON_COMMAND" >> "${USER_CRONTAB}.new"

# Aplicar nova crontab
crontab "${USER_CRONTAB}.new"

if [ $? -eq 0 ]; then
    log "✅ Monitoramento automático configurado com sucesso!"
    
    # Criar arquivo de log se não existir
    sudo touch /var/log/ml_health.log
    sudo chown $(whoami):$(whoami) /var/log/ml_health.log 2>/dev/null || true
    
    # Atualizar .env com configurações de alerta
    ENV_FILE="$SCRIPT_DIR/../.env"
    if [ -f "$ENV_FILE" ]; then
        log "Atualizando configurações de alerta no .env..."
        
        # Backup do .env
        cp "$ENV_FILE" "${ENV_FILE}.backup.$(date +%Y%m%d_%H%M%S)"
        
        # Remover configurações antigas
        grep -v -E "^(ALERT_EMAIL|TELEGRAM_ENABLED|TELEGRAM_BOT_TOKEN|TELEGRAM_CHAT_ID)=" "$ENV_FILE" > "${ENV_FILE}.tmp"
        
        # Adicionar novas configurações
        echo "" >> "${ENV_FILE}.tmp"
        echo "# Configurações de Alerta - $(date)" >> "${ENV_FILE}.tmp"
        
        if [ -n "$EMAIL_ADDRESS" ]; then
            echo "ALERT_EMAIL=$EMAIL_ADDRESS" >> "${ENV_FILE}.tmp"
        fi
        
        if [ -n "$BOT_TOKEN" ]; then
            echo "TELEGRAM_ENABLED=true" >> "${ENV_FILE}.tmp"
            echo "TELEGRAM_BOT_TOKEN=$BOT_TOKEN" >> "${ENV_FILE}.tmp"
            echo "TELEGRAM_CHAT_ID=$CHAT_ID" >> "${ENV_FILE}.tmp"
        else
            echo "TELEGRAM_ENABLED=false" >> "${ENV_FILE}.tmp"
        fi
        
        mv "${ENV_FILE}.tmp" "$ENV_FILE"
        log "✅ Configurações de alerta atualizadas"
    fi
    
    echo ""
    echo "📋 RESUMO DA CONFIGURAÇÃO:"
    echo "   ⏰ Frequência: $DESCRIPTION"
    echo "   📝 Log: /var/log/ml_health.log"
    echo "   📧 Email: ${EMAIL_ADDRESS:-Não configurado}"
    echo "   📱 Telegram: ${BOT_TOKEN:+Configurado}"
    echo ""
    echo "🔍 COMANDOS ÚTEIS:"
    echo "   Ver crontab: crontab -l"
    echo "   Ver log: tail -f /var/log/ml_health.log"
    echo "   Teste manual: php $HEALTH_SCRIPT"
    echo "   Teste detalhado: php $HEALTH_SCRIPT --detailed"
    echo "   Remover: $0 (opção 7)"
    echo ""
    
    # Verificar se o serviço cron está rodando
    if systemctl is-active --quiet cron || systemctl is-active --quiet crond; then
        info "✅ Serviço cron está ativo"
    else
        warning "⚠️ Serviço cron pode não estar rodando!"
        echo "   Execute: sudo systemctl start cron"
        echo "   Ativar: sudo systemctl enable cron"
    fi
    
    # Perguntar se quer fazer teste
    echo ""
    read -p "Executar health check de teste agora? (y/n): " TEST_NOW
    if [[ "$TEST_NOW" =~ ^[Yy]$ ]]; then
        log "Executando health check de teste..."
        echo ""
        php "$HEALTH_SCRIPT" --detailed
    fi
    
else
    error "❌ Falha ao configurar crontab!"
fi

# Limpar arquivos temporários
rm -f "$USER_CRONTAB" "${USER_CRONTAB}.new"

log "🎉 Configuração de monitoramento finalizada!"