#!/usr/bin/env bash
##############################################################################
# bin/sandbox-test.sh — Start MySQL + Redis inside the sandbox and run tests
#
# The sandbox isolates each command in its own network namespace (bwrap
# --unshare-net --unshare-pid + seccomp). Therefore, MySQL, Redis, and
# all test clients MUST run inside the same command execution.
#
# Usage:
#   bash bin/sandbox-test.sh                 # Unit tests only (fast)
#   bash bin/sandbox-test.sh --integration   # Start MySQL + Redis + integration
#   bash bin/sandbox-test.sh --all           # Start MySQL + Redis + all suites
#   bash bin/sandbox-test.sh --services-only # Start MySQL + Redis, keep running
#
# Environment:
#   MYSQL_PORT   — default 3306
#   REDIS_PORT   — default 6379
#   SKIP_REDIS   — set to 1 to skip Redis
##############################################################################
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# ---------- Configuração ----------
STORAGE="$PROJECT_ROOT/storage"
MYSQL_DATADIR="$STORAGE/mysql-data"
MYSQL_LOGDIR="$STORAGE/mysql-log"
MYSQL_RUNDIR="$STORAGE/mysql-run"
MYSQL_TMPDIR="$STORAGE/mysql-tmp"
REDIS_CONF="$STORAGE/redis.conf"
SHIM_SO="$STORAGE/fake_unix_socket.so"
SANDBOX_ENV="$STORAGE/sandbox_env.php"

MYSQL_PORT="${MYSQL_PORT:-3306}"
REDIS_PORT="${REDIS_PORT:-6379}"

# DB credentials - FORCE override (system env may have different values)
DB_DATABASE="meli"
DB_USERNAME="eskill_app"
DB_PASSWORD="bd3a1cdee8f251c1bd073f601a87e8bd382d"

# PHP wrapper: auto_prepend forces $_ENV from getenv() + LD_PRELOAD for AF_UNIX shim
run_php() {
    LD_PRELOAD="$SHIM_SO" php -d "auto_prepend_file=$SANDBOX_ENV" "$@"
}

# ---------- Parse args ----------
MODE="unit"
for arg in "$@"; do
    case "$arg" in
        --integration) MODE="integration" ;;
        --all)         MODE="all" ;;
        --services-only) MODE="services" ;;
        --unit)        MODE="unit" ;;
        *)             echo "Unknown arg: $arg"; exit 1 ;;
    esac
done

# ---------- Funções de cleanup ----------
MYSQL_PID=""
REDIS_PID=""

cleanup() {
    echo ""
    echo "=== Cleanup ==="
    if [[ -n "$MYSQL_PID" ]] && kill -0 "$MYSQL_PID" 2>/dev/null; then
        echo "Stopping MySQL (PID $MYSQL_PID)..."
        kill "$MYSQL_PID" 2>/dev/null || true
        wait "$MYSQL_PID" 2>/dev/null || true
    fi
    if [[ -n "$REDIS_PID" ]] && kill -0 "$REDIS_PID" 2>/dev/null; then
        echo "Stopping Redis (PID $REDIS_PID)..."
        kill "$REDIS_PID" 2>/dev/null || true
        wait "$REDIS_PID" 2>/dev/null || true
    fi
    echo "Done."
}
trap cleanup EXIT

# ---------- Unit tests (no services needed) ----------
if [[ "$MODE" == "unit" ]]; then
    echo "=== Running Unit Tests (no DB required) ==="
    php vendor/bin/phpunit --testsuite=Unit
    exit $?
fi

# ---------- Verificar pré-requisitos ----------
echo "=== Sandbox Test Runner ==="
echo "Mode: $MODE"
echo "Project: $PROJECT_ROOT"

# Check mysqld
MYSQLD=$(command -v mysqld 2>/dev/null || echo "/usr/sbin/mysqld")
if [[ ! -x "$MYSQLD" ]]; then
    echo "ERROR: mysqld not found"
    exit 1
fi

# Check LD_PRELOAD shim
if [[ ! -f "$SHIM_SO" ]]; then
    echo "Compiling LD_PRELOAD shim..."
    gcc -shared -fPIC -o "$SHIM_SO" "$STORAGE/fake_unix_socket.c" -ldl
fi

# ---------- Preparar diretórios ----------
mkdir -p "$MYSQL_LOGDIR" "$MYSQL_RUNDIR" "$MYSQL_TMPDIR" "$MYSQL_DATADIR"

# ---------- Inicializar MySQL data se necessário ----------
if [[ ! -f "$MYSQL_DATADIR/ibdata1" ]]; then
    echo "Initializing MySQL data directory..."
    LD_PRELOAD="$SHIM_SO" "$MYSQLD" \
        --no-defaults \
        --initialize-insecure \
        --datadir="$MYSQL_DATADIR" \
        --tmpdir="$MYSQL_TMPDIR" \
        --log-error="$MYSQL_LOGDIR/init.log" \
        --user="$(whoami)" 2>/dev/null
    echo "MySQL data initialized."
fi

# ---------- Iniciar MySQL ----------
echo "Starting MySQL on port $MYSQL_PORT..."

LD_PRELOAD="$SHIM_SO" "$MYSQLD" \
    --no-defaults \
    --datadir="$MYSQL_DATADIR" \
    --tmpdir="$MYSQL_TMPDIR" \
    --port="$MYSQL_PORT" \
    --bind-address=127.0.0.1 \
    --skip-networking=OFF \
    --socket="$MYSQL_RUNDIR/mysql.sock" \
    --pid-file="$MYSQL_RUNDIR/mysql.pid" \
    --log-error="$MYSQL_LOGDIR/server.log" \
    --innodb-buffer-pool-size=64M \
    --innodb-log-file-size=16M \
    --innodb-flush-method=fsync \
    --innodb-flush-log-at-trx-commit=0 \
    --innodb-doublewrite=OFF \
    --performance-schema=OFF \
    --skip-log-bin \
    --max-connections=20 \
    --user="$(whoami)" \
    --mysqlx=0 \
    2>>"$MYSQL_LOGDIR/stderr.log" &
MYSQL_PID=$!
echo "MySQL PID: $MYSQL_PID"

# Wait for MySQL to be ready
echo -n "Waiting for MySQL..."
MYSQL_READY=0
for i in $(seq 1 30); do
    if mysql -h 127.0.0.1 -P "$MYSQL_PORT" --protocol=tcp -u root -e "SELECT 1" &>/dev/null; then
        MYSQL_READY=1
        break
    fi
    # Check if process died
    if ! kill -0 "$MYSQL_PID" 2>/dev/null; then
        echo ""
        echo "ERROR: MySQL process died. Check log:"
        tail -20 "$MYSQL_LOGDIR/server.log" 2>/dev/null || true
        exit 1
    fi
    echo -n "."
    sleep 1
done
echo ""

if [[ "$MYSQL_READY" -ne 1 ]]; then
    echo "ERROR: MySQL did not become ready in 30 seconds."
    echo "Last log lines:"
    tail -20 "$MYSQL_LOGDIR/server.log" 2>/dev/null || true
    exit 1
fi
echo "MySQL is ready!"

# ---------- Criar database e usuários ----------
echo "Setting up database and users..."
mysql -h 127.0.0.1 -P "$MYSQL_PORT" --protocol=tcp -u root <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`;
-- App user
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%';
-- Root user with same password (PHP migrations may connect as root)
ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
echo "Database '$DB_DATABASE' and users ready."

# ---------- Iniciar Redis ----------
if [[ "${SKIP_REDIS:-0}" != "1" ]]; then
    echo "Starting Redis on port $REDIS_PORT..."
    redis-server "$REDIS_CONF" --port "$REDIS_PORT" --daemonize no \
        --loglevel warning 2>>"$MYSQL_LOGDIR/redis-stderr.log" &
    REDIS_PID=$!
    echo "Redis PID: $REDIS_PID"

    # Quick check
    sleep 1
    if redis-cli -h 127.0.0.1 -p "$REDIS_PORT" PING 2>/dev/null | grep -q PONG; then
        echo "Redis is ready!"
    else
        echo "WARNING: Redis might not be ready (continuing anyway)"
    fi
fi

# ---------- Export env for PHP ----------
export APP_ENV=testing
export DB_HOST=127.0.0.1
export DB_PORT="$MYSQL_PORT"
export DB_DATABASE="$DB_DATABASE"
export DB_USERNAME="$DB_USERNAME"
export DB_PASSWORD="$DB_PASSWORD"
export PHPUNIT_REQUIRE_DB=1
# ---------- Run migrations ----------
echo ""
echo "=== Running Migrations ==="
run_php bin/migrate.php --testing 2>&1 || {
    echo "WARNING: Migrations had issues (some may already exist)"
}

# ---------- Services-only mode: wait ----------
if [[ "$MODE" == "services" ]]; then
    echo ""
    echo "=== Services Running ==="
    echo "MySQL: 127.0.0.1:$MYSQL_PORT (user: $DB_USERNAME)"
    echo "Redis: 127.0.0.1:$REDIS_PORT"
    echo "Database: $DB_DATABASE"
    echo ""
    echo "Press Ctrl+C to stop."
    wait
    exit 0
fi

# ---------- Run tests ----------
echo ""
echo "=== Running Tests ==="

case "$MODE" in
    integration)
        echo "Running Integration tests..."
        run_php vendor/bin/phpunit --testsuite=Integration
        ;;
    all)
        echo "Running ALL tests (Unit + Integration)..."
        run_php vendor/bin/phpunit --testsuite=Unit
        UNIT_EXIT=$?
        echo ""
        echo "--- Integration Suite ---"
        run_php vendor/bin/phpunit --testsuite=Integration
        INT_EXIT=$?
        if [[ $UNIT_EXIT -ne 0 || $INT_EXIT -ne 0 ]]; then
            echo "Some tests failed (Unit: $UNIT_EXIT, Integration: $INT_EXIT)"
            exit 1
        fi
        ;;
esac

echo ""
echo "=== All done ==="
