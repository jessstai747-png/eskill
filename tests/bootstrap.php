<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load helpers
require_once __DIR__ . '/../app/Helpers/LogHelper.php';
require_once __DIR__ . '/../app/Helpers/CacheHelper.php';

// Load environment variables for testing (prefer .env.testing when present)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', ['.env.testing', '.env']);
$dotenv->safeLoad();

// Ensure variables from .env.testing are populated into $_ENV if Dotenv doesn't
$localEnv = __DIR__ . '/../.env.testing';
if (file_exists($localEnv)) {
    $lines = file($localEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, "=") === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        if ($k !== '') {
            $_ENV[$k] = $v;
            putenv($k . '=' . $v);
        }
    }
}

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['CACHE_ENABLED'] = false; // Disable cache in tests

// Make sessions safe in CLI tests even when there is output before session_start().
// PHPUnit may emit output (logs) during the run, and some services depend on $_SESSION.
// Disabling cookies and cache limiter prevents "headers already sent" warnings.
// Also, force save_path to a writable directory (sandbox environments may have read-only /var/lib/php/sessions
// and even a read-only /tmp). Use project storage for maximum portability.
$sessionSavePath = realpath(__DIR__ . '/../storage') ?: (__DIR__ . '/../storage');
$sessionSavePath = rtrim($sessionSavePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionSavePath)) {
    @mkdir($sessionSavePath, 0775, true);
}
@ini_set('session.save_path', $sessionSavePath);
@ini_set('session.use_cookies', '0');
@ini_set('session.use_only_cookies', '0');
@ini_set('session.cache_limiter', '');
@ini_set('session.cache_expire', '0');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Mock session if needed
if (!isset($_SESSION)) {
    $_SESSION = [];
}

// Detect PHPUnit testsuite from CLI args. Unit tests should not require DB connectivity.
$argv = $_SERVER['argv'] ?? [];
$isUnitSuite = false;
for ($i = 0; $i < count($argv); $i++) {
    // Supports: --testsuite Unit
    if ($argv[$i] === '--testsuite' && isset($argv[$i + 1]) && strcasecmp((string) $argv[$i + 1], 'Unit') === 0) {
        $isUnitSuite = true;
        break;
    }

    // Supports: --testsuite=Unit
    if (is_string($argv[$i]) && str_starts_with($argv[$i], '--testsuite=')) {
        $value = substr($argv[$i], strlen('--testsuite='));
        if (strcasecmp((string) $value, 'Unit') === 0) {
            $isUnitSuite = true;
            break;
        }
    }
}

if ($isUnitSuite) {
    error_log('[phpunit-bootstrap] Unit testsuite detected; skipping DB connectivity enforcement.');
    return;
}

// --- Enforce MySQL usage during tests -----------------------------------
// Log resolved DB env and ensure tests run against MySQL (abort if not)
$dbConn = trim((string)($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?? ''));
$dbHost = trim((string)($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? ''));
$dbPort = trim((string)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? ''));
$dbName = trim((string)($_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('DB_DATABASE') ?? ''));

if ($dbConn === '') {
    // Normalize empty string to null so defaults apply
    $dbConn = null;
}

error_log("[phpunit-bootstrap] Resolved DB_CONNECTION=" . ($dbConn ?? 'null') . ", DB_HOST=" . ($dbHost !== '' ? $dbHost : 'null') . ", DB_PORT=" . ($dbPort !== '' ? $dbPort : 'null') . ", DB_DATABASE=" . ($dbName !== '' ? $dbName : 'null'));

if ($dbConn === null) {
    // If not explicitly set, assume config default; but enforce mysql for tests
    $dbConn = 'mysql';
}

if (strtolower($dbConn) !== 'mysql') {
    throw new \Exception("PHPUnit aborted: DB_CONNECTION must be 'mysql' for tests. Resolved DB_CONNECTION={$dbConn}");
}

// Attempt to obtain a PDO instance from App\Database and verify driver
try {
    // Force database initialization and validate driver
    $pdo = \App\Database::getInstance();
    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    if ($driver !== 'mysql') {
        throw new \Exception("PHPUnit aborted: Detected PDO driver '{$driver}' (expected 'mysql').");
    }
    // Log a non-secret representation of DSN
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $dbHost ?? '127.0.0.1', $dbPort ?? '3306', $dbName ?? 'app_test');
    error_log("[phpunit-bootstrap] Resolved PDO DSN={$dsn}");
} catch (\Throwable $e) {
    // Rethrow to abort PHPUnit immediately
    throw $e;
}

