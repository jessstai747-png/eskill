#!/bin/bash
# Sobe o MySQL de teste via docker compose e espera ficar healthy.
# Uso: bash bin/dev-test-db-up.sh

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

if command -v docker >/dev/null 2>&1; then
    :
else
    echo "ERRO: docker não encontrado no PATH" >&2
    exit 1
fi

COMPOSE_CMD=""
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
else
    echo "ERRO: nem 'docker compose' nem 'docker-compose' disponível" >&2
    exit 1
fi

echo "[dev-test-db] Subindo mysql_test..."
$COMPOSE_CMD up -d mysql_test

echo "[dev-test-db] Aguardando container healthy (ml_mysql_test)..."
for i in $(seq 1 90); do
    status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' ml_mysql_test 2>/dev/null || true)"
    if [ "$status" = "healthy" ]; then
        echo "[dev-test-db] OK: healthy"
        break
    fi

    if [ "$i" -eq 90 ]; then
        echo "ERRO: mysql_test não ficou healthy a tempo (status=$status)" >&2
        echo "Dica: veja logs com: $COMPOSE_CMD logs --tail=200 mysql_test" >&2
        exit 2
    fi

    sleep 1
done

echo "[dev-test-db] Validando conexão TCP 127.0.0.1:3307..."
if command -v mysql >/dev/null 2>&1; then
    mysql -h 127.0.0.1 -P 3307 -u testuser -ptestpass -e "SELECT 1 AS ok" app_test >/dev/null
    echo "[dev-test-db] OK: conexão MySQL" 
else
    echo "[dev-test-db] Aviso: mysql client não encontrado; pulando teste de conexão" >&2
fi

echo "[dev-test-db] Pronto."
