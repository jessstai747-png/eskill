#!/bin/bash
# ==============================================
# Script de Backup Diário - eSkill
# Configurar no CRON: 0 3 * * * /home/eskill/htdocs/eskill.com.br/scripts/backup_daily.sh
# ==============================================

# Configurações
BACKUP_DIR="/home/eskill/htdocs/eskill.com.br/storage/backups"
DB_NAME="meli"
DB_USER="root"
DB_PASS="Tr1unf0@"
DB_HOST="localhost"
RETENTION_DAYS=30
APP_DIR="/home/eskill/htdocs/eskill.com.br"

# Criar diretório se não existir
mkdir -p "$BACKUP_DIR"

# Nome do arquivo com data
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_$DATE.sql"
BACKUP_FILE_COMPRESSED="$BACKUP_FILE.gz"

echo "=============================================="
echo "Iniciando backup: $(date)"
echo "=============================================="

# 1. Fazer backup do banco de dados
echo "[1/3] Fazendo backup do banco de dados..."
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" --single-transaction --routines --triggers > "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Dump do banco criado: $BACKUP_FILE"
    
    # Comprimir
    gzip "$BACKUP_FILE"
    echo "✅ Backup comprimido: $BACKUP_FILE_COMPRESSED"
    
    # Tamanho do arquivo
    SIZE=$(ls -lh "$BACKUP_FILE_COMPRESSED" | awk '{print $5}')
    echo "   Tamanho: $SIZE"
else
    echo "❌ Erro ao criar dump do banco"
fi

# 2. Backup do arquivo .env
echo "[2/3] Fazendo backup do .env..."
cp "$APP_DIR/.env" "$BACKUP_DIR/env_$DATE.backup"
if [ $? -eq 0 ]; then
    echo "✅ Backup do .env criado"
else
    echo "❌ Erro ao copiar .env"
fi

# 3. Remover backups antigos
echo "[3/3] Removendo backups antigos (>${RETENTION_DAYS} dias)..."
REMOVED=$(find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete -print | wc -l)
REMOVED_ENV=$(find "$BACKUP_DIR" -name "env_*.backup" -mtime +$RETENTION_DAYS -delete -print | wc -l)
echo "   Removidos: $REMOVED backups de BD, $REMOVED_ENV backups de .env"

# Log
LOG_FILE="$BACKUP_DIR/backup.log"
echo "$(date '+%Y-%m-%d %H:%M:%S') | Backup OK | DB: $BACKUP_FILE_COMPRESSED | Size: $SIZE" >> "$LOG_FILE"

# 4. Upload para backup remoto (se configurado)
echo "[4/4] Verificando backup remoto..."
if [ -f "$APP_DIR/.env" ]; then
    REMOTE_TYPE=$(grep "^BACKUP_REMOTE_TYPE=" "$APP_DIR/.env" | cut -d= -f2)
    REMOTE_NAME=$(grep "^BACKUP_REMOTE_NAME=" "$APP_DIR/.env" | cut -d= -f2)
    REMOTE_PATH=$(grep "^BACKUP_REMOTE_PATH=" "$APP_DIR/.env" | cut -d= -f2)
    
    if [ "$REMOTE_TYPE" = "rclone" ] && [ -n "$REMOTE_NAME" ]; then
        if command -v rclone &> /dev/null; then
            echo "   Sincronizando com $REMOTE_NAME:$REMOTE_PATH..."
            rclone copy "$BACKUP_FILE_COMPRESSED" "${REMOTE_NAME}:${REMOTE_PATH}/" --log-level ERROR 2>&1
            if [ $? -eq 0 ]; then
                echo "✅ Upload remoto concluído"
                echo "$(date '+%Y-%m-%d %H:%M:%S') | Remote OK | $REMOTE_NAME:$REMOTE_PATH" >> "$LOG_FILE"
            else
                echo "⚠️ Erro no upload remoto"
            fi
        else
            echo "⚠️ rclone não instalado, pulando backup remoto"
        fi
    elif [ "$REMOTE_TYPE" = "s3" ] && [ -n "$REMOTE_NAME" ]; then
        if command -v rclone &> /dev/null; then
            echo "   Enviando para S3: $REMOTE_PATH..."
            rclone copy "$BACKUP_FILE_COMPRESSED" "${REMOTE_NAME}:${REMOTE_PATH}/" --log-level ERROR 2>&1
            if [ $? -eq 0 ]; then
                echo "✅ Upload S3 concluído"
                echo "$(date '+%Y-%m-%d %H:%M:%S') | S3 OK | $REMOTE_PATH" >> "$LOG_FILE"
            else
                echo "⚠️ Erro no upload S3"
            fi
        fi
    else
        echo "   Backup remoto não configurado (execute: ./scripts/setup_quick.sh)"
    fi
fi

echo "=============================================="
echo "Backup concluído: $(date)"
echo "=============================================="

# Listar backups recentes
echo ""
echo "Backups disponíveis:"
ls -lh "$BACKUP_DIR"/*.gz 2>/dev/null | tail -5
