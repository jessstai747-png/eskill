#!/bin/bash
# Script de Backup Automatizado para Produção
# Configurar no CRON: 0 2 * * * /caminho/para/backup_production.sh

# Configurações
BACKUP_DIR="/backups/mercadolivre"
DB_NAME="mercadolivre_db"
DB_USER="ml_user"
DB_PASS="senha_forte_aqui"
RETENTION_DAYS=30

# Criar diretório se não existir
mkdir -p "$BACKUP_DIR"

# Nome do arquivo com data
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/backup_$DATE.sql"
BACKUP_FILE_COMPRESSED="$BACKUP_FILE.gz"

# Fazer backup do banco
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"

# Comprimir
gzip "$BACKUP_FILE"

# Remover backups antigos
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Log
echo "$(date): Backup criado: $BACKUP_FILE_COMPRESSED" >> "$BACKUP_DIR/backup.log"
