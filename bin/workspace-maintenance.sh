#!/bin/bash
# Script de Manutenção para Prevenir Erros do VS Code
# Rotaciona logs grandes e limpa cache antigo

LOG_DIR="storage/logs"
CACHE_DIR="storage/cache"
MAX_LOG_SIZE=5242880  # 5MB
DAYS_OLD=7

echo "🔧 Iniciando manutenção do workspace..."

# Rotacionar logs grandes
echo "📋 Verificando logs grandes..."
cd "$(dirname "$0")/../" || exit 1

rotated=0
for log in $LOG_DIR/*.log; do
    if [ -f "$log" ]; then
        size=$(stat -c%s "$log" 2>/dev/null || stat -f%z "$log" 2>/dev/null || echo 0)
        if [ "$size" -gt "$MAX_LOG_SIZE" ]; then
            backup="${log}.$(date +%Y%m%d_%H%M%S).bak"
            mv "$log" "$backup"
            touch "$log"
            chmod 664 "$log"
            echo "  ✓ Rotacionado: $(basename "$log") ($(($size / 1048576))MB)"
            ((rotated++))
        fi
    fi
done
[ $rotated -eq 0 ] && echo "  ✓ Nenhum log precisa rotação"

# Limpar cache antigo
echo "🗑️  Limpando cache antigo (>$DAYS_OLD dias)..."
if [ -d "$CACHE_DIR" ]; then
    deleted=$(find "$CACHE_DIR" -type f -mtime +$DAYS_OLD -delete -print | wc -l)
    echo "  ✓ Removidos: $deleted arquivos"
else
    echo "  ⚠️  Diretório de cache não encontrado"
fi

# Limpar backups antigos de logs (>30 dias)
echo "🗑️  Limpando backups antigos..."
deleted=$(find "$LOG_DIR" -name "*.bak" -mtime +30 -delete -print | wc -l)
echo "  ✓ Removidos: $deleted backups"

# Verificar espaço em disco
echo "💾 Espaço em disco:"
df -h . | tail -1 | awk '{print "  Usado: "$3" / "$2" ("$5")"}'

# Verificar memória
echo "🧠 Memória disponível:"
free -h | grep "Mem:" | awk '{print "  Livre: "$4" / "$2}'

echo "✅ Manutenção concluída!"
