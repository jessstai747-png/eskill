#!/bin/bash

# ====================================
# BACKUP REMOTO - S3/RCLONE
# Mercado Livre Manager
# ====================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
CONFIG_FILE="$PROJECT_DIR/.env"

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
info() { echo -e "${BLUE}[INFO]${NC} $1"; }

# Carregar configurações
load_config() {
    if [ -f "$CONFIG_FILE" ]; then
        export $(grep -v '^#' "$CONFIG_FILE" | grep -v '^$' | xargs)
    fi
    
    BACKUP_LOCAL_PATH="${BACKUP_PATH:-/home/eskill/htdocs/eskill.com.br/storage/backups}"
    BACKUP_REMOTE_TYPE="${BACKUP_REMOTE_TYPE:-none}"
    BACKUP_REMOTE_PATH="${BACKUP_REMOTE_PATH:-}"
}

# Verificar se rclone está instalado
check_rclone() {
    if ! command -v rclone &> /dev/null; then
        warning "rclone não está instalado."
        echo ""
        echo "Para instalar rclone:"
        echo "  curl https://rclone.org/install.sh | sudo bash"
        echo ""
        echo "Depois configure com:"
        echo "  rclone config"
        echo ""
        return 1
    fi
    return 0
}

# Verificar configuração do remote
check_remote_config() {
    local remote_name="$1"
    
    if ! rclone listremotes | grep -q "^${remote_name}:$"; then
        warning "Remote '$remote_name' não configurado no rclone."
        echo ""
        echo "Configure com: rclone config"
        echo "Adicione um remote chamado '$remote_name'"
        return 1
    fi
    
    info "Remote '$remote_name' configurado ✅"
    return 0
}

# Upload para S3 via AWS CLI
upload_s3_cli() {
    local local_path="$1"
    local s3_bucket="$2"
    local s3_prefix="${3:-backups}"
    
    if ! command -v aws &> /dev/null; then
        error "AWS CLI não instalado. Instale com: pip install awscli"
    fi
    
    log "Enviando para S3: s3://$s3_bucket/$s3_prefix/"
    
    aws s3 sync "$local_path" "s3://$s3_bucket/$s3_prefix/" \
        --storage-class STANDARD_IA \
        --exclude "*.tmp" \
        --exclude "temp/*"
    
    log "✅ Upload S3 concluído"
}

# Upload via rclone (suporta S3, GCS, Dropbox, etc)
upload_rclone() {
    local local_path="$1"
    local remote_name="$2"
    local remote_path="${3:-/backups/eskill}"
    
    check_rclone || return 1
    check_remote_config "$remote_name" || return 1
    
    log "Sincronizando com $remote_name:$remote_path"
    
    rclone sync "$local_path" "${remote_name}:${remote_path}" \
        --transfers 4 \
        --checkers 8 \
        --exclude "*.tmp" \
        --exclude "temp/**" \
        --log-level INFO \
        --stats 30s
    
    log "✅ Sincronização concluída"
}

# Listar backups remotos
list_remote_backups() {
    local remote_name="$1"
    local remote_path="${2:-/backups/eskill}"
    
    check_rclone || return 1
    check_remote_config "$remote_name" || return 1
    
    echo ""
    echo "📁 Backups em $remote_name:$remote_path"
    echo "=================================="
    
    rclone ls "${remote_name}:${remote_path}" 2>/dev/null | head -20
    
    echo ""
    echo "Total:"
    rclone size "${remote_name}:${remote_path}" 2>/dev/null
}

# Restaurar do remoto
restore_from_remote() {
    local remote_name="$1"
    local remote_path="${2:-/backups/eskill}"
    local local_dest="${3:-$BACKUP_LOCAL_PATH/restored}"
    
    check_rclone || return 1
    check_remote_config "$remote_name" || return 1
    
    mkdir -p "$local_dest"
    
    log "Restaurando de $remote_name:$remote_path para $local_dest"
    
    rclone copy "${remote_name}:${remote_path}" "$local_dest" \
        --transfers 4 \
        --log-level INFO
    
    log "✅ Restauração concluída em $local_dest"
}

# Configurar backup automático
setup_cron() {
    local schedule="${1:-0 4 * * *}"  # Padrão: 4h da manhã
    
    echo ""
    echo "Para adicionar ao cron, execute:"
    echo "  crontab -e"
    echo ""
    echo "E adicione a linha:"
    echo "  $schedule $SCRIPT_DIR/backup_remote.sh sync"
    echo ""
}

# Mostrar status
show_status() {
    echo ""
    echo "🔧 Configuração de Backup Remoto"
    echo "=================================="
    echo ""
    
    load_config
    
    echo "Local Path: $BACKUP_LOCAL_PATH"
    echo "Remote Type: $BACKUP_REMOTE_TYPE"
    echo "Remote Path: $BACKUP_REMOTE_PATH"
    echo ""
    
    # Verificar rclone
    if command -v rclone &> /dev/null; then
        echo "rclone: ✅ Instalado ($(rclone version | head -1))"
        echo ""
        echo "Remotes configurados:"
        rclone listremotes 2>/dev/null || echo "  Nenhum"
    else
        echo "rclone: ❌ Não instalado"
    fi
    
    # Verificar AWS CLI
    if command -v aws &> /dev/null; then
        echo "AWS CLI: ✅ Instalado"
    else
        echo "AWS CLI: ❌ Não instalado"
    fi
    
    echo ""
    echo "📁 Backups locais:"
    ls -lh "$BACKUP_LOCAL_PATH"/*.gz 2>/dev/null | tail -5 || echo "  Nenhum backup encontrado"
    echo ""
}

# Menu de ajuda
show_help() {
    echo ""
    echo "🔄 Backup Remoto - Mercado Livre Manager"
    echo ""
    echo "Uso: $0 <comando> [opções]"
    echo ""
    echo "Comandos:"
    echo "  status              Mostrar status da configuração"
    echo "  sync <remote>       Sincronizar backups com remote rclone"
    echo "  s3 <bucket>         Upload para bucket S3 via AWS CLI"
    echo "  list <remote>       Listar backups no remote"
    echo "  restore <remote>    Restaurar backups do remote"
    echo "  setup-cron          Mostrar instrução para cron"
    echo "  help                Mostrar esta ajuda"
    echo ""
    echo "Exemplos:"
    echo "  $0 status"
    echo "  $0 sync gdrive"
    echo "  $0 s3 meu-bucket-backup"
    echo "  $0 list gdrive /backups/eskill"
    echo ""
    echo "Configurar remotes:"
    echo "  rclone config"
    echo ""
}

# Main
main() {
    load_config
    
    case "${1:-help}" in
        status)
            show_status
            ;;
        sync)
            if [ -z "$2" ]; then
                error "Especifique o remote: $0 sync <remote_name> [remote_path]"
            fi
            upload_rclone "$BACKUP_LOCAL_PATH" "$2" "${3:-/backups/eskill}"
            ;;
        s3)
            if [ -z "$2" ]; then
                error "Especifique o bucket: $0 s3 <bucket_name> [prefix]"
            fi
            upload_s3_cli "$BACKUP_LOCAL_PATH" "$2" "${3:-backups}"
            ;;
        list)
            if [ -z "$2" ]; then
                error "Especifique o remote: $0 list <remote_name> [remote_path]"
            fi
            list_remote_backups "$2" "${3:-/backups/eskill}"
            ;;
        restore)
            if [ -z "$2" ]; then
                error "Especifique o remote: $0 restore <remote_name> [remote_path] [local_dest]"
            fi
            restore_from_remote "$2" "${3:-/backups/eskill}" "$4"
            ;;
        setup-cron)
            setup_cron "$2"
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            error "Comando desconhecido: $1. Use '$0 help' para ajuda."
            ;;
    esac
}

main "$@"
