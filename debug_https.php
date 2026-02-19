<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "getenv FORCE_HTTPS: " . var_export(getenv('FORCE_HTTPS'), true) . PHP_EOL;
echo "_ENV FORCE_HTTPS: " . var_export($_ENV['FORCE_HTTPS'] ?? 'NOT_SET', true) . PHP_EOL;
echo "_ENV APP_ENV: " . var_export($_ENV['APP_ENV'] ?? 'NOT_SET', true) . PHP_EOL;
echo "getenv APP_ENV: " . var_export(getenv('APP_ENV'), true) . PHP_EOL;
echo "force_https config via getenv: " . var_export((getenv('FORCE_HTTPS') ?: 'false') === 'true', true) . PHP_EOL;
