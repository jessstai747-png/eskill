#!/bin/bash

# ====================================
# SISTEMA DE BACKUP AUTOMATIZADO
# Mercado Livre Manager
# ====================================

set -e

# Configurações
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
CONFIG_FILE="$PROJECT_DIR/.env"

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Funções de log
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
    logger "ML_BACKUP: $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
    logger "ML_BACKUP WARNING: $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    logger "ML_BACKUP ERROR: $1"
    exit 1
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Carregar configurações
load_config() {
    if [ ! -f "$CONFIG_FILE" ]; then
        error "Arquivo .env não encontrado: $CONFIG_FILE"
    fi
    
    # Carregar variáveis do .env
    export $(grep -v '^#' "$CONFIG_FILE" | grep -v '^$' | xargs)
    
    # Configurações de backup
    BACKUP_BASE_PATH=${BACKUP_PATH:-"/backup/mercadolivre"}
    BACKUP_RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-30}
    BACKUP_COMPRESS=${BACKUP_COMPRESS:-true}
    
    # Data para nomes de arquivo
    BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
    BACKUP_DATE_DAILY=$(date +%Y%m%d)
    
    # Diretórios de backup
    BACKUP_DB_DIR="$BACKUP_BASE_PATH/database"
    BACKUP_FILES_DIR="$BACKUP_BASE_PATH/files"
    BACKUP_LOGS_DIR="$BACKUP_BASE_PATH/logs"
    
    # Criar diretórios se não existirem
    mkdir -p "$BACKUP_DB_DIR"
    mkdir -p "$BACKUP_FILES_DIR" 
    mkdir -p "$BACKUP_LOGS_DIR"
}

# Backup do banco de dados
backup_database() {
    log "Iniciando backup do banco de dados..."
    
    # Validar configurações e mapear variáveis se necessário
    DB_HOST=${DB_HOST:-localhost}
    DB_NAME=${DB_NAME:-$DB_DATABASE}
    DB_USER=${DB_USER:-$DB_USERNAME}
    DB_PASS=${DB_PASS:-$DB_PASSWORD}

    if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
        error "Configurações do banco não encontradas no .env"
    fi
    
    local backup_file="$BACKUP_DB_DIR/${DB_NAME}_${BACKUP_DATE}.sql"
    
    # Criar arquivo de configuração temporário para MySQL
    local mysql_config=$(mktemp)
    cat << EOF > "$mysql_config"
[client]
user=$DB_USER
password=$DB_PASS
host=$DB_HOST
port=${DB_PORT:-3306}
EOF
    
    # Executar backup
    log "Fazendo dump do banco: $DB_NAME"
    
    if mysqldump --defaults-file="$mysql_config" \
                 --single-transaction \
                 --routines \
                 --triggers \
                 "$DB_NAME" > "$backup_file" 2>/dev/null; then
        
        # Remover arquivo de configuração temporário
        rm -f "$mysql_config"
        
        # Comprimir se configurado
        if [ "$BACKUP_COMPRESS" = "true" ]; then
            log "Comprimindo backup do banco..."
            gzip "$backup_file"
            backup_file="${backup_file}.gz"
        fi
        
        local size=$(du -h "$backup_file" | cut -f1)
        log "✅ Backup do banco criado: $backup_file ($size)"
        
        # Verificar integridade do backup
        if [ "$BACKUP_COMPRESS" = "true" ]; then
            if gzip -t "$backup_file"; then
                info "✅ Integridade do backup verificada"
            else
                error "❌ Backup corrompido!"
            fi
        fi
        
        return 0
    else
        rm -f "$mysql_config"
        error "❌ Falha no backup do banco de dados"
    fi
}

# Backup dos arquivos importantes
backup_files() {
    log "Iniciando backup de arquivos..."
    
    local backup_file="$BACKUP_FILES_DIR/files_${BACKUP_DATE}.tar"
    
    # Lista de arquivos/diretórios para backup
    local files_to_backup=(
        ".env"
        "composer.json"
        "composer.lock"
        "app/"
        "config/"
        "database/migrations/"
        "docs/"
        "public/"
        "storage/logs/"
    )
    
    # Criar arquivo de exclusão temporário
    local exclude_file=$(mktemp)
    cat << EOF > "$exclude_file"
storage/cache/*
storage/sessions/*
vendor/*
node_modules/*
*.log
*.tmp
.git/*
.DS_Store
Thumbs.db
EOF
    
    log "Criando arquivo tar com arquivos importantes..."
    
    # Executar backup
    cd "$PROJECT_DIR"
    
    if tar --exclude-from="$exclude_file" \
           -cf "$backup_file" \
           "${files_to_backup[@]}" 2>/dev/null; then
        
        # Remover arquivo de exclusão
        rm -f "$exclude_file"
        
        # Comprimir se configurado
        if [ "$BACKUP_COMPRESS" = "true" ]; then
            log "Comprimindo backup de arquivos..."
            gzip "$backup_file"
            backup_file="${backup_file}.gz"
        fi
        
        local size=$(du -h "$backup_file" | cut -f1)
        log "✅ Backup de arquivos criado: $backup_file ($size)"
        
        return 0
    else
        rm -f "$exclude_file"
        error "❌ Falha no backup de arquivos"
    fi
}

# Backup dos logs
backup_logs() {
    log "Iniciando backup de logs..."
    
    local log_backup_dir="$BACKUP_LOGS_DIR/$BACKUP_DATE_DAILY"
    mkdir -p "$log_backup_dir"
    
    # Copiar logs da aplicação
    if [ -d "$PROJECT_DIR/storage/logs" ]; then
        cp -r "$PROJECT_DIR/storage/logs/"* "$log_backup_dir/" 2>/dev/null || true
    fi
    
    # Copiar logs do sistema PHP (se existir)
    if [ -f "/var/log/php_errors.log" ]; then
        cp "/var/log/php_errors.log" "$log_backup_dir/php_errors.log" 2>/dev/null || true
    fi
    
    # Comprimir logs se configurado
    if [ "$BACKUP_COMPRESS" = "true" ] && [ -d "$log_backup_dir" ]; then
        log "Comprimindo logs..."
        tar -czf "$BACKUP_LOGS_DIR/logs_${BACKUP_DATE_DAILY}.tar.gz" -C "$BACKUP_LOGS_DIR" "$BACKUP_DATE_DAILY"
        rm -rf "$log_backup_dir"
    fi
    
    log "✅ Backup de logs concluído"
}

# Limpeza de backups antigos
cleanup_old_backups() {
    log "Limpando backups antigos (mais de $BACKUP_RETENTION_DAYS dias)..."
    
    local total_removed=0
    
    # Limpar backups do banco
    if [ -d "$BACKUP_DB_DIR" ]; then
        local db_removed=$(find "$BACKUP_DB_DIR" -name "*.sql*" -mtime +$BACKUP_RETENTION_DAYS -delete -print | wc -l)
        total_removed=$((total_removed + db_removed))
    fi
    
    # Limpar backups de arquivos
    if [ -d "$BACKUP_FILES_DIR" ]; then
        local files_removed=$(find "$BACKUP_FILES_DIR" -name "*.tar*" -mtime +$BACKUP_RETENTION_DAYS -delete -print | wc -l)
        total_removed=$((total_removed + files_removed))
    fi
    
    # Limpar logs antigos
    if [ -d "$BACKUP_LOGS_DIR" ]; then
        local logs_removed=$(find "$BACKUP_LOGS_DIR" -name "*" -mtime +$BACKUP_RETENTION_DAYS -delete -print | wc -l)
        total_removed=$((total_removed + logs_removed))
    fi
    
    if [ $total_removed -gt 0 ]; then
        log "✅ $total_removed arquivos antigos removidos"
    else
        info "Nenhum arquivo antigo para remover"
    fi
}

# Verificar espaço em disco
check_disk_space() {
    log "Verificando espaço em disco..."
    
    local available_space=$(df "$BACKUP_BASE_PATH" | awk 'NR==2 {print $4}')
    local available_gb=$((available_space / 1024 / 1024))
    
    if [ $available_gb -lt 1 ]; then
        error "❌ Espaço insuficiente: ${available_gb}GB disponível"
    elif [ $available_gb -lt 5 ]; then
        warning "⚠️ Pouco espaço disponível: ${available_gb}GB"
    else
        info "✅ Espaço suficiente: ${available_gb}GB disponível"
    fi
}

# Enviar notificação (se configurado)
send_notification() {
    local status="$1"
    local message="$2"
    
    # Telegram
    if [ "$TELEGRAM_ENABLED" = "true" ] && [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
        local emoji=""
        case "$status" in
            "success") emoji="✅" ;;
            "warning") emoji="⚠️" ;;
            "error") emoji="❌" ;;
        esac
        
        local text="$emoji ML Manager Backup\n$message\n$(date '+%Y-%m-%d %H:%M:%S')"
        
        curl -s -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
             -d chat_id="$TELEGRAM_CHAT_ID" \
             -d text="$text" \
             -d parse_mode="HTML" >/dev/null 2>&1 || true
    fi
}

# Gerar relatório
generate_report() {
    local start_time="$1"
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    local duration_formatted=$(date -u -d @$duration +%H:%M:%S)
    
    local report_file="$BACKUP_LOGS_DIR/backup_report_${BACKUP_DATE}.txt"
    
    cat << EOF > "$report_file"
RELATÓRIO DE BACKUP - $(date '+%Y-%m-%d %H:%M:%S')
================================================

CONFIGURAÇÕES:
- Diretório base: $BACKUP_BASE_PATH
- Retenção: $BACKUP_RETENTION_DAYS dias
- Compressão: $BACKUP_COMPRESS

BANCO DE DADOS:
- Host: $DB_HOST
- Banco: $DB_NAME
- Usuário: $DB_USER

TEMPO DE EXECUÇÃO: $duration_formatted

ARQUIVOS CRIADOS:
$(find "$BACKUP_BASE_PATH" -name "*${BACKUP_DATE}*" -o -name "*${BACKUP_DATE_DAILY}*" | sort)

ESPAÇO UTILIZADO:
$(du -sh "$BACKUP_BASE_PATH")

================================================
EOF
    
    info "📄 Relatório gerado: $report_file"
}

# Função principal
main() {
    local start_time=$(date +%s)
    
    echo "======================================"
    echo "💾 BACKUP AUTOMATIZADO"
    echo "$(date '+%Y-%m-%d %H:%M:%S')"
    echo "======================================"
    
    # Verificar se está rodando como root em produção
    if [ "$APP_ENV" = "production" ] && [ "$EUID" -ne 0 ]; then
        warning "Executando como usuário não-root em produção"
    fi
    
    # Carregar configurações
    load_config
    
    # Verificar espaço
    check_disk_space
    
    local success=true
    local error_msgs=""
    
    # Executar backups
    if ! backup_database; then
        success=false
        error_msgs="$error_msgs\n- Falha no backup do banco"
    fi
    
    if ! backup_files; then
        success=false
        error_msgs="$error_msgs\n- Falha no backup de arquivos"
    fi
    
    backup_logs
    cleanup_old_backups
    
    # Gerar relatório
    generate_report "$start_time"
    
    # Enviar notificação
    if [ "$success" = true ]; then
        log "🎉 Backup concluído com sucesso!"
        send_notification "success" "Backup realizado com sucesso"
    else
        error_msg="❌ Backup concluído com erros:$error_msgs"
        warning "$error_msg"
        send_notification "error" "$error_msg"
    fi
    
    echo "======================================"
    echo "✅ Backup finalizado"
    echo "📁 Local: $BACKUP_BASE_PATH"
    echo "======================================"
}

# Executar apenas se chamado diretamente
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    main "$@"
fi