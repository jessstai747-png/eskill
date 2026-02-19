<?php

/**
 * Script de compatibilidade para executar migrations.
 *
 * Preferir: php bin/migrate.php
 *
 * Este wrapper mantém o comportamento legado de carregar .env.testing/.env
 * e delega a execução ao runner oficial (tracking + .sql + .php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente (prefere .env.testing quando presente)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', ['.env.testing', '.env']);
$dotenv->safeLoad();

// Ensure variables from .env.testing are populated into $_ENV (fallback)
$localEnv = __DIR__ . '/../.env.testing';
if (file_exists($localEnv)) {
    $lines = file($localEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
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

$runner = __DIR__ . '/../bin/migrate.php';
$args = array_slice($argv, 1);
$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($runner);
foreach ($args as $arg) {
    $cmd .= ' ' . escapeshellarg($arg);
}

passthru($cmd, $code);
exit((int)$code);
