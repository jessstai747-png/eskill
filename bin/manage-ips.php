#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Auth Monitor - IP Management CLI
 * Gerenciar whitelist/blacklist permanente
 */

require_once __DIR__ . '/../vendor/autoload.php';

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

// Parse argumentos
$command = $argv[1] ?? 'help';
$ip = $argv[2] ?? null;
$reason = $argv[3] ?? 'Manual management';

function showHelp(): void
{
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║         AUTH MONITOR - IP MANAGEMENT CLI                     ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Uso: php bin/manage-ips.php <command> [ip] [reason]\n";
    echo "\n";
    echo "Comandos Disponíveis:\n";
    echo "  block <ip> [reason]     - Bloquear IP permanentemente\n";
    echo "  unblock <ip>            - Desbloquear IP\n";
    echo "  whitelist <ip>          - Adicionar IP à whitelist\n";
    echo "  remove-whitelist <ip>   - Remover IP da whitelist\n";
    echo "  list-blocked            - Listar IPs bloqueados permanentemente\n";
    echo "  list-whitelist          - Listar IPs na whitelist\n";
    echo "  info <ip>               - Informações detalhadas sobre IP\n";
    echo "  help                    - Exibir esta ajuda\n";
    echo "\n";
    echo "Exemplos:\n";
    echo "  php bin/manage-ips.php block 192.168.1.100 \"IP malicioso confirmado\"\n";
    echo "  php bin/manage-ips.php unblock 192.168.1.100\n";
    echo "  php bin/manage-ips.php whitelist 187.111.220.84\n";
    echo "  php bin/manage-ips.php list-blocked\n";
    echo "  php bin/manage-ips.php info 201.47.36.86\n";
    echo "\n";
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SHOW TABLES LIKE :table');
    $stmt->execute([':table' => $table]);

    return $stmt->fetchColumn() !== false;
}

/**
 * @return array<int, string>
 */
function getBlockTables(PDO $db): array
{
    $tables = [];

    foreach (['blocked_ips', 'auth_blocked_ips'] as $table) {
        if (tableExists($db, $table)) {
            $tables[] = $table;
        }
    }

    return $tables;
}

function blockIP(PDO $db, string $ip, string $reason): void
{
    echo "🔒 Bloqueando IP $ip permanentemente...\n";

    $tables = getBlockTables($db);
    if ($tables === []) {
        echo "❌ Nenhuma tabela de bloqueio encontrada.\n";
        return;
    }

    $alreadyBlocked = false;

    foreach ($tables as $table) {
        if ($table === 'blocked_ips') {
            $stmt = $db->prepare("
                SELECT id FROM blocked_ips
                WHERE ip_address = :ip
                  AND blocked_until IS NULL
                LIMIT 1
            ");
            $stmt->execute([':ip' => $ip]);
            $alreadyBlocked = $alreadyBlocked || $stmt->fetch() !== false;

            $stmt = $db->prepare("
                INSERT INTO blocked_ips (ip_address, reason, blocked_by, blocked_until, attempts)
                VALUES (:ip, :reason, 'CLI', NULL, 1)
                ON DUPLICATE KEY UPDATE
                    reason = VALUES(reason),
                    blocked_by = 'CLI',
                    blocked_until = NULL,
                    attempts = attempts + 1,
                    updated_at = NOW()
            ");
            $stmt->execute([
                ':ip' => $ip,
                ':reason' => $reason,
            ]);
            continue;
        }

        $stmt = $db->prepare("
            SELECT id FROM auth_blocked_ips
            WHERE ip_address = :ip AND is_permanent = 1
            LIMIT 1
        ");
        $stmt->execute([':ip' => $ip]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $alreadyBlocked = $alreadyBlocked || $existing !== false;

        if ($existing !== false) {
            $stmt = $db->prepare("
                UPDATE auth_blocked_ips
                SET reason = :reason,
                    expires_at = NULL,
                    is_permanent = 1,
                    created_by = 'CLI'
                WHERE id = :id
            ");
            $stmt->execute([
                ':reason' => $reason,
                ':id' => $existing['id'],
            ]);
            continue;
        }

        $stmt = $db->prepare("
            INSERT INTO auth_blocked_ips
            (ip_address, reason, failure_count, blocked_at, expires_at, is_permanent, created_by)
            VALUES (:ip, :reason, 0, NOW(), NULL, 1, 'CLI')
        ");
        $stmt->execute([
            ':ip' => $ip,
            ':reason' => $reason,
        ]);
    }

    if ($alreadyBlocked) {
        echo "⚠️  IP $ip já constava como bloqueado em pelo menos uma tabela.\n";
    }

    echo "✅ IP $ip bloqueado permanentemente!\n";
    echo "   Tabelas: " . implode(', ', $tables) . "\n";
    echo "   Motivo: $reason\n";
}

function unblockIP(PDO $db, string $ip): void
{
    echo "🔓 Desbloqueando IP $ip...\n";

    $affected = 0;
    foreach (getBlockTables($db) as $table) {
        if ($table === 'blocked_ips') {
            $stmt = $db->prepare("DELETE FROM blocked_ips WHERE ip_address = :ip");
        } else {
            $stmt = $db->prepare("DELETE FROM auth_blocked_ips WHERE ip_address = :ip");
        }
        $stmt->execute([':ip' => $ip]);
        $affected += $stmt->rowCount();
    }

    if ($affected > 0) {
        echo "✅ IP $ip desbloqueado com sucesso!\n";
    } else {
        echo "⚠️  IP $ip não estava bloqueado nas tabelas conhecidas.\n";
    }
}

function addToWhitelist(string $ip): void
{
    echo "⚪ Adicionando IP $ip à whitelist...\n";
    
    $envFile = __DIR__ . '/../.env';
    $envContent = file_get_contents($envFile);
    
    // Verificar se já está na whitelist
    if (preg_match('/AUTH_IP_WHITELIST=(.*)/', $envContent, $matches)) {
        $currentWhitelist = $matches[1];
        $ips = array_map('trim', explode(',', $currentWhitelist));
        
        if (in_array($ip, $ips)) {
            echo "⚠️  IP $ip já está na whitelist!\n";
            return;
        }
        
        $ips[] = $ip;
        $newWhitelist = implode(',', $ips);
        
        $envContent = preg_replace(
            '/AUTH_IP_WHITELIST=.*/',
            "AUTH_IP_WHITELIST=$newWhitelist",
            $envContent
        );
    } else {
        // Adicionar linha se não existir
        $envContent .= "\nAUTH_IP_WHITELIST=$ip\n";
    }
    
    file_put_contents($envFile, $envContent);
    echo "✅ IP $ip adicionado à whitelist!\n";
    echo "⚠️  ATENÇÃO: Reinicie o cron para aplicar as mudanças.\n";
}

function removeFromWhitelist(string $ip): void
{
    echo "🔴 Removendo IP $ip da whitelist...\n";
    
    $envFile = __DIR__ . '/../.env';
    $envContent = file_get_contents($envFile);
    
    if (preg_match('/AUTH_IP_WHITELIST=(.*)/', $envContent, $matches)) {
        $currentWhitelist = $matches[1];
        $ips = array_map('trim', explode(',', $currentWhitelist));
        
        if (!in_array($ip, $ips)) {
            echo "⚠️  IP $ip não está na whitelist!\n";
            return;
        }
        
        $ips = array_filter($ips, fn($i) => $i !== $ip);
        $newWhitelist = implode(',', $ips);
        
        $envContent = preg_replace(
            '/AUTH_IP_WHITELIST=.*/',
            "AUTH_IP_WHITELIST=$newWhitelist",
            $envContent
        );
        
        file_put_contents($envFile, $envContent);
        echo "✅ IP $ip removido da whitelist!\n";
        echo "⚠️  ATENÇÃO: Reinicie o cron para aplicar as mudanças.\n";
    } else {
        echo "⚠️  Whitelist não configurada no .env\n";
    }
}

function listBlocked(PDO $db): void
{
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║         IPs BLOQUEADOS PERMANENTEMENTE                        ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    $blocked = [];

    if (tableExists($db, 'blocked_ips')) {
        $stmt = $db->query("
            SELECT ip_address, reason, created_at AS blocked_at, blocked_by AS created_by, 'blocked_ips' AS source
            FROM blocked_ips
            WHERE blocked_until IS NULL OR blocked_until > NOW()
        ");
        $blocked = array_merge($blocked, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (tableExists($db, 'auth_blocked_ips')) {
        $stmt = $db->query("
            SELECT ip_address, reason, blocked_at, created_by, 'auth_blocked_ips' AS source
            FROM auth_blocked_ips
            WHERE is_permanent = 1 OR expires_at IS NULL OR expires_at > NOW()
        ");
        $blocked = array_merge($blocked, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    if (empty($blocked)) {
        echo "✅ Nenhum IP bloqueado permanentemente.\n\n";
        return;
    }
    
    usort($blocked, static fn(array $a, array $b): int => strcmp((string)($b['blocked_at'] ?? ''), (string)($a['blocked_at'] ?? '')));

    foreach ($blocked as $ip) {
        echo sprintf(
            "🔒 %-15s | Bloqueado em: %s | Por: %s | Fonte: %s\n",
            $ip['ip_address'],
            $ip['blocked_at'],
            $ip['created_by'],
            $ip['source']
        );
        echo "   Motivo: {$ip['reason']}\n\n";
    }
    
    echo "Total: " . count($blocked) . " IPs\n\n";
}

function listWhitelist(): void
{
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║         IPs NA WHITELIST                                      ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    $envFile = __DIR__ . '/../.env';
    $envContent = file_get_contents($envFile);
    
    if (preg_match('/AUTH_IP_WHITELIST=(.*)/', $envContent, $matches)) {
        $ips = array_map('trim', explode(',', $matches[1]));
        
        foreach ($ips as $idx => $ip) {
            echo sprintf("%2d. ⚪ %s\n", $idx + 1, $ip);
        }
        
        echo "\nTotal: " . count($ips) . " IPs\n\n";
    } else {
        echo "⚠️  Whitelist não configurada no .env\n\n";
    }
}

function showIPInfo(PDO $db, string $ip): void
{
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║         INFORMAÇÕES DO IP: $ip                     ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    $blocks = [];

    if (tableExists($db, 'blocked_ips')) {
        $stmt = $db->prepare("
            SELECT ip_address, reason, attempts AS failure_count, created_at AS blocked_at,
                   blocked_until AS expires_at, blocked_by AS created_by, 'blocked_ips' AS source,
                   CASE WHEN blocked_until IS NULL THEN 1 ELSE 0 END AS is_permanent
            FROM blocked_ips
            WHERE ip_address = :ip
        ");
        $stmt->execute([':ip' => $ip]);
        $blocks = array_merge($blocks, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (tableExists($db, 'auth_blocked_ips')) {
        $stmt = $db->prepare("
            SELECT ip_address, reason, failure_count, blocked_at, expires_at, created_by,
                   'auth_blocked_ips' AS source, is_permanent
            FROM auth_blocked_ips
            WHERE ip_address = :ip
        ");
        $stmt->execute([':ip' => $ip]);
        $blocks = array_merge($blocks, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($blocks !== []) {
        usort($blocks, static fn(array $a, array $b): int => strcmp((string)($b['blocked_at'] ?? ''), (string)($a['blocked_at'] ?? '')));

        foreach ($blocks as $blockInfo) {
            $isPermanent = (int)($blockInfo['is_permanent'] ?? 0) === 1;
            $expiresAt = $blockInfo['expires_at'] ?? null;
            $status = $isPermanent
                ? '🔒 Bloqueado PERMANENTEMENTE'
                : ($expiresAt !== null && strtotime((string)$expiresAt) > time()
                    ? '⏳ Bloqueado temporariamente (expira em ' . $expiresAt . ')'
                    : '✅ Bloqueio expirado');

            echo "Status: $status | Fonte: {$blockInfo['source']}\n";
            echo "Motivo: {$blockInfo['reason']}\n";
            echo "Falhas registradas: {$blockInfo['failure_count']}\n";
            echo "Bloqueado em: {$blockInfo['blocked_at']}\n";
            echo "Bloqueado por: {$blockInfo['created_by']}\n\n";
        }
    } else {
        echo "Status: ✅ Não bloqueado\n\n";
    }
    
    // Estatísticas de falhas
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            MIN(detected_at) as first_seen,
            MAX(detected_at) as last_seen
        FROM auth_failure_log 
        WHERE ip_address = :ip
    ");
    $stmt->execute([':ip' => $ip]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats['total'] > 0) {
        echo "📊 Estatísticas de Falhas:\n";
        echo "   Total de tentativas: {$stats['total']}\n";
        echo "   Primeira detecção: {$stats['first_seen']}\n";
        echo "   Última detecção: {$stats['last_seen']}\n\n";
        
        // Últimas 5 falhas
        $stmt = $db->prepare("
            SELECT detected_at, failure_type 
            FROM auth_failure_log 
            WHERE ip_address = :ip 
            ORDER BY detected_at DESC 
            LIMIT 5
        ");
        $stmt->execute([':ip' => $ip]);
        $failures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "⚠️  Últimas 5 Tentativas:\n";
        foreach ($failures as $f) {
            echo "   - {$f['detected_at']} | Tipo: {$f['failure_type']}\n";
        }
        echo "\n";
    } else {
        echo "📊 Nenhuma falha registrada para este IP.\n\n";
    }
    
    // Verificar whitelist
    $envFile = __DIR__ . '/../.env';
    $envContent = file_get_contents($envFile);
    
    if (preg_match('/AUTH_IP_WHITELIST=(.*)/', $envContent, $matches)) {
        $ips = array_map('trim', explode(',', $matches[1]));
        if (in_array($ip, $ips)) {
            echo "⚪ Este IP está na WHITELIST (nunca será bloqueado)\n\n";
        }
    }
}

// Executar comando
switch ($command) {
    case 'block':
        if (!$ip) {
            echo "❌ Erro: IP não informado!\n";
            showHelp();
            exit(1);
        }
        blockIP($db, $ip, $reason);
        break;
    
    case 'unblock':
        if (!$ip) {
            echo "❌ Erro: IP não informado!\n";
            showHelp();
            exit(1);
        }
        unblockIP($db, $ip);
        break;
    
    case 'whitelist':
        if (!$ip) {
            echo "❌ Erro: IP não informado!\n";
            showHelp();
            exit(1);
        }
        addToWhitelist($ip);
        break;
    
    case 'remove-whitelist':
        if (!$ip) {
            echo "❌ Erro: IP não informado!\n";
            showHelp();
            exit(1);
        }
        removeFromWhitelist($ip);
        break;
    
    case 'list-blocked':
        listBlocked($db);
        break;
    
    case 'list-whitelist':
        listWhitelist();
        break;
    
    case 'info':
        if (!$ip) {
            echo "❌ Erro: IP não informado!\n";
            showHelp();
            exit(1);
        }
        showIPInfo($db, $ip);
        break;
    
    case 'help':
    default:
        showHelp();
        break;
}
