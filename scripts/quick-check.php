#!/usr/bin/env php
<?php
/**
 * Quick Check - Validação rápida de hardenings
 * Executa verificações essenciais sem dependências externas
 */

$checks = [
    'ExceptionHandler' => [
        'file' => 'app/Core/ExceptionHandler.php',
        'patterns' => [
            'wantsJson' => 'private static function wantsJson(): bool',
            'api_detection' => "strpos(\$path, '/api/') === 0",
            'headers_check' => 'headers_sent()',
        ]
    ],
    'SecurityMiddleware' => [
        'file' => 'app/Middleware/SecurityMiddleware.php',
        'patterns' => [
            'rate_flag' => 'SECURITY_MW_RATE_LIMIT_ENABLED',
            'rate_disabled' => "'rate_limit_enabled' => (\$securityMwRateLimitEnabled ?: 'false') === 'true'",
        ]
    ],
    'SecurityHeadersMiddleware' => [
        'file' => 'app/Middleware/SecurityHeadersMiddleware.php',
        'patterns' => [
            'legacy_flag' => 'SECURITY_HEADERS_LEGACY_ENABLED',
            'disabled_default' => "!== 'true'",
        ]
    ],
    'CloneAdvancedController' => [
        'file' => 'app/Controllers/CloneAdvancedController.php',
        'patterns' => [
            'correct_method' => '$seo->updateSettings($data)',
        ]
    ],
    'SettingsController' => [
        'file' => 'app/Controllers/SettingsController.php',
        'patterns' => [
            'correct_helper' => 'SessionHelper::getUserAccounts()',
        ]
    ],
];

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

echo "\n🔍 Quick Check - Validação de Hardenings\n";
echo "==========================================\n\n";

foreach ($checks as $component => $config) {
    $filePath = $root . '/' . $config['file'];
    
    if (!file_exists($filePath)) {
        echo "❌ $component: Arquivo não encontrado\n";
        $failed++;
        continue;
    }
    
    $content = file_get_contents($filePath);
    $componentPassed = true;
    
    foreach ($config['patterns'] as $name => $pattern) {
        if (strpos($content, $pattern) === false) {
            echo "❌ $component: Pattern '$name' não encontrado\n";
            $componentPassed = false;
            $failed++;
        }
    }
    
    if ($componentPassed) {
        echo "✅ $component: OK\n";
        $passed++;
    }
}

echo "\n==========================================\n";
echo "Resultado: $passed OK, $failed Failed\n";

if ($failed === 0) {
    echo "🎉 Todos os hardenings validados!\n\n";
    echo "Próximos passos:\n";
    echo "1. bash install-sandbox-deps.sh (no host)\n";
    echo "2. composer test-unit\n";
    echo "3. Deploy! 🚀\n\n";
    exit(0);
} else {
    echo "⚠️  Alguns checks falharam. Revise os arquivos.\n\n";
    exit(1);
}
