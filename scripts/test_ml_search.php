<?php

/**
 * Script para testar a API de pesquisa do Mercado Livre
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

use App\Services\MercadoLivreClient;

echo "=== Teste de Pesquisa do Mercado Livre ===\n\n";

try {
    // Teste 1: Com conta autenticada
    echo "=== Teste 1: Com conta autenticada (account_id = 1) ===\n";
    $client1 = new MercadoLivreClient(1);
    echo "Account ID no cliente: " . var_export($client1->getAccountId(), true) . "\n\n";

    echo "Fazendo requisição de busca...\n";
    $response1 = $client1->get('/sites/MLB/search', [
        'category' => 'MLB1071',
        'BRAND' => 'AWA',
        'limit' => 5
    ]);

    if (isset($response1['error']) && $response1['error']) {
        echo "❌ ERRO: " . ($response1['message'] ?? 'Desconhecido') . " (status: {$response1['status_code']})\n";
    } else {
        echo "✅ Sucesso! Total: " . ($response1['paging']['total'] ?? 0) . "\n";
    }

    // Teste 2: Sem autenticação (App Token ou público)
    echo "\n=== Teste 2: Sem conta (App Token/Público) ===\n";
    $client2 = new MercadoLivreClient(null);
    echo "Account ID no cliente: " . var_export($client2->getAccountId(), true) . "\n\n";

    echo "Fazendo requisição de busca...\n";
    $response2 = $client2->get('/sites/MLB/search', [
        'category' => 'MLB1071',
        'BRAND' => 'AWA',
        'limit' => 5
    ]);

    if (isset($response2['error']) && $response2['error']) {
        echo "❌ ERRO: " . ($response2['message'] ?? 'Desconhecido') . " (status: {$response2['status_code']})\n";
    } else {
        echo "✅ Sucesso! Total: " . ($response2['paging']['total'] ?? 0) . "\n";
        if (!empty($response2['results'])) {
            $first = $response2['results'][0];
            echo "Primeiro: {$first['title']} - R$ " . number_format($first['price'], 2, ',', '.') . "\n";
        }
    }

    // Teste 3: Via cURL direto sem token
    echo "\n=== Teste 3: cURL direto (sem autenticação) ===\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.mercadolibre.com/sites/MLB/search?category=MLB1071&BRAND=AWA&limit=5',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: MercadoLivreManager/1.0'
        ]
    ]);
    $curlResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($curlResponse, true);
        echo "✅ Sucesso! Total: " . ($data['paging']['total'] ?? 0) . "\n";
    } else {
        echo "❌ ERRO HTTP: {$httpCode}\n";
        echo "Resposta: " . substr($curlResponse, 0, 200) . "\n";
    }
} catch (\Exception $e) {
    echo "\n❌ Exceção: " . $e->getMessage() . "\n";
}

echo "\n=== Teste Concluído ===\n";
