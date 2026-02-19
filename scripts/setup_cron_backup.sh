#!/bin/bash

# ====================================
# CONFIGURADOR DE BACKUP AUTOMÁTICO
# Configura CRON para execução automática
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
BACKUP_SCRIPT="$SCRIPT_DIR/backup_system.sh"
USER_CRONTAB="/tmp/crontab_backup_$(date +%Y%m%d_%H%M%S)"

echo "======================================"
echo "⏰ CONFIGURADOR BACKUP AUTOMÁTICO"
echo "======================================"

# Verificar se script de backup existe
if [ ! -f "$BACKUP_SCRIPT" ]; then
    error "Script de backup não encontrado: $BACKUP_SCRIPT"
fi

# Verificar se cron está instalado
if ! command -v crontab &> /dev/null; then
    error "Crontab não encontrado! Instale com: sudo apt install cron"
fi

echo ""
echo "Configuração de horários para backup automático:"
echo ""
echo "Opções pré-configuradas:"
echo ""
echo "1) Diário às 02:00"
echo "2) Diário às 03:30" 
echo "3) A cada 12 horas (02:00 e 14:00)"
echo "4) Semanalmente (Domingo às 01:00)"
echo "5) Configuração customizada"
echo "6) Mostrar crontab atual e sair"
echo "7) Remover backups automáticos"
echo ""

read -p "Escolha uma opção [1-7]: " OPTION

case "$OPTION" in
    1)
        CRON_SCHEDULE="0 2 * * *"
        DESCRIPTION="Backup diário às 02:00"
        ;;
    2)
        CRON_SCHEDULE="30 3 * * *"
        DESCRIPTION="Backup diário às 03:30"
        ;;
    3)
        CRON_SCHEDULE="0 2,14 * * *"
        DESCRIPTION="Backup duas vezes ao dia (02:00 e 14:00)"
        ;;
    4)
        CRON_SCHEDULE="0 1 * * 0"
        DESCRIPTION="Backup semanal (Domingo às 01:00)"
        ;;
    5)
        echo ""
        echo "Digite a configuração CRON personalizada:"
        echo "Formato: minuto hora dia mês dia_da_semana"
        echo "Exemplo: '0 3 * * *' para diário às 03:00"
        echo ""
        read -p "CRON: " CRON_SCHEDULE
        DESCRIPTION="Backup personalizado: $CRON_SCHEDULE"
        
        # Validação básica
        if [ $(echo "$CRON_SCHEDULE" | wc -w) -ne 5 ]; then
            error "Formato CRON inválido!"
        fi
        ;;
    6)
        echo ""
        log "Crontab atual:"
        echo "----------------------------------------"
        crontab -l 2>/dev/null | grep -v "^#" || echo "Nenhuma tarefa configurada"
        echo "----------------------------------------"
        exit 0
        ;;
    7)
        echo ""
        log "Removendo backups automáticos..."
        
        # Obter crontab atual
        crontab -l 2>/dev/null > "$USER_CRONTAB" || touch "$USER_CRONTAB"
        
        # Remover linhas relacionadas ao backup
        grep -v "backup_system.sh" "$USER_CRONTAB" > "${USER_CRONTAB}.new" || touch "${USER_CRONTAB}.new"
        
        # Aplicar nova crontab
        crontab "${USER_CRONTAB}.new"
        
        # Limpar arquivos temporários
        rm -f "$USER_CRONTAB" "${USER_CRONTAB}.new"
        
        log "✅ Backups automáticos removidos do crontab"
        exit 0
        ;;
    *)
        error "Opção inválida!"
        ;;
esac

echo ""
echo "📋 Configuração escolhida:"
echo "   Horário: $CRON_SCHEDULE"
echo "   Descrição: $DESCRIPTION"
echo "   Script: $BACKUP_SCRIPT"
echo ""

# Verificar se usuário quer notificações por email
echo "Configurar notificações por email em caso de erro? (y/n)"
read -p "Email [n]: " EMAIL_NOTIFY
EMAIL_NOTIFY=${EMAIL_NOTIFY:-n}

EMAIL_ADDRESS=""
if [[ "$EMAIL_NOTIFY" =~ ^[Yy]$ ]]; then
    read -p "Digite o email para notificações: " EMAIL_ADDRESS
    
    # Validação básica de email
    if [[ ! "$EMAIL_ADDRESS" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
        warning "Email pode estar inválido, mas continuando..."
    fi
fi

echo ""
read -p "Confirma a configuração? (y/n): " CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    log "Configuração cancelada"
    exit 0
fi

log "Configurando backup automático..."

# Obter crontab atual
crontab -l 2>/dev/null > "$USER_CRONTAB" || touch "$USER_CRONTAB"

# Remover configurações antigas do backup (se existirem)
grep -v "backup_system.sh" "$USER_CRONTAB" > "${USER_CRONTAB}.new" || touch "${USER_CRONTAB}.new"

# Adicionar nova configuração
echo "" >> "${USER_CRONTAB}.new"
echo "# Backup automático Mercado Livre Manager - $DESCRIPTION" >> "${USER_CRONTAB}.new"

if [ -n "$EMAIL_ADDRESS" ]; then
    # Com notificação por email
    CRON_COMMAND="$CRON_SCHEDULE $BACKUP_SCRIPT >> /var/log/ml_backup.log 2>&1 || echo 'Backup falhou em \$(date)' | mail -s 'ERRO Backup ML Manager' $EMAIL_ADDRESS"
else
    # Sem notificação por email
    CRON_COMMAND="$CRON_SCHEDULE $BACKUP_SCRIPT >> /var/log/ml_backup.log 2>&1"
fi

echo "$CRON_COMMAND" >> "${USER_CRONTAB}.new"

# Aplicar nova crontab
crontab "${USER_CRONTAB}.new"

if [ $? -eq 0 ]; then
    log "✅ Backup automático configurado com sucesso!"
    
    # Criar arquivo de log se não existir
    sudo touch /var/log/ml_backup.log
    sudo chown $(whoami):$(whoami) /var/log/ml_backup.log 2>/dev/null || true
    
    echo ""
    echo "📋 RESUMO DA CONFIGURAÇÃO:"
    echo "   ⏰ Horário: $DESCRIPTION"
    echo "   📝 Log: /var/log/ml_backup.log"
    echo "   📧 Email: ${EMAIL_ADDRESS:-Não configurado}"
    echo ""
    echo "🔍 COMANDOS ÚTEIS:"
    echo "   Ver crontab: crontab -l"
    echo "   Ver log: tail -f /var/log/ml_backup.log"
    echo "   Teste manual: $BACKUP_SCRIPT"
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
    read -p "Executar backup de teste agora? (y/n): " TEST_NOW
    if [[ "$TEST_NOW" =~ ^[Yy]$ ]]; then
        log "Executando backup de teste..."
        echo ""
        $BACKUP_SCRIPT
    fi
    
else
    error "❌ Falha ao configurar crontab!"
fi

# Limpar arquivos temporários
rm -f "$USER_CRONTAB" "${USER_CRONTAB}.new"

log "🎉 Configuração finalizada!"