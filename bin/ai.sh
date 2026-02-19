#!/bin/bash
#
# AI Optimization System - Quick Commands
# Useful shortcuts for common operations
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
function show_help() {
    echo "🤖 AI Optimization - Quick Commands"
    echo "===================================="
    echo ""
    echo "Usage: ./bin/ai.sh <command>"
    echo ""
    echo "Commands:"
    echo "  status        - Show system status"
    echo "  queue         - Monitor queue"
    echo "  costs         - Show cost report"
    echo "  worker start  - Start background worker"
    echo "  worker stop   - Stop background worker"
    echo "  worker logs   - View worker logs"
    echo "  test          - Run system tests"
    echo "  setup         - Run setup check"
    echo "  migrate       - Run database migrations"
    echo "  clean         - Clean failed queue items"
    echo ""
}

function show_status() {
    echo -e "${BLUE}📊 System Status${NC}"
    php "$SCRIPT_DIR/ai-setup-check.php"
}

function show_queue() {
    echo -e "${BLUE}📋 Queue Monitor${NC}"
    php "$SCRIPT_DIR/ai-queue-monitor.php"
}

function show_costs() {
    local days=${1:-30}
    echo -e "${BLUE}💰 Cost Report (Last $days days)${NC}"
    php "$SCRIPT_DIR/ai-cost-report.php" "$days"
}

function worker_start() {
    echo -e "${BLUE}🚀 Starting AI Worker...${NC}"
    
    # Check if already running
    if pgrep -f "ai-worker.php" > /dev/null; then
        echo -e "${YELLOW}⚠️  Worker already running${NC}"
        pgrep -f "ai-worker.php" | while read pid; do
            echo "   PID: $pid"
        done
        return 1
    fi
    
    # Start worker
    nohup php "$SCRIPT_DIR/ai-worker.php" > "$PROJECT_DIR/storage/logs/ai-worker.log" 2>&1 &
    WORKER_PID=$!
    
    sleep 2
    
    if ps -p $WORKER_PID > /dev/null; then
        echo -e "${GREEN}✅ Worker started (PID: $WORKER_PID)${NC}"
        echo "   Logs: tail -f $PROJECT_DIR/storage/logs/ai-worker.log"
    else
        echo -e "${RED}❌ Failed to start worker${NC}"
        return 1
    fi
}

function worker_stop() {
    echo -e "${BLUE}🛑 Stopping AI Worker...${NC}"
    
    if ! pgrep -f "ai-worker.php" > /dev/null; then
        echo -e "${YELLOW}⚠️  No worker running${NC}"
        return 1
    fi
    
    pkill -f "ai-worker.php"
    sleep 1
    
    if ! pgrep -f "ai-worker.php" > /dev/null; then
        echo -e "${GREEN}✅ Worker stopped${NC}"
    else
        echo -e "${RED}❌ Failed to stop worker${NC}"
        return 1
    fi
}

function worker_logs() {
    local lines=${1:-50}
    echo -e "${BLUE}📄 Worker Logs (Last $lines lines)${NC}"
    tail -n "$lines" "$PROJECT_DIR/storage/logs/ai-worker.log"
}

function run_tests() {
    echo -e "${BLUE}🧪 Running Tests...${NC}"
    # TODO: Implement tests
    echo "Tests not yet implemented"
}

function run_setup() {
    echo -e "${BLUE}⚙️  Running Setup Check...${NC}"
    php "$SCRIPT_DIR/ai-setup-check.php"
}

function run_migrate() {
    echo -e "${BLUE}🗄️  Running Migrations...${NC}"
    php "$PROJECT_DIR/scripts/migrate.php"
}

function clean_queue() {
    echo -e "${BLUE}🧹 Cleaning Failed Queue Items...${NC}"
    
    read -p "Delete all failed items? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        php -r "
            require '$PROJECT_DIR/vendor/autoload.php';
            \$db = App\Database::getInstance();
            \$stmt = \$db->prepare('DELETE FROM ai_optimization_queue WHERE status = ?');
            \$result = \$stmt->execute(['failed']);
            echo 'Deleted ' . \$stmt->rowCount() . ' failed items\n';
        "
        echo -e "${GREEN}✅ Queue cleaned${NC}"
    else
        echo "Cancelled"
    fi
}

# Main
case "${1:-}" in
    status)
        show_status
        ;;
    queue)
        show_queue
        ;;
    costs)
        show_costs "${2:-30}"
        ;;
    worker)
        case "${2:-}" in
            start)
                worker_start
                ;;
            stop)
                worker_stop
                ;;
            logs)
                worker_logs "${3:-50}"
                ;;
            *)
                echo "Usage: ./bin/ai.sh worker {start|stop|logs}"
                exit 1
                ;;
        esac
        ;;
    test)
        run_tests
        ;;
    setup)
        run_setup
        ;;
    migrate)
        run_migrate
        ;;
    clean)
        clean_queue
        ;;
    help|--help|-h|"")
        show_help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac
