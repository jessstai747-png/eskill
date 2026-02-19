<?php
// DISABLED - Setup complete. Delete this file.
http_response_code(404);
echo "Not Found";
exit;

/**
 * Setup Check - REMOVA APOS USO!
 */
// Token check disabled for initial setup - REMOVA ESTE ARQUIVO DEPOIS!
// if (($_GET['token'] ?? '') !== 'setup2026') { http_response_code(403); die('Forbidden'); }

header('Content-Type: text/plain; charset=utf-8');
echo "=== Setup Check ===\n\n";

$root = dirname(__DIR__);

// PHP
echo "PHP: " . PHP_VERSION . "\n";
foreach (['pdo','pdo_mysql','curl','json','mbstring','redis'] as $e) {
    echo "ext-$e: " . (extension_loaded($e) ? 'OK' : 'MISSING') . "\n";
}

// ENV
echo "\n.env: " . (file_exists("$root/.env") ? 'EXISTS' : 'MISSING') . "\n";
$env = [];
if (file_exists("$root/.env")) {
    foreach (file("$root/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
    echo "APP_KEY: " . (strlen($env['APP_KEY'] ?? '') >= 32 ? 'OK ('.strlen($env['APP_KEY']).' chars)' : 'TOO SHORT') . "\n";
    echo "ML_APP_ID: " . ($env['ML_APP_ID'] ?? 'NOT SET') . "\n";
    echo "QUEUE: " . ($env['QUEUE_CONNECTION'] ?? 'NOT SET (default sync)') . "\n";
    echo "TOKEN_REFRESH: " . ($env['TOKEN_REFRESH_MARGIN_MINUTES'] ?? 'NOT SET') . " min\n";
}

// DB
echo "\n--- DATABASE ---\n";
try {
    $h = $env['DB_HOST'] ?? 'localhost';
    $p = $env['DB_PORT'] ?? '3306';
    $d = $env['DB_DATABASE'] ?? $env['DB_NAME'] ?? 'meli';
    $u = $env['DB_USERNAME'] ?? $env['DB_USER'] ?? 'root';
    $pw = $env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? '';
    $pdo = new PDO("mysql:host=$h;port=$p;dbname=$d", $u, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "MySQL: OK - " . count($tables) . " tables in '$d'\n";
    
    if (in_array('ml_accounts', $tables)) {
        $rows = $pdo->query("SELECT id, nickname, status, token_expires_at, CASE WHEN access_token IS NOT NULL AND access_token != '' THEN 'YES' ELSE 'NO' END as has_token FROM ml_accounts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        echo "ml_accounts: " . count($rows) . " conta(s)\n";
        foreach ($rows as $r) echo "  #{$r['id']} {$r['nickname']} [{$r['status']}] token={$r['has_token']} exp={$r['token_expires_at']}\n";
    } else {
        echo "ml_accounts: TABLE MISSING\n";
    }
    
    if (in_array('migrations', $tables)) {
        echo "migrations: " . $pdo->query("SELECT COUNT(*) FROM migrations")->fetchColumn() . " applied\n";
    } else {
        echo "migrations: TABLE MISSING\n";
    }
    
    $need = ['items','ml_orders','users','refresh_tokens','sync_logs'];
    $miss = array_diff($need, $tables);
    echo "essential tables: " . (empty($miss) ? 'ALL OK' : 'MISSING: '.implode(', ', $miss)) . "\n";
    
} catch (PDOException $e) {
    echo "MySQL ERROR: " . $e->getMessage() . "\n";
}

// Storage
echo "\n--- STORAGE ---\n";
foreach (['storage/logs','storage/cache','storage/sessions','storage/locks'] as $dir) {
    $fp = "$root/$dir";
    if (!is_dir($fp)) { @mkdir($fp, 0775, true); echo "$dir: CREATED\n"; }
    else echo "$dir: " . (is_writable($fp) ? 'OK' : 'NOT WRITABLE') . "\n";
}

// Crontab
echo "\n--- CRONTAB ---\n";
$cron = @shell_exec('crontab -l 2>&1');
if ($cron && !str_contains($cron, 'no crontab')) {
    $active = count(array_filter(explode("\n", $cron), fn($l) => trim($l) && $l[0] !== '#'));
    echo "crontab: $active active jobs\n";
} else {
    echo "crontab: EMPTY or inaccessible\n";
}

// Key files
echo "\n--- KEY FILES ---\n";
foreach (['bin/migrate.php','bin/auto-token-refresh-worker.php','scripts/refresh_ml_tokens.php','scripts/poll_orders.php','current_crontab'] as $f) {
    echo "$f: " . (file_exists("$root/$f") ? 'OK' : 'MISSING') . "\n";
}

echo "\n=== DONE ===\n";
echo "REMOVA: rm public/setup_check.php\n";
