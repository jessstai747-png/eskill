#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Auth Monitor Status Dashboard
 * Exibe status rápido do sistema de monitoramento
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GeoIPService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configuração
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_DATABASE'] ?? 'meli';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ Erro de conexão: {$e->getMessage()}\n";
    exit(1);
}

// Initialize GeoIP service
$geoip = new GeoIPService();

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);

    return $stmt->fetchColumn() !== false;
}

// Header
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║           AUTH FAILURE MONITOR - STATUS DASHBOARD               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// 1. Status Geral
echo "📊 STATUS GERAL\n";
echo str_repeat("─", 70) . "\n";

$totalQueries = [];
if (tableExists($db, 'auth_blocked_ips')) {
    $totalQueries[] = "SELECT ip_address FROM auth_blocked_ips";
}
if (tableExists($db, 'blocked_ips')) {
    $totalQueries[] = "SELECT ip_address FROM blocked_ips";
}

$stmt = $db->query(
    $totalQueries !== []
        ? "SELECT COUNT(*) as total FROM (" . implode(' UNION ALL ', $totalQueries) . ") t"
        : "SELECT 0 as total"
);
$totalBlocks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$activeQueries = [];
if (tableExists($db, 'auth_blocked_ips')) {
    $activeQueries[] = "SELECT ip_address
        FROM auth_blocked_ips
        WHERE is_permanent = 1 OR expires_at IS NULL OR expires_at > NOW()";
}
if (tableExists($db, 'blocked_ips')) {
    $activeQueries[] = "SELECT ip_address
        FROM blocked_ips
        WHERE blocked_until IS NULL OR blocked_until > NOW()";
}

$stmt = $db->query(
    $activeQueries !== []
        ? "SELECT COUNT(DISTINCT ip_address) as active FROM (" . implode(' UNION ALL ', $activeQueries) . ") t"
        : "SELECT 0 as active"
);
$activeBlocks = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

$stmt = $db->query("SELECT COUNT(*) as total FROM auth_failure_log");
$totalFailures = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(DISTINCT ip_address) as unique_ips FROM auth_failure_log");
$uniqueIPs = $stmt->fetch(PDO::FETCH_ASSOC)['unique_ips'];

echo sprintf("  Total de Bloqueios: %d\n", $totalBlocks);
echo sprintf("  Bloqueios Ativos:   %d\n", $activeBlocks);
echo sprintf("  Falhas Registradas: %d\n", $totalFailures);
echo sprintf("  IPs Únicos:         %d\n", $uniqueIPs);
echo "\n";

// 2. IPs Bloqueados Ativos
echo "🚫 IPs BLOQUEADOS ATIVOS\n";
echo str_repeat("─", 70) . "\n";

$blockedQueries = [];
if (tableExists($db, 'auth_blocked_ips')) {
    $blockedQueries[] = "SELECT ip_address, country_code, country_name, city, failure_count, blocked_at, expires_at, 'auth_blocked_ips' AS source
        FROM auth_blocked_ips
        WHERE is_permanent = 1 OR expires_at IS NULL OR expires_at > NOW()";
}
if (tableExists($db, 'blocked_ips')) {
    $blockedQueries[] = "SELECT ip_address, NULL AS country_code, NULL AS country_name, NULL AS city, attempts AS failure_count,
           created_at AS blocked_at, blocked_until AS expires_at, 'blocked_ips' AS source
        FROM blocked_ips
        WHERE blocked_until IS NULL OR blocked_until > NOW()";
}

$stmt = $db->query(
    $blockedQueries !== []
        ? "SELECT ip_address, country_code, country_name, city, failure_count, blocked_at, expires_at, source
           FROM (" . implode(' UNION ALL ', $blockedQueries) . ") t
           ORDER BY blocked_at DESC
           LIMIT 10"
        : "SELECT NULL AS ip_address, NULL AS country_code, NULL AS country_name, NULL AS city,
                  0 AS failure_count, NULL AS blocked_at, NULL AS expires_at, NULL AS source
           WHERE 1 = 0"
);

$blockedIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($blockedIPs)) {
    echo "  ✅ Nenhum IP bloqueado no momento\n";
} else {
    foreach ($blockedIPs as $ip) {
        $expiresMin = null;
        if (!empty($ip['expires_at'])) {
            $expiresIn = strtotime($ip['expires_at']) - time();
            $expiresMin = floor($expiresIn / 60);
        }
        
        $location = '';
        if ($ip['country_code']) {
            $flag = $geoip->getCountryFlag($ip['country_code']);
            $location = " $flag ";
            if ($ip['city']) {
                $location .= $ip['city'] . ', ';
            }
            $location .= $ip['country_name'];
        }
        
        echo sprintf(
            "  🔒 %-15s %s [%s]\n",
            $ip['ip_address'],
            $location,
            $ip['source']
        );
        if ($expiresMin !== null) {
            echo sprintf(
                "      %3d falhas | Expira em %d minutos\n",
                $ip['failure_count'],
                $expiresMin
            );
        } else {
            echo sprintf(
                "      %3d falhas | Expiração: permanente\n",
                $ip['failure_count']
            );
        }
    }
}
echo "\n";

// 3. Últimas Falhas
echo "⚠️  ÚLTIMAS FALHAS DETECTADAS\n";
echo str_repeat("─", 70) . "\n";

$stmt = $db->query("
    SELECT ip_address, failure_type, detected_at
    FROM auth_failure_log
    ORDER BY id DESC
    LIMIT 5
");

$failures = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($failures as $failure) {
    $ago = time() - strtotime($failure['detected_at']);
    $agoMin = floor($ago / 60);
    echo sprintf(
        "  ⚡ %-15s | Tipo: %-5s | Há %d minutos\n",
        $failure['ip_address'],
        $failure['failure_type'] ?? 'N/A',
        $agoMin
    );
}
echo "\n";

// 4. Top Países Atacantes
if ($geoip->isEnabled()) {
    echo "🌍 TOP PAÍSES ATACANTES\n";
    echo str_repeat("─", 70) . "\n";
    
    $countryStats = $geoip->getCountryStats($db);
    
    if (!empty($countryStats)) {
        foreach ($countryStats as $idx => $stat) {
            if ($idx >= 10) break;
            
            $flag = $geoip->getCountryFlag($stat['country_code']);
            $bar = str_repeat("▓", min(30, (int)($stat['count'] / 20)));
            
            echo sprintf(
                "  %2d. %s %-20s | %4d ataques %s\n",
                $idx + 1,
                $flag,
                $stat['country_name'] ?? $stat['country_code'],
                $stat['count'],
                $bar
            );
        }
    } else {
        echo "  ⚠️  Nenhum dado de geolocalização disponível\n";
    }
    echo "\n";
}

// 5. Top 10 IPs com mais falhas
echo "🔝 TOP 10 IPs COM MAIS FALHAS\n";
echo str_repeat("─", 70) . "\n";

$stmt = $db->query("
    SELECT ip_address, COUNT(*) as count
    FROM auth_failure_log
    GROUP BY ip_address
    ORDER BY count DESC
    LIMIT 10
");

$topIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($topIPs as $idx => $ip) {
    $bar = str_repeat("█", min(50, (int)($ip['count'] / 2)));
    echo sprintf(
        "  %2d. %-15s | %4d falhas %s\n",
        $idx + 1,
        $ip['ip_address'],
        $ip['count'],
        $bar
    );
}
echo "\n";

// 5. Estatísticas de Tempo
echo "📅 ESTATÍSTICAS TEMPORAIS\n";
echo str_repeat("─", 70) . "\n";

$stmt = $db->query("
    SELECT 
        DATE(detected_at) as date,
        COUNT(*) as count
    FROM auth_failure_log
    WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(detected_at)
    ORDER BY date DESC
");

$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($dailyStats as $stat) {
    $bar = str_repeat("▓", min(50, (int)($stat['count'] / 20)));
    echo sprintf(
        "  %s | %4d falhas %s\n",
        $stat['date'],
        $stat['count'],
        $bar
    );
}
echo "\n";

// 6. Configuração Atual
echo "⚙️  CONFIGURAÇÃO\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("  Threshold de Bloqueio:    %d falhas\n", $_ENV['AUTH_BLOCK_THRESHOLD'] ?? 10);
echo sprintf("  Threshold de Alerta:      %d falhas\n", $_ENV['AUTH_FAILURE_ALERT_THRESHOLD'] ?? 50);
echo sprintf("  Duração do Bloqueio:      %d segundos (%.1f horas)\n", 
    $_ENV['AUTH_BLOCK_DURATION'] ?? 3600,
    ($_ENV['AUTH_BLOCK_DURATION'] ?? 3600) / 3600
);
echo sprintf("  Janela de Análise:        %d segundos (%.1f horas)\n",
    $_ENV['AUTH_TIME_WINDOW'] ?? 3600,
    ($_ENV['AUTH_TIME_WINDOW'] ?? 3600) / 3600
);
echo "\n";

// 7. Status do Cron
echo "🔄 CRON JOB\n";
echo str_repeat("─", 70) . "\n";

exec('crontab -l 2>/dev/null | grep monitor-auth-failures', $cronOutput, $cronReturn);

if ($cronReturn === 0 && !empty($cronOutput)) {
    echo "  ✅ Cron ativo: " . $cronOutput[0] . "\n";
} else {
    echo "  ❌ Cron não encontrado\n";
}

// Verificar último log do cron
$cronLogFile = __DIR__ . '/../storage/logs/auth_monitor_cron.log';
if (file_exists($cronLogFile)) {
    $lastLines = array_slice(file($cronLogFile), -3);
    echo "\n  Últimas linhas do log:\n";
    foreach ($lastLines as $line) {
        echo "  " . trim($line) . "\n";
    }
}
echo "\n";

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    Sistema Operacional                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
