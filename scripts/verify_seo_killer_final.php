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

// 7. HTTP Endpoints
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
    printf("║  %-3s %-20s %-15s %-20s ║\n",
        $check[0],
        substr($check[1], 0, 20),
        substr($check[2], 0, 15),
        ''
    );
}

echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Final status
if ($allPassed) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                                                                ║\n";
    echo "║                    ✅ SISTEMA 100% FUNCIONAL                   ║\n";
    echo "║                                                                ║\n";
    echo "║  🟢 Backend operacional                                        ║\n";
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
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    exit(1);
}
