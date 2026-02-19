#!/usr/bin/env php
<?php
/**
 * Script de Diagnóstico Completo do Sistema de Proxy
 * 
 * Verifica:
 * 1. Configuração do .env
 * 2. Tabelas do banco de dados
 * 3. Conectividade com a API do ML
 * 4. Sistema de fallback
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\ProxyService;
use App\Services\AlternativeSearchService;

// Cores para output
function colorize(string $text, string $color): string
{
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'reset' => "\033[0m",
        'bold' => "\033[1m"
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function printHeader(string $title): void
{
    echo "\n" . colorize("═══════════════════════════════════════════════════════", 'cyan') . "\n";
    echo colorize(" $title", 'bold') . "\n";
    echo colorize("═══════════════════════════════════════════════════════", 'cyan') . "\n\n";
}

function printCheck(string $item, bool $success, string $message = ''): void
{
    $icon = $success ? colorize('✓', 'green') : colorize('✗', 'red');
    $status = $success ? colorize('OK', 'green') : colorize('FALHOU', 'red');
    echo "  $icon $item: $status";
    if ($message) {
        echo " - " . colorize($message, 'yellow');
    }
    echo "\n";
}

// Banner
echo colorize("\n", 'reset');
echo colorize(" ╔══════════════════════════════════════════════════════════╗\n", 'cyan');
echo colorize(" ║", 'cyan') . colorize("   🔧 DIAGNÓSTICO DO SISTEMA DE PROXY - ML MANAGER      ", 'bold') . colorize("║\n", 'cyan');
echo colorize(" ╚══════════════════════════════════════════════════════════╝\n", 'cyan');

// 1. Verificar .env
printHeader("1. CONFIGURAÇÃO DO AMBIENTE");

$envFile = __DIR__ . '/../.env';
$envExists = file_exists($envFile);
printCheck('Arquivo .env', $envExists);

if ($envExists) {
    $envContent = file_get_contents($envFile);

    $proxyEnabled = preg_match('/ML_PROXY_ENABLED\s*=\s*(\w+)/', $envContent, $matches);
    $proxyStatus = $matches[1] ?? 'não definido';
    printCheck('ML_PROXY_ENABLED', $proxyEnabled, $proxyStatus);

    $hasProxyHost = preg_match('/ML_PROXY_HOST\s*=\s*(.+)/', $envContent, $matches);
    $proxyHost = trim($matches[1] ?? '');
    printCheck('ML_PROXY_HOST', !empty($proxyHost), $proxyHost ?: 'não configurado');

    $hasDbConfig = preg_match('/DB_HOST\s*=/', $envContent);
    printCheck('Configuração do banco', $hasDbConfig);
}

// 2. Verificar banco de dados
printHeader("2. BANCO DE DADOS");

try {
    $db = Database::getInstance();
    printCheck('Conexão com banco', true);

    // Verificar tabelas
    $tables = ['ml_proxies', 'ml_proxy_logs', 'ml_research_cache'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        printCheck("Tabela $table", $exists, $exists ? 'existe' : 'criar via migration');
    }

    // Contar proxies
    $stmt = $db->query("SELECT COUNT(*) as total FROM ml_proxies WHERE status = 'active'");
    $row = $stmt->fetch();
    $proxyCount = $row['total'] ?? 0;
    echo "\n  " . colorize("→ Proxies ativos no banco: $proxyCount", 'blue') . "\n";
} catch (Exception $e) {
    printCheck('Conexão com banco', false, $e->getMessage());
}

// 3. Testar API do ML diretamente
printHeader("3. CONECTIVIDADE COM A API");

echo "  Testando endpoint de busca (sem proxy)...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.mercadolibre.com/sites/MLB/search?q=celular&limit=1',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$searchWorks = $httpCode === 200;
printCheck('Busca direta', $searchWorks, "HTTP $httpCode");

if (!$searchWorks) {
    $decoded = json_decode($response, true);
    $blockReason = $decoded['blocked_by'] ?? 'desconhecido';
    echo "  " . colorize("→ Bloqueio detectado: $blockReason", 'yellow') . "\n";
    echo "  " . colorize("→ Isso é normal! Por isso usamos o sistema de proxy/fallback.", 'blue') . "\n";
}

// Testar endpoint autenticado
echo "\n  Testando endpoint público (sem auth)...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.mercadolibre.com/sites/MLB',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

printCheck('Sites endpoint', $httpCode === 200, "HTTP $httpCode");

// 4. Testar ProxyService
printHeader("4. SERVIÇOS DE PROXY");

try {
    $proxyService = new ProxyService();
    printCheck('ProxyService instanciado', true);

    try {
        $stats = $proxyService->getStats();
        echo "  " . colorize("→ Estatísticas:", 'blue') . "\n";
        echo "    - Total de proxies: {$stats['total_proxies']}\n";
        echo "    - Disponíveis: {$stats['available_proxies']}\n";
        echo "    - Blacklistados: {$stats['blacklisted']}\n";
        echo "    - Taxa média de sucesso: {$stats['average_success_rate']}%\n";
    } catch (Exception $e) {
        echo "  " . colorize("→ Não foi possível obter estatísticas (banco offline?)", 'yellow') . "\n";
    }
} catch (Exception $e) {
    printCheck('ProxyService', false, $e->getMessage());
}

// 5. Testar AlternativeSearchService
printHeader("5. SERVIÇO DE BUSCA ALTERNATIVA");

try {
    $altService = new AlternativeSearchService(1); // Account ID 1 para teste
    printCheck('AlternativeSearchService instanciado', true);

    echo "\n  Testando busca via vendedores (método alternativo)...\n";

    // Verificar se há cache (só se o banco estiver disponível)
    try {
        $dbCheck = Database::getInstance();
        $stmt = $dbCheck->query("SELECT COUNT(*) as total FROM ml_research_cache");
        $cacheCount = $stmt->fetch()['total'] ?? 0;
        echo "  " . colorize("→ Entradas em cache: $cacheCount", 'blue') . "\n";
    } catch (Exception $e) {
        echo "  " . colorize("→ Cache não verificado (banco offline)", 'yellow') . "\n";
    }
} catch (Exception $e) {
    printCheck('AlternativeSearchService', false, $e->getMessage());
}

// 6. Recomendações
printHeader("6. RECOMENDAÇÕES");

$recommendations = [];

if (!$searchWorks) {
    $recommendations[] = "Configure um proxy residencial para contornar o bloqueio";
    $recommendations[] = "Acesse /settings/proxies para gerenciar proxies";
}

if ($proxyCount == 0) {
    $recommendations[] = "Adicione pelo menos um proxy no sistema";
    $recommendations[] = "Use serviços como BrightData, Oxylabs ou SmartProxy";
}

if (empty($recommendations)) {
    echo "  " . colorize("✓ Sistema configurado corretamente!", 'green') . "\n";
} else {
    foreach ($recommendations as $i => $rec) {
        echo "  " . colorize(($i + 1) . ". $rec", 'yellow') . "\n";
    }
}

// Resumo final
echo "\n" . colorize("═══════════════════════════════════════════════════════", 'cyan') . "\n";
echo colorize(" RESUMO", 'bold') . "\n";
echo colorize("═══════════════════════════════════════════════════════", 'cyan') . "\n";

if ($searchWorks) {
    echo "\n  " . colorize("✓ A busca direta funciona! Nenhuma configuração adicional necessária.", 'green') . "\n";
} else {
    echo "\n  " . colorize("! A busca direta está bloqueada pelo Mercado Livre.", 'yellow') . "\n";
    echo "  " . colorize("→ Sistema de fallback configurado para usar:", 'blue') . "\n";
    echo "    1. Proxy pool (se configurado)\n";
    echo "    2. Busca via vendedores\n";
    echo "    3. Cache histórico\n";
    echo "\n  " . colorize("→ Acesse: /settings/proxies para configurar proxies", 'cyan') . "\n";
}

echo "\n";
