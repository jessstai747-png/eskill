#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CANONICAL_FILE="$ROOT_DIR/final_crontab"
CURRENT_FILE="$ROOT_DIR/current_crontab"
DRY_RUN="${1:-}"

if [[ ! -f "$CANONICAL_FILE" ]]; then
    echo "Erro: arquivo canônico não encontrado: $CANONICAL_FILE" >&2
    exit 1
fi

TMP_EXISTING="$(mktemp)"
TMP_FILTERED="$(mktemp)"
TMP_NEW="$(mktemp)"
trap 'rm -f "$TMP_EXISTING" "$TMP_FILTERED" "$TMP_NEW"' EXIT

crontab -l > "$TMP_EXISTING" 2>/dev/null || true

# Remove entradas do projeto para evitar duplicação e divergência.
grep -Ev '/home/eskill/htdocs/eskill\.com\.br|eskill\.com\.br|catalog-clone-worker\.php|clone-post-actions-worker\.php|clone-health-monitor\.php|cleanup-clone-data\.php|generate-clone-metrics-report\.php' "$TMP_EXISTING" > "$TMP_FILTERED" || true

{
    cat "$TMP_FILTERED"
    echo ""
    cat "$CANONICAL_FILE"
} > "$TMP_NEW"

# Remove espaços em branco no fim e colapsa múltiplas linhas vazias.
sed -E 's/[[:space:]]+$//' "$TMP_NEW" | awk 'BEGIN{blank=0} {if ($0=="") {if(!blank){print; blank=1}} else {print; blank=0}}' > "$CURRENT_FILE"

if [[ "$DRY_RUN" == "--dry-run" ]]; then
    echo "[DRY-RUN] current_crontab gerado em: $CURRENT_FILE"
    echo "[DRY-RUN] Nenhuma alteração aplicada via crontab."
    exit 0
fi

crontab "$CURRENT_FILE"
echo "Crontab sincronizado com sucesso usando: $CANONICAL_FILE"
