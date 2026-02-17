#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ML Integration Test CLI
 * 
 * Testa a integração completa com a API do Mercado Livre e exibe diagnóstico detalhado.
 * 
 * Usage:
 *   php bin/test-ml-integration.php [--account=ID] [--token=ABC...] [--verbose]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MercadoLivre\MercadoLivreAIIntegrationService;

// Parse CLI args
$options = getopt('', ['account:', 'token:', 'verbose', 'help']);

if (isset($options['help'])) {
    echo <<<'HELP'

ML Integration Test — Diagnóstico completo da integração API do Mercado Livre

Usage:
  php bin/test-ml-integration.php [OPTIONS]

Options:
  --account=ID       ID da conta em ml_accounts (preferencial)
  --token=ABC...     Access token direto (alternativo, modo single-token)
  --verbose          Exibir JSON completo do diagnóstico
  --help             Exibir esta ajuda

Exemplos:
  # Testar conta ID 1 (multi-account mode)
  php bin/test-ml-integration.php --account=1

  # Testar com token via ambiente (ML_ACCESS_TOKEN)
  php bin/test-ml-integration.php

  # Testar com token direto via CLI
  php bin/test-ml-integration.php --token=APP_USR-123456...

  # Modo verbose (JSON completo)
  php bin/test-ml-integration.php --account=1 --verbose

HELP;
    exit(0);
}

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Determine account/token mode
$accountId = isset($options['account']) ? (int)$options['account'] : null;
$cliToken = $options['token'] ?? null;
$verbose = isset($options['verbose']);

if ($cliToken !== null) {
    // Override ML_ACCESS_TOKEN for this run
    putenv('ML_ACCESS_TOKEN=' . $cliToken);
    $_ENV['ML_ACCESS_TOKEN'] = $cliToken;
    echo "\n[INFO] Usando token fornecido via --token\n";
}

if ($accountId === null && $cliToken === null && empty($_ENV['ML_ACCESS_TOKEN'])) {
    echo "\n[ERRO] Nenhuma conta/token configurado.\n";
    echo "Opções:\n";
    echo "  1. Defina ML_ACCESS_TOKEN no .env\n";
    echo "  2. Passe --account=ID (conta em ml_accounts)\n";
    echo "  3. Passe --token=ABC... (token direto)\n\n";
    exit(1);
}

// Instantiate service
try {
    $service = new MercadoLivreAIIntegrationService($accountId ?? 0);
} catch (\Throwable $e) {
    echo "\n[ERRO] Falha ao inicializar serviço: {$e->getMessage()}\n\n";
    exit(1);
}

echo "\n";
echo "══════════════════════════════════════════════════════════════════\n";
echo "  ML-AI Integration — Health Check & Diagnostics\n";
echo "══════════════════════════════════════════════════════════════════\n";
echo "\n";

// Get health status
try {
    $health = $service->getHealthStatus();
} catch (\Throwable $e) {
    echo "[ERRO] Falha ao obter health status: {$e->getMessage()}\n\n";
    exit(1);
}

// Display results
$ml = $health['ml'] ?? [];
$ai = $health['ai'] ?? [];
$integrated = $health['integrated'] ?? false;
$recommendations = $health['recommendations'] ?? [];

echo "📊 MERCADO LIVRE API\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("  Conectado:        %s\n", $ml['connected'] ? '✅ Sim' : '❌ Não');
echo sprintf("  Token Válido:     %s\n", $ml['token_valid'] ?? false ? '✅ Sim' : '❌ Não');
echo sprintf("  API Pública:      %s\n", $ml['public_api'] ?? false ? '✅ Acessível' : '⚠️  Indisponível');
echo sprintf("  Auth OK:          %s\n", $ml['auth_ok'] ?? false ? '✅ Sim' : '❌ Não');
echo sprintf("  Seller ID:        %s\n", $ml['seller_id'] ?? '(nenhum)');
echo sprintf("  Token Source:     %s\n", $ml['token_source'] ?? 'unknown');
echo sprintf("  DB Disponível:    %s\n", $ml['db_unavailable'] ?? false ? '❌ Indisponível' : '✅ OK');
echo sprintf("  Account ID:       %s\n", $ml['account_id'] ?? 'N/A');
echo sprintf("  Mode:             %s\n", $ml['mode'] ?? 'unknown');
echo sprintf("  Items Count:      %d\n", $ml['items_count'] ?? 0);

if (!empty($ml['checks'])) {
    echo "\n  Checks Detalhados:\n";
    foreach ($ml['checks'] as $check => $status) {
        $icon = str_starts_with((string)$status, 'ok') ? '✅' : (str_starts_with((string)$status, 'skipped') ? '⏭️ ' : '❌');
        echo sprintf("    %s %-15s: %s\n", $icon, $check, $status);
    }
}

echo "\n";
echo "🤖 AI PROVIDERS\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("  Disponíveis:      %d / %d\n", $ai['available_count'] ?? 0, $ai['total_providers'] ?? 0);
echo sprintf("  Provider Ativo:   %s\n", $ai['preferred_provider'] ?? 'nenhum');
echo sprintf("  Fallback:         %s\n", $ai['fallback_enabled'] ?? false ? '✅ Habilitado' : '❌ Desabilitado');

if (!empty($ai['providers'])) {
    echo "\n  Providers Configurados:\n";
    foreach ($ai['providers'] as $name => $info) {
        $icon = $info['available'] ?? false ? '✅' : '❌';
        $model = $info['model'] ?? 'unknown';
        echo sprintf("    %s %-15s (model: %s)\n", $icon, $name, $model);
    }
}

echo "\n";
echo "🔗 INTEGRAÇÃO\n";
echo str_repeat("─", 70) . "\n";
echo sprintf("  Status:           %s\n", $integrated ? '✅ Integrado (ML + AI)' : '⚠️  Parcial');

if (!empty($recommendations)) {
    echo "\n  ⚠️  Recomendações:\n";
    foreach ($recommendations as $rec) {
        echo "    • " . $rec . "\n";
    }
}

if ($verbose) {
    echo "\n";
    echo "🔍 JSON COMPLETO (--verbose)\n";
    echo str_repeat("─", 70) . "\n";
    echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n";
echo "══════════════════════════════════════════════════════════════════\n";

if ($integrated) {
    echo "  ✅ Integração ML ↔ AI funcionando!\n";
    exit(0);
} else {
    echo "  ⚠️  Integração parcial — revise recomendações acima\n";
    exit(1);
}
