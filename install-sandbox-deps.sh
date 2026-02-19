#!/bin/bash
# Script de instalação de dependências do sandbox VSCode/GitHub Copilot
# Execute este script no HOST (fora do sandbox) para habilitar terminal

set -e

echo "=== Instalação de Dependências do Sandbox ==="
echo ""

# Detectar distro
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VER=$VERSION_ID
else
    echo "Não foi possível detectar a distribuição Linux."
    exit 1
fi

echo "Sistema detectado: $OS $VER"
echo ""

# Instalação baseada na distro
case "$OS" in
    ubuntu|debian)
        echo "Instalando via apt-get..."
        sudo apt-get update
        sudo apt-get install -y ripgrep bubblewrap socat
        ;;
    fedora|rhel|centos)
        echo "Instalando via dnf/yum..."
        if command -v dnf &> /dev/null; then
            sudo dnf install -y ripgrep bubblewrap socat
        else
            sudo yum install -y ripgrep bubblewrap socat
        fi
        ;;
    arch|manjaro)
        echo "Instalando via pacman..."
        sudo pacman -Sy --noconfirm ripgrep bubblewrap socat
        ;;
    *)
        echo "Distro não suportada automaticamente: $OS"
        echo "Instale manualmente: ripgrep, bubblewrap, socat"
        exit 1
        ;;
esac

echo ""
echo "=== Verificação de Instalação ==="

# Verificar ripgrep
if command -v rg &> /dev/null; then
    echo "✓ ripgrep instalado: $(rg --version | head -n1)"
else
    echo "✗ ripgrep NÃO encontrado"
    exit 1
fi

# Verificar bubblewrap
if command -v bwrap &> /dev/null; then
    echo "✓ bubblewrap instalado: $(bwrap --version 2>&1 | head -n1 || echo 'versão indisponível')"
else
    echo "✗ bubblewrap NÃO encontrado"
    exit 1
fi

# Verificar socat
if command -v socat &> /dev/null; then
    echo "✓ socat instalado: $(socat -V 2>&1 | head -n1)"
else
    echo "✗ socat NÃO encontrado"
    exit 1
fi

echo ""
echo "=== Instalação Concluída com Sucesso ==="
echo "Agora você pode usar os terminais do VSCode/GitHub Copilot."
echo ""
echo "Execute os testes:"
echo "  cd /home/eskill/htdocs/eskill.com.br"
echo "  composer test-unit"
