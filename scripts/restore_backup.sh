#!/bin/bash

# ====================================
# SISTEMA DE RESTAURAÇÃO DE BACKUP
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

# Carregar configurações
load_config() {
    if [ ! -f "$CONFIG_FILE" ]; then
        error "Arquivo .env não encontrado: $CONFIG_FILE"
    fi
    
    # Carregar variáveis do .env
    export $(grep -v '^#' "$CONFIG_FILE" | grep -v '^$' | xargs)
    
    BACKUP_BASE_PATH=${BACKUP_PATH:-"/backup/mercadolivre"}
    BACKUP_DB_DIR="$BACKUP_BASE_PATH/database"
    BACKUP_FILES_DIR="$BACKUP_BASE_PATH/files"
}

# Listar backups disponíveis
list_backups() {
    echo "======================================"
    echo "📋 BACKUPS DISPONÍVEIS"
    echo "======================================"
    
    echo ""
    echo "💾 BANCO DE DADOS:"
    if [ -d "$BACKUP_DB_DIR" ]; then
        find "$BACKUP_DB_DIR" -name "*.sql*" -type f -printf "%T+ %p\n" | sort -r | head -10 | while read date file; do
            size=$(du -h "$file" | cut -f1)
            basename_file=$(basename "$file")
            echo "  $basename_file ($size) - $date"
        done
    else
        echo "  Nenhum backup encontrado"
    fi
    
    echo ""
    echo "📁 ARQUIVOS:"
    if [ -d "$BACKUP_FILES_DIR" ]; then
        find "$BACKUP_FILES_DIR" -name "*.tar*" -type f -printf "%T+ %p\n" | sort -r | head -10 | while read date file; do
            size=$(du -h "$file" | cut -f1)
            basename_file=$(basename "$file")
            echo "  $basename_file ($size) - $date"
        done
    else
        echo "  Nenhum backup encontrado"
    fi
    echo ""
}

# Restaurar banco de dados
restore_database() {
    local backup_file="$1"
    
    if [ ! -f "$backup_file" ]; then
        error "Arquivo de backup não encontrado: $backup_file"
    fi
    
    echo "⚠️  ATENÇÃO: Esta operação irá SOBRESCREVER o banco atual!"
    echo "Banco: $DB_NAME"
    echo "Backup: $(basename "$backup_file")"
    echo ""
    
    read -p "Tem certeza que deseja continuar? (digite 'SIM' para confirmar): " confirm
    if [ "$confirm" != "SIM" ]; then
        log "Operação cancelada"
        exit 0
    fi
    
    log "Iniciando restauração do banco de dados..."
    
    # Criar backup de segurança do banco atual
    local safety_backup="/tmp/safety_backup_$(date +%Y%m%d_%H%M%S).sql"
    log "Criando backup de segurança: $safety_backup"
    
    mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$safety_backup" 2>/dev/null || {
        warning "Não foi possível criar backup de segurança"
    }
    
    # Preparar arquivo para restauração
    local restore_file="$backup_file"
    
    # Verificar se é comprimido
    if [[ "$backup_file" == *.gz ]]; then
        log "Descomprimindo backup..."
        restore_file="/tmp/restore_$(date +%Y%m%d_%H%M%S).sql"
        gunzip -c "$backup_file" > "$restore_file"
    fi
    
    # Executar restauração
    log "Restaurando banco de dados..."
    
    # Criar arquivo de configuração temporário para MySQL
    local mysql_config=$(mktemp)
    cat << EOF > "$mysql_config"
[client]
user=$DB_USER
password=$DB_PASS
host=$DB_HOST
port=${DB_PORT:-3306}
EOF
    
    if mysql --defaults-file="$mysql_config" "$DB_NAME" < "$restore_file" 2>/dev/null; then
        rm -f "$mysql_config"
        
        # Limpar arquivo temporário se foi descomprimido
        if [ "$restore_file" != "$backup_file" ]; then
            rm -f "$restore_file"
        fi
        
        log "✅ Banco de dados restaurado com sucesso!"
        info "💾 Backup de segurança em: $safety_backup"
    else
        rm -f "$mysql_config"
        
        # Limpar arquivo temporário se foi descomprimido
        if [ "$restore_file" != "$backup_file" ]; then
            rm -f "$restore_file"
        fi
        
        error "❌ Falha na restauração do banco!"
    fi
}

# Restaurar arquivos
restore_files() {
    local backup_file="$1"
    
    if [ ! -f "$backup_file" ]; then
        error "Arquivo de backup não encontrado: $backup_file"
    fi
    
    echo "⚠️  ATENÇÃO: Esta operação irá SOBRESCREVER arquivos existentes!"
    echo "Backup: $(basename "$backup_file")"
    echo ""
    
    read -p "Tem certeza que deseja continuar? (digite 'SIM' para confirmar): " confirm
    if [ "$confirm" != "SIM" ]; then
        log "Operação cancelada"
        exit 0
    fi
    
    log "Iniciando restauração de arquivos..."
    
    # Criar backup de segurança dos arquivos atuais
    local safety_backup="/tmp/files_safety_backup_$(date +%Y%m%d_%H%M%S).tar.gz"
    log "Criando backup de segurança: $safety_backup"
    
    cd "$PROJECT_DIR"
    tar -czf "$safety_backup" \
        --exclude="storage/cache/*" \
        --exclude="storage/sessions/*" \
        --exclude="vendor/*" \
        .env app/ config/ public/ 2>/dev/null || {
        warning "Não foi possível criar backup completo de segurança"
    }
    
    # Extrair backup
    cd "$PROJECT_DIR"
    
    if [[ "$backup_file" == *.gz ]]; then
        log "Descomprimindo e extraindo arquivos..."
        tar -xzf "$backup_file"
    else
        log "Extraindo arquivos..."
        tar -xf "$backup_file"
    fi
    
    if [ $? -eq 0 ]; then
        log "✅ Arquivos restaurados com sucesso!"
        info "💾 Backup de segurança em: $safety_backup"
        
        # Reconfigurar permissões
        log "Reconfigurando permissões..."
        chmod 644 .env 2>/dev/null || true
        chmod -R 755 storage/ 2>/dev/null || true
        
    else
        error "❌ Falha na restauração de arquivos!"
    fi
}

# Verificar integridade do backup
verify_backup() {
    local backup_file="$1"
    
    if [ ! -f "$backup_file" ]; then
        error "Arquivo não encontrado: $backup_file"
    fi
    
    log "Verificando integridade: $(basename "$backup_file")"
    
    if [[ "$backup_file" == *.sql.gz ]]; then
        # Backup de banco comprimido
        if gzip -t "$backup_file"; then
            info "✅ Arquivo comprimido íntegro"
            
            # Verificar conteúdo SQL
            if gunzip -c "$backup_file" | head -20 | grep -q "CREATE TABLE\|INSERT INTO\|DROP TABLE"; then
                info "✅ Conteúdo SQL válido"
            else
                warning "⚠️ Conteúdo SQL pode estar corrompido"
            fi
        else
            error "❌ Arquivo comprimido corrompido!"
        fi
        
    elif [[ "$backup_file" == *.tar.gz ]]; then
        # Backup de arquivos comprimido
        if tar -tzf "$backup_file" >/dev/null 2>&1; then
            info "✅ Arquivo tar.gz íntegro"
            
            local file_count=$(tar -tzf "$backup_file" | wc -l)
            info "📁 $file_count arquivos no backup"
        else
            error "❌ Arquivo tar.gz corrompido!"
        fi
        
    elif [[ "$backup_file" == *.sql ]]; then
        # Backup de banco não comprimido
        if head -20 "$backup_file" | grep -q "CREATE TABLE\|INSERT INTO\|DROP TABLE"; then
            info "✅ Conteúdo SQL válido"
        else
            warning "⚠️ Conteúdo SQL pode estar corrompido"
        fi
        
    elif [[ "$backup_file" == *.tar ]]; then
        # Backup de arquivos não comprimido
        if tar -tf "$backup_file" >/dev/null 2>&1; then
            info "✅ Arquivo tar íntegro"
            
            local file_count=$(tar -tf "$backup_file" | wc -l)
            info "📁 $file_count arquivos no backup"
        else
            error "❌ Arquivo tar corrompido!"
        fi
        
    else
        warning "⚠️ Tipo de arquivo não reconhecido"
    fi
}

# Menu principal
show_menu() {
    echo "======================================"
    echo "🔄 RESTAURAÇÃO DE BACKUP"
    echo "======================================"
    echo ""
    echo "Escolha uma opção:"
    echo ""
    echo "1) Listar backups disponíveis"
    echo "2) Restaurar banco de dados"
    echo "3) Restaurar arquivos"
    echo "4) Verificar integridade de backup"
    echo "5) Sair"
    echo ""
    read -p "Opção [1-5]: " option
    
    case "$option" in
        1)
            list_backups
            ;;
        2)
            echo ""
            echo "Digite o caminho completo do backup de banco:"
            read -p "Arquivo: " db_backup
            restore_database "$db_backup"
            ;;
        3)
            echo ""
            echo "Digite o caminho completo do backup de arquivos:"
            read -p "Arquivo: " files_backup
            restore_files "$files_backup"
            ;;
        4)
            echo ""
            echo "Digite o caminho completo do arquivo para verificar:"
            read -p "Arquivo: " verify_file
            verify_backup "$verify_file"
            ;;
        5)
            log "Saindo..."
            exit 0
            ;;
        *)
            warning "Opção inválida!"
            ;;
    esac
}

# Função principal
main() {
    echo "======================================"
    echo "🔄 SISTEMA DE RESTAURAÇÃO"
    echo "$(date '+%Y-%m-%d %H:%M:%S')"
    echo "======================================"
    
    # Verificar se está rodando como usuário adequado
    if [ "$EUID" -eq 0 ]; then
        warning "Executando como root - tenha cuidado!"
    fi
    
    # Carregar configurações
    load_config
    
    # Verificar se os diretórios de backup existem
    if [ ! -d "$BACKUP_BASE_PATH" ]; then
        error "Diretório de backup não encontrado: $BACKUP_BASE_PATH"
    fi
    
    # Se argumentos foram passados, executar diretamente
    if [ $# -gt 0 ]; then
        case "$1" in
            "list"|"ls")
                list_backups
                ;;
            "verify")
                if [ -z "$2" ]; then
                    error "Especifique o arquivo para verificar"
                fi
                verify_backup "$2"
                ;;
            "db")
                if [ -z "$2" ]; then
                    error "Especifique o arquivo de backup do banco"
                fi
                restore_database "$2"
                ;;
            "files")
                if [ -z "$2" ]; then
                    error "Especifique o arquivo de backup dos arquivos"
                fi
                restore_files "$2"
                ;;
            *)
                error "Comando inválido: $1"
                ;;
        esac
    else
        # Modo interativo
        while true; do
            echo ""
            show_menu
            echo ""
            read -p "Pressione ENTER para continuar..."
        done
    fi
}

# Executar apenas se chamado diretamente
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    main "$@"
fi