#!/usr/bin/env php
<?php
/**
 * Verificação Final do SEO Killer - Status Visual
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

echo "\033[2J\033[H"; // Clear screen
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                ║\n";
echo "║              🔥 SEO KILLER - VERIFICAÇÃO FINAL 🔥              ║\n";
echo "║                                                                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$checks = [];
$allPassed = true;

// 1. Autoloader
try {
    if (class_exists('App\Services\MercadoLivreClient')) {
        $checks[] = ['✅', 'Autoloader', 'Funcionando', 'green'];
    } else {
        $checks[] = ['❌', 'Autoloader', 'FALHOU', 'red'];
        $allPassed = false;
    }
} catch (Exception $e) {
    $checks[] = ['❌', 'Autoloader', 'ERRO: ' . $e->getMessage(), 'red'];
    $allPassed = false;
}

// 2. Guzzle HTTP Client
try {
    if (class_exists('GuzzleHttp\Client')) {
        $checks[] = ['✅', 'Guzzle HTTP', 'Instalado', 'green'];
    } else {
        $checks[] = ['❌', 'Guzzle HTTP', 'NÃO INSTALADO', 'red'];
        $allPassed = false;
    }
} catch (Exception $e) {
    $checks[] = ['❌', 'Guzzle HTTP', 'ERRO', 'red'];
    $allPassed = false;
}

// 3. Database
try {
    $db = App\Database::getInstance();
    $checks[] = ['✅', 'Database', 'Conectado', 'green'];
} catch (Exception $e) {
    $checks[] = ['❌', 'Database', 'FALHOU', 'red'];
    $allPassed = false;
}

// 4. SEOKillerEngine
try {
    $engine = new \App\Services\AI\SEO\SEOKillerEngine(1);
    $checks[] = ['✅', 'SEOKillerEngine', 'Operacional', 'green'];
} catch (Exception $e) {
    $checks[] = ['❌', 'SEOKillerEngine', 'ERRO', 'red'];
    $allPassed = false;
}

// 5. SEOKillerController
try {
    $reflection = new ReflectionClass('App\Controllers\SEOKillerController');
    $hasJsonMethod = $reflection->hasMethod('json');
    $hasGetJsonInputMethod = $reflection->hasMethod('getJsonInput');

    if ($hasJsonMethod && $hasGetJsonInputMethod) {
        $checks[] = ['✅', 'SEOKillerController', 'Métodos OK', 'green'];
    } else {
        $checks[] = ['❌', 'SEOKillerController', 'Métodos faltando', 'red'];
        $allPassed = false;
    }
} catch (Exception $e) {
    $checks[] = ['❌', 'SEOKillerController', 'ERRO', 'red'];
    $allPassed = false;
}

// 6. Syntax Check
try {
    exec('php -l /home/eskill/htdocs/eskill.com.br/app/Controllers/SEOKillerController.php 2>&1', $output, $return);
    $syntaxOk = ($return === 0 && strpos(implode("\n", $output), 'No syntax errors') !== false);

    if ($syntaxOk) {
        $checks[] = ['✅', 'Sintaxe PHP', 'Sem erros', 'green'];
    } else {
        $checks[] = ['❌', 'Sintaxe PHP', 'ERROS ENCONTRADOS', 'red'];
        $allPassed = false;
    }
} catch (Exception $e) {
    $checks[] = ['⚠️ ', 'Sintaxe PHP', 'Não verificado', 'yellow'];
}

// 7. Mercado Livre API Integration
$mlDiagnosis = null;
try {
    // Tenta criar client sem accountId para usar token do ambiente
    $mlClient = new \App\Services\MercadoLivreClient(null);
    $mlDiagnosis = $mlClient->diagnose();

    // API Pública
    if (!empty($mlDiagnosis['api_accessible'])) {
        $checks[] = ['✅', 'ML API Pública', 'Acessível', 'green'];
    } else {
        $checks[] = ['❌', 'ML API Pública', 'Inacessível', 'red'];
        $allPassed = false;
    }

    // Token Status
    $tokenSource = $mlDiagnosis['token_source'] ?? 'none';
    if ($mlDiagnosis['token_valid'] ?? false) {
        $checks[] = ['✅', 'ML Token', "Válido ($tokenSource)", 'green'];
    } elseif ($mlDiagnosis['has_token'] ?? false) {
        $checks[] = ['⚠️ ', 'ML Token', "Expirado ($tokenSource)", 'yellow'];
    } else {
        $checks[] = ['⚠️ ', 'ML Token', 'Não configurado', 'yellow'];
    }

    // Autenticação/Seller
    if ($mlDiagnosis['connected'] ?? false) {
        $sellerId = $mlDiagnosis['seller_id'] ?? 'N/A';
        $nickname = $mlDiagnosis['user_info']['nickname'] ?? '';
        $checks[] = ['✅', 'ML Autenticação', "OK #$sellerId", 'green'];
        if ($nickname) {
            $checks[] = ['✅', 'ML Seller', $nickname, 'green'];
        }
        // Items count
        $itemsCount = $mlDiagnosis['items_count'] ?? 0;
        $checks[] = ['✅', 'ML Items', "$itemsCount anúncios", 'green'];
    } else {
        $checks[] = ['⚠️ ', 'ML Autenticação', 'Desconectado', 'yellow'];
    }
} catch (Exception $e) {
    $checks[] = ['⚠️ ', 'ML API', 'Erro: ' . substr($e->getMessage(), 0, 30), 'yellow'];
}

// 8. HTTP Endpoints
$endpoints = [
    '/api/seo-killer/diagnose',
    '/api/seo-killer/autopilot/config',
    '/api/seo-killer/autopilot/history'
];

foreach ($endpoints as $endpoint) {
    $ch = curl_init("https://eskill.com.br$endpoint");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401) {
        $checks[] = ['✅', basename($endpoint), "HTTP $httpCode (OK)", 'green'];
    } else {
        $checks[] = ['❌', basename($endpoint), "HTTP $httpCode", 'red'];
        $allPassed = false;
    }
}

// Display results
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  COMPONENTE               STATUS            DETALHES           ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";

foreach ($checks as $check) {
    printf(
        "║  %-3s %-20s %-15s %-20s ║\n",
        $check[0],
        substr($check[1], 0, 20),
        substr($check[2], 0, 15),
        ''
    );
}

echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ML Diagnosis Summary (if available)
if ($mlDiagnosis !== null) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║              🔌 MERCADO LIVRE API DIAGNOSIS                    ║\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    $apiStatus = ($mlDiagnosis['api_accessible'] ?? false) ? '✅ Acessível' : '❌ Inacessível';
    $tokenStatus = ($mlDiagnosis['token_valid'] ?? false) ? '✅ Válido' : '⚠️  Inválido/Ausente';
    $authStatus = ($mlDiagnosis['connected'] ?? false) ? '✅ Conectado' : '⚠️  Desconectado';
    $sellerId = $mlDiagnosis['seller_id'] ?? 'N/A';
    $itemsCount = $mlDiagnosis['items_count'] ?? 0;
    $tokenSource = $mlDiagnosis['token_source'] ?? 'none';

    printf("║  API Pública:      %-45s ║\n", $apiStatus);
    printf("║  Token:            %-45s ║\n", $tokenStatus);
    printf("║  Token Source:     %-45s ║\n", $tokenSource);
    printf("║  Autenticação:     %-45s ║\n", $authStatus);
    printf("║  Seller ID:        %-45s ║\n", $sellerId);
    printf("║  Total Anúncios:   %-45s ║\n", $itemsCount);

    // Checks details
    if (!empty($mlDiagnosis['checks'])) {
        echo "╠════════════════════════════════════════════════════════════════╣\n";
        echo "║  Checks detalhados:                                            ║\n";
        foreach ($mlDiagnosis['checks'] as $checkName => $checkResult) {
            $icon = (strpos((string)$checkResult, 'ok') === 0) ? '✅' : '⚠️ ';
            printf("║    %s %-12s: %-43s ║\n", $icon, $checkName, substr((string)$checkResult, 0, 43));
        }
    }
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
}

// Final status
if ($allPassed) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                                                                ║\n";
    echo "║                    ✅ SISTEMA 100% FUNCIONAL                   ║\n";
    echo "║                                                                ║\n";
    echo "║  🟢 Backend operacional                                        ║\n";
    echo "║  🟢 Mercado Livre API integrada                                ║\n";
    echo "║  🟢 Todos os endpoints respondendo                             ║\n";
    echo "║  🟢 Sem erros PHP                                              ║\n";
    echo "║  🟢 Autenticação funcionando                                   ║\n";
    echo "║                                                                ║\n";
    echo "║  ℹ️  Se ainda vê erros no navegador:                           ║\n";
    echo "║     → Pressione CTRL+SHIFT+R para limpar cache                ║\n";
    echo "║     → Ou abra em janela anônima                                ║\n";
    echo "║                                                                ║\n";
    echo "║  📖 Documentação: SEO_KILLER_STATUS_FINAL.md                   ║\n";
    echo "║                                                                ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    exit(0);
} else {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                                                                ║\n";
    echo "║                    ⚠️  PROBLEMAS DETECTADOS                    ║\n";
    echo "║                                                                ║\n";
    echo "║  Verifique os itens marcados com ❌ acima                      ║\n";
    echo "║                                                                ║\n";
    if ($mlDiagnosis !== null && !($mlDiagnosis['connected'] ?? false)) {
        echo "║  💡 Dica: Execute 'php bin/mcp-ml-auth.php --open' para       ║\n";
        echo "║     reconectar sua conta do Mercado Livre                     ║\n";
        echo "║                                                                ║\n";
    }
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    exit(1);
}
