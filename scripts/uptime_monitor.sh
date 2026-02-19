#!/bin/bash

# ====================================
# SISTEMA DE MONITORAMENTO DE UPTIME
# Monitora disponibilidade e resposta da aplicação
# ====================================

set -e

# Configurações
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/../.env"
LOG_FILE="/var/log/ml_uptime.log"

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Função para log
log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
    
    case "$level" in
        "ERROR")
            echo -e "${RED}❌ $message${NC}"
            ;;
        "WARNING")
            echo -e "${YELLOW}⚠️ $message${NC}"
            ;;
        "INFO")
            echo -e "${GREEN}✅ $message${NC}"
            ;;
        "DEBUG")
            echo -e "${BLUE}🔍 $message${NC}"
            ;;
    esac
}

# Carregar configurações do .env
load_config() {
    if [ -f "$CONFIG_FILE" ]; then
        export $(grep -v '^#' "$CONFIG_FILE" | grep -v '^$' | xargs)
    fi
    
    # URLs para monitorar
    BASE_URL=${APP_URL:-"http://localhost"}
    
    ENDPOINTS=(
        "$BASE_URL/"
        "$BASE_URL/public/diagnostic.php"
        "$BASE_URL/dashboard"
        "$BASE_URL/auth/login"
    )
}

# Verificar conectividade de rede
check_network() {
    log_message "DEBUG" "Verificando conectividade de rede..."
    
    # Testar DNS
    if ! ping -c 1 8.8.8.8 >/dev/null 2>&1; then
        log_message "ERROR" "Falha na conectividade de rede (DNS)"
        return 1
    fi
    
    # Testar conectividade HTTP externa
    if ! curl -s --max-time 10 https://www.google.com >/dev/null 2>&1; then
        log_message "WARNING" "Conectividade HTTP externa limitada"
    fi
    
    log_message "INFO" "Conectividade de rede OK"
    return 0
}

# Verificar serviços do sistema
check_services() {
    log_message "DEBUG" "Verificando serviços do sistema..."
    
    local services=("apache2" "nginx" "mysql" "mariadb" "php8.1-fpm" "redis-server")
    local running_services=()
    local failed_services=()
    
    for service in "${services[@]}"; do
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            running_services+=("$service")
        else
            # Verificar se o serviço existe
            if systemctl list-units --full -all | grep -Fq "$service"; then
                failed_services+=("$service")
            fi
        fi
    done
    
    if [ ${#failed_services[@]} -gt 0 ]; then
        log_message "ERROR" "Serviços não rodando: ${failed_services[*]}"
        return 1
    fi
    
    if [ ${#running_services[@]} -gt 0 ]; then
        log_message "INFO" "Serviços ativos: ${running_services[*]}"
    fi
    
    return 0
}

# Verificar endpoint específico
check_endpoint() {
    local url="$1"
    local timeout="${2:-10}"
    local expected_code="${3:-200}"
    
    log_message "DEBUG" "Verificando endpoint: $url"
    
    local start_time=$(date +%s%3N)
    local response=$(curl -s -w "%{http_code}|%{time_total}|%{size_download}" \
                         --max-time "$timeout" \
                         --connect-timeout 5 \
                         -o /dev/null \
                         "$url" 2>/dev/null)
    local end_time=$(date +%s%3N)
    
    if [ $? -eq 0 ]; then
        local http_code=$(echo "$response" | cut -d'|' -f1)
        local time_total=$(echo "$response" | cut -d'|' -f2)
        local size=$(echo "$response" | cut -d'|' -f3)
        local response_time=$((end_time - start_time))
        
        if [ "$http_code" = "$expected_code" ]; then
            log_message "INFO" "Endpoint OK - $url (${response_time}ms, ${size} bytes)"
            return 0
        else
            log_message "ERROR" "Endpoint retornou código $http_code - $url"
            return 1
        fi
    else
        log_message "ERROR" "Endpoint inacessível - $url"
        return 1
    fi
}

# Verificar todos os endpoints
check_all_endpoints() {
    log_message "DEBUG" "Verificando todos os endpoints..."
    
    local failed_count=0
    local total_count=${#ENDPOINTS[@]}
    
    for endpoint in "${ENDPOINTS[@]}"; do
        if ! check_endpoint "$endpoint"; then
            ((failed_count++))
        fi
        sleep 1
    done
    
    local success_rate=$(( (total_count - failed_count) * 100 / total_count ))
    
    if [ $failed_count -eq 0 ]; then
        log_message "INFO" "Todos os endpoints OK (${total_count}/${total_count})"
        return 0
    elif [ $success_rate -ge 75 ]; then
        log_message "WARNING" "Alguns endpoints com problema ($success_rate% OK)"
        return 1
    else
        log_message "ERROR" "Muitos endpoints falhando ($success_rate% OK)"
        return 2
    fi
}

# Verificar banco de dados
check_database() {
    log_message "DEBUG" "Verificando banco de dados..."
    
    if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
        log_message "WARNING" "Configurações do banco não encontradas"
        return 1
    fi
    
    local start_time=$(date +%s%3N)
    if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1; then
        local end_time=$(date +%s%3N)
        local response_time=$((end_time - start_time))
        log_message "INFO" "Banco de dados OK (${response_time}ms)"
        return 0
    else
        log_message "ERROR" "Falha na conexão com banco de dados"
        return 1
    fi
}

# Verificar carga do sistema
check_system_load() {
    log_message "DEBUG" "Verificando carga do sistema..."
    
    local load_avg=($(cat /proc/loadavg | cut -d' ' -f1-3))
    local cpu_count=$(nproc)
    
    # Converter para inteiro para comparação
    local load_1m_int=$(echo "${load_avg[0]}" | cut -d'.' -f1)
    local threshold=$((cpu_count * 2))
    
    if [ "$load_1m_int" -gt "$threshold" ]; then
        log_message "ERROR" "Carga alta do sistema: ${load_avg[0]} (CPUs: $cpu_count)"
        return 1
    elif [ "$load_1m_int" -gt "$cpu_count" ]; then
        log_message "WARNING" "Carga moderada do sistema: ${load_avg[0]}"
        return 1
    else
        log_message "INFO" "Carga do sistema OK: ${load_avg[0]}"
        return 0
    fi
}

# Verificar espaço em disco
check_disk_space() {
    log_message "DEBUG" "Verificando espaço em disco..."
    
    local disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "$disk_usage" -gt 95 ]; then
        log_message "ERROR" "Espaço em disco crítico: ${disk_usage}%"
        return 2
    elif [ "$disk_usage" -gt 85 ]; then
        log_message "WARNING" "Espaço em disco baixo: ${disk_usage}%"
        return 1
    else
        log_message "INFO" "Espaço em disco OK: ${disk_usage}%"
        return 0
    fi
}

# Gerar métricas de uptime
generate_metrics() {
    local status="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local metrics_file="/var/log/ml_uptime_metrics.json"
    
    # Coletar métricas do sistema
    local load_avg=($(cat /proc/loadavg | cut -d' ' -f1-3))
    local disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    local memory_usage=$(free | awk 'NR==2{printf "%.1f", $3*100/$2}')
    
    # Criar entrada JSON
    local metric_entry=$(cat << EOF
{
  "timestamp": "$timestamp",
  "status": "$status",
  "metrics": {
    "load_1m": ${load_avg[0]},
    "load_5m": ${load_avg[1]},
    "load_15m": ${load_avg[2]},
    "disk_usage_percent": $disk_usage,
    "memory_usage_percent": $memory_usage
  }
}
EOF
    )
    
    echo "$metric_entry" >> "$metrics_file"
    
    # Manter apenas últimas 1000 entradas
    tail -n 1000 "$metrics_file" > "${metrics_file}.tmp" && mv "${metrics_file}.tmp" "$metrics_file"
}

# Enviar alerta
send_uptime_alert() {
    local status="$1"
    local details="$2"
    
    local message="🚨 ML Manager Uptime Alert

Status: $status
Timestamp: $(date '+%Y-%m-%d %H:%M:%S')

Detalhes:
$details"
    
    # Telegram
    if [ "$TELEGRAM_ENABLED" = "true" ] && [ -n "$TELEGRAM_BOT_TOKEN" ] && [ -n "$TELEGRAM_CHAT_ID" ]; then
        curl -s -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
             -d chat_id="$TELEGRAM_CHAT_ID" \
             -d text="$message" >/dev/null 2>&1 || true
    fi
    
    # Email
    if [ -n "$ALERT_EMAIL" ]; then
        echo "$message" | mail -s "ML Manager Uptime Alert" "$ALERT_EMAIL" 2>/dev/null || true
    fi
}

# Função principal de monitoramento
run_uptime_check() {
    log_message "INFO" "Iniciando verificação de uptime..."
    
    local overall_status="UP"
    local issues=()
    
    # Carregar configurações
    load_config
    
    # Verificar componentes
    if ! check_network; then
        overall_status="DOWN"
        issues+=("Conectividade de rede")
    fi
    
    if ! check_services; then
        overall_status="DEGRADED"
        issues+=("Serviços do sistema")
    fi
    
    if ! check_database; then
        overall_status="DOWN"
        issues+=("Banco de dados")
    fi
    
    local endpoint_result
    check_all_endpoints
    endpoint_result=$?
    
    if [ $endpoint_result -eq 2 ]; then
        overall_status="DOWN"
        issues+=("Endpoints críticos")
    elif [ $endpoint_result -eq 1 ]; then
        if [ "$overall_status" = "UP" ]; then
            overall_status="DEGRADED"
        fi
        issues+=("Alguns endpoints")
    fi
    
    if ! check_system_load; then
        if [ "$overall_status" = "UP" ]; then
            overall_status="DEGRADED"
        fi
        issues+=("Carga do sistema")
    fi
    
    local disk_result
    check_disk_space
    disk_result=$?
    
    if [ $disk_result -eq 2 ]; then
        overall_status="DOWN"
        issues+=("Espaço em disco crítico")
    elif [ $disk_result -eq 1 ]; then
        if [ "$overall_status" = "UP" ]; then
            overall_status="DEGRADED"
        fi
        issues+=("Espaço em disco baixo")
    fi
    
    # Gerar métricas
    generate_metrics "$overall_status"
    
    # Log do resultado final
    if [ "$overall_status" = "UP" ]; then
        log_message "INFO" "Sistema operacional - Status: $overall_status"
    elif [ "$overall_status" = "DEGRADED" ]; then
        log_message "WARNING" "Sistema com problemas - Status: $overall_status - Problemas: ${issues[*]}"
        send_uptime_alert "DEGRADED" "Problemas detectados: ${issues[*]}"
    else
        log_message "ERROR" "Sistema com falhas críticas - Status: $overall_status - Problemas: ${issues[*]}"
        send_uptime_alert "DOWN" "Falhas críticas: ${issues[*]}"
    fi
    
    log_message "INFO" "Verificação de uptime concluída"
    
    # Exit code baseado no status
    case "$overall_status" in
        "UP") exit 0 ;;
        "DEGRADED") exit 1 ;;
        "DOWN") exit 2 ;;
    esac
}

# Executar apenas se chamado diretamente
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    # Criar arquivo de log se não existir
    touch "$LOG_FILE" 2>/dev/null || true
    
    # Executar verificação
    run_uptime_check "$@"
fi