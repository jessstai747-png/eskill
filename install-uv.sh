#!/bin/bash
# Script para instalar dependências do sandbox e uv
set -e

echo "=== Instalando dependências do sandbox do VS Code ==="
if command -v apt-get &> /dev/null; then
    sudo apt-get update
    sudo apt-get install -y ripgrep bubblewrap socat
elif command -v yum &> /dev/null; then
    sudo yum install -y ripgrep bubblewrap socat
elif command -v pacman &> /dev/null; then
    sudo pacman -S --noconfirm ripgrep bubblewrap socat
fi

echo ""
echo "=== Instalando uv (Astral) ==="
curl -LsSf https://astral.sh/uv/install.sh | sh

echo ""
echo "=== Recarregando PATH ==="
export PATH="$HOME/.local/bin:$PATH"

echo ""
echo "=== Verificando instalação ==="
uv --version

echo ""
echo "=== Concluído! ==="
echo "Execute: source ~/.bashrc"
echo "Depois reconecte a sessão remota do VS Code (Ctrl+Shift+P > Reload Window)"
