<?php
// DISABLED - Setup complete. Delete this file.
http_response_code(404);
echo "Not Found";
exit;

/**
 * Setup Actions - Executa migrations e instala crontab
 * REMOVA APÓS USO!
 */
set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}
clearstatcache(true);

$root = dirname(__DIR__);
$results = [];

echo "=== Setup Actions ===\n\n";

// Parse .env
$env = [];
foreach (file("$root/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

$action = $_GET['action'] ?? 'all';

// ============================================================
// ACTION: MIGRATE
// ============================================================
if ($action === 'all' || $action === 'migrate') {
    echo "--- RUNNING MIGRATIONS ---\n";
    
    // Double check .env is valid
    $envContent = file_get_contents("$root/.env");
    $lines = explode("\n", $envContent);
    $problemLines = [];
    foreach ($lines as $i => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        // Check for unquoted values with spaces
        if ($v !== '' && $v[0] !== '"' && $v[0] !== "'" && strpos($v, ' ') !== false) {
            $problemLines[] = "Line " . ($i + 1) . ": $k=$v";
        }
    }
    
    if (!empty($problemLines)) {
        echo "WARNING: .env has unquoted values with spaces:\n";
        foreach ($problemLines as $pl) echo "  $pl\n";
        echo "\n";
    }
    
    $output = [];
    $exitCode = 0;
    exec("cd $root && /usr/bin/php bin/migrate.php 2>&1", $output, $exitCode);
    echo implode("\n", $output) . "\n";
    echo "Exit code: $exitCode\n\n";
}

// ============================================================
// ACTION: CRONTAB
// ============================================================
if ($action === 'all' || $action === 'crontab') {
    echo "--- INSTALLING CRONTAB ---\n";
    
    // First check current crontab
    $currentCron = shell_exec('crontab -l 2>&1');
    $activeLines = 0;
    if ($currentCron && !str_contains($currentCron, 'no crontab')) {
        $activeLines = count(array_filter(explode("\n", $currentCron), fn($l) => trim($l) && $l[0] !== '#'));
    }
    echo "Current crontab: $activeLines active lines\n";
    
    if (file_exists("$root/current_crontab")) {
        $output = [];
        $exitCode = 0;
        exec("crontab $root/current_crontab 2>&1", $output, $exitCode);
        echo implode("\n", $output) . "\n";
        
        if ($exitCode === 0) {
            // Verify
            $newCron = shell_exec('crontab -l 2>&1');
            $newLines = count(array_filter(explode("\n", $newCron), fn($l) => trim($l) && $l[0] !== '#'));
            echo "Crontab installed: $newLines active lines\n";
        } else {
            echo "ERROR installing crontab (exit code $exitCode)\n";
        }
    } else {
        echo "ERROR: current_crontab not found\n";
    }
    echo "\n";
}

// ============================================================
// ACTION: VERIFY CRON SERVICE
// ============================================================
if ($action === 'all' || $action === 'cron-status') {
    echo "--- CRON SERVICE ---\n";
    $cronStatus = shell_exec('systemctl is-active cron 2>&1');
    echo "cron service: " . trim($cronStatus) . "\n";
    
    if (trim($cronStatus) !== 'active') {
        echo "Attempting to start cron...\n";
        $startResult = shell_exec('systemctl start cron 2>&1');
        echo ($startResult ?: "OK") . "\n";
        $cronStatus = shell_exec('systemctl is-active cron 2>&1');
        echo "cron service now: " . trim($cronStatus) . "\n";
    }
    echo "\n";
}

// ============================================================
// ACTION: TOKEN STATUS
// ============================================================
if ($action === 'all' || $action === 'tokens') {
    echo "--- TOKEN STATUS ---\n";
    try {
        $h = $env['DB_HOST'] ?? 'localhost';
        $p = $env['DB_PORT'] ?? '3306';
        $d = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? 'meli';
        $u = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? 'root';
        $pw = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? '';
        $pdo = new PDO("mysql:host=$h;port=$p;dbname=$d", $u, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        $rows = $pdo->query("SELECT id, nickname, status, token_expires_at, 
            CASE WHEN access_token IS NOT NULL AND access_token != '' THEN 'HAS_TOKEN' ELSE 'NO_TOKEN' END as token_status,
            CASE WHEN refresh_token IS NOT NULL AND refresh_token != '' THEN 'HAS_REFRESH' ELSE 'NO_REFRESH' END as refresh_status
            FROM ml_accounts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $r) {
            $exp = $r['token_expires_at'] ?? 'N/A';
            $expTs = strtotime($exp);
            $isExpired = $expTs && $expTs < time();
            $status = $isExpired ? 'EXPIRED' : 'VALID';
            echo "#{$r['id']} {$r['nickname']}: db_status={$r['status']} token={$r['token_status']} refresh={$r['refresh_status']} expires=$exp ($status)\n";
        }
        
    } catch (PDOException $e) {
        echo "DB ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// ============================================================
// ACTION: MIGRATION STATUS
// ============================================================
if ($action === 'all' || $action === 'migration-status') {
    echo "--- MIGRATION STATUS ---\n";
    $output = [];
    exec("cd $root && /usr/bin/php bin/migrate.php --status 2>&1", $output, $exitCode);
    // Limit output to last 30 lines
    $out = array_slice($output, -30);
    echo implode("\n", $out) . "\n\n";
}

echo "=== DONE ===\n";
echo "REMOVA: rm public/setup_action.php\n";
