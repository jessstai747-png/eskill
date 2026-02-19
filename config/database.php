<?php

// Em produção, falhar explicitamente se credenciais DB não estiverem configuradas
$appEnv = $_ENV['APP_ENV'] ?? 'production';
$isProduction = $appEnv === 'production';

$dbPassword = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? null;
$dbUsername = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? null;

if ($isProduction && (empty($dbPassword) || $dbPassword === 'CHANGE_ME')) {
    throw new RuntimeException(
        'CRITICAL: DB_PASSWORD não configurado. Defina DB_PASSWORD ou DB_PASS no .env antes de rodar em produção.'
    );
}

if ($isProduction && empty($dbUsername)) {
    throw new RuntimeException(
        'CRITICAL: DB_USERNAME não configurado. Defina DB_USERNAME ou DB_USER no .env antes de rodar em produção.'
    );
}

return [
    'default' => 'mysql',
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'mercadolivre_db',
            'username' => $dbUsername ?? 'root',
            'password' => $dbPassword ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
];

