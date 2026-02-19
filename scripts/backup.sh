#!/bin/bash

# Script de Backup - Mercado Livre Manager
# Execute: bash scripts/backup.sh

set -e

# Configurações
BACKUP_DIR="./backups"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME=$(grep DB_NAME .env | cut -d '=' -f2 | tr -d ' ')
DB_USER=$(grep DB_USER .env | cut -d '=' -f2 | tr -d ' ')
DB_PASS=$(grep DB_PASS .env | cut -d '=' -f2 | tr -d ' ')

# Criar diretório de backup
mkdir -p $BACKUP_DIR

echo "📦 Iniciando backup..."
echo ""

# Backup do banco de dados
if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ]; then
    echo "Backup do banco de dados..."
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/db_$DATE.sql"
    echo "✓ Banco de dados: $BACKUP_DIR/db_$DATE.sql"
else
    echo "⚠ Credenciais do banco não encontradas no .env"
fi

# Backup dos arquivos importantes
echo "Backup dos arquivos..."
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/cache' \
    --exclude='storage/logs' \
    --exclude='backups' \
    .env \
    app/ \
    config/ \
    database/ \
    public/ \
    composer.json \
    composer.lock

echo "✓ Arquivos: $BACKUP_DIR/files_$DATE.tar.gz"

# Limpar backups antigos (manter últimos 7 dias)
echo ""
echo "Limpando backups antigos..."
find $BACKUP_DIR -type f -mtime +7 -delete
echo "✓ Limpeza concluída"

echo ""
echo "✅ Backup concluído!"
echo "Localização: $BACKUP_DIR/"

