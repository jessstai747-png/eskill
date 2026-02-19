#!/bin/bash
# Preflight único para execução ML-only no host/VM.
# Checa:
# - Ferramentas básicas (php/composer/node/npm/docker)
# - Arquivos esperados (.env.testing, vendor/autoload.php)
# - Plataforma PHP (extensões) via composer check-platform-reqs
# - Acesso ao Docker daemon
# - Conexão DB e tabelas básicas via ml-auto-improve --preflight
# Uso:
#   bash bin/dev-preflight.sh

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

fail() {
    echo "ERRO: $1" >&2
    exit 1
}

warn() {
    echo "AVISO: $1" >&2
}

echo "[preflight] Iniciando..."

echo "[preflight] Checando .env.testing..."
if [ ! -f .env.testing ]; then
    fail ".env.testing não encontrado (necessário para DB de teste)."
fi

if ! command -v php >/dev/null 2>&1; then
    fail "php não encontrado no PATH"
fi

echo "[preflight] PHP: $(php -r 'echo PHP_VERSION;')"

echo "[preflight] Checando vendor/autoload.php..."
if [ ! -f vendor/autoload.php ]; then
    fail "vendor/autoload.php ausente. Rode: composer install"
fi

if command -v composer >/dev/null 2>&1; then
    echo "[preflight] Composer: $(composer --version | head -n 1)"

    mkdir -p storage/tmp storage/cache/composer
    export COMPOSER_TMPDIR="$PROJECT_DIR/storage/tmp"
    export COMPOSER_CACHE_DIR="$PROJECT_DIR/storage/cache/composer"

    echo "[preflight] Composer validate..."
    if ! composer validate --no-interaction >/dev/null; then
        warn "composer validate falhou (composer.lock pode estar desatualizado)."
    else
        echo "[preflight] Composer validate: OK"
    fi

    echo "[preflight] Composer check-platform-reqs..."
    composer check-platform-reqs --no-interaction >/dev/null
    echo "[preflight] Composer platform reqs: OK"
else
    warn "composer não encontrado; pulando validação/reqs de plataforma"
fi

if command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1; then
    echo "[preflight] Node: $(node -v)"
    echo "[preflight] npm: $(npm -v)"

    if [ -f package-lock.json ]; then
        echo "[preflight] npm ci --dry-run..."
        npm ci --dry-run >/dev/null
        echo "[preflight] npm ci --dry-run: OK"
    else
        warn "package-lock.json ausente; pulando npm ci --dry-run"
    fi
else
    warn "node/npm não encontrados; pulando checagens JS"
fi

if command -v docker >/dev/null 2>&1; then
    echo "[preflight] Docker: OK (binário encontrado)"
    if ! docker info >/dev/null 2>&1; then
        fail "sem acesso ao Docker daemon. Verifique permissões do socket /var/run/docker.sock (ou use sudo)."
    fi

    if docker compose version >/dev/null 2>&1; then
        echo "[preflight] docker compose: OK"
    elif command -v docker-compose >/dev/null 2>&1; then
        echo "[preflight] docker-compose: OK"
    else
        fail "nem 'docker compose' nem 'docker-compose' disponível"
    fi
else
    warn "docker não encontrado; scripts de DB de teste não funcionarão"
fi

echo "[preflight] Verificando conexão DB via ml-auto-improve --preflight..."
php bin/ml-auto-improve.php --preflight

echo "[preflight] OK."
