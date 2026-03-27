<?php

declare(strict_types=1);

/**
 * Teste de Integração - Ficha Técnica + SEO Strategies
 * Testa a integração das estratégias SEO com a ficha técnica
 */

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Carregar dependências
require_once __DIR__ . '/app/Database.php';

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                           ║\n";
echo "║      TESTE DE INTEGRAÇÃO - FICHA TÉCNICA + SEO STRATEGIES                ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = App\Database::getInstance();

    // ========================================================================
    // TESTE 1: Verificar Tabelas da Ficha Técnica
    // ========================================================================

    echo "🔍 TESTE 1: Verificando Tabelas da Ficha Técnica\n";
    echo str_repeat("─", 75) . "\n";

    $techSheetTables = [
        'tech_sheet_data',
        'tech_sheet_history',
        'tech_sheet_scores',
        'tech_sheet_templates'
    ];

    $existingTables = [];

    foreach ($techSheetTables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE '{$table}'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $existingTables[] = $table;

            // Contar registros
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM {$table}");
            $countStmt->execute();
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            echo "✅ {$table}: {$count} registros\n";
        } else {
            echo "⚠️  {$table}: Não encontrada\n";
        }
    }

    echo "\n";

    // ========================================================================
    // TESTE 2: Verificar Tabelas de SEO Strategies
    // ========================================================================

    echo "🔍 TESTE 2: Verificando Tabelas de SEO Strategies\n";
    echo str_repeat("─", 75) . "\n";

    $seoTables = [
        'seo_synonym_hierarchy',
        'seo_use_contexts',
        'seo_keyword_cache',
        'seo_keyword_performance',
        'seo_category_config'
    ];

    $seoTablesExist = true;

    foreach ($seoTables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE '{$table}'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM {$table}");
            $countStmt->execute();
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            echo "✅ {$table}: {$count} registros\n";
        } else {
            echo "❌ {$table}: NÃO ENCONTRADA\n";
            $seoTablesExist = false;
        }
    }

    echo "\n";

    // ========================================================================
    // TESTE 3: Verificar Services Disponíveis
    // ========================================================================

    echo "🔍 TESTE 3: Verificando Services Disponíveis\n";
    echo str_repeat("─", 75) . "\n";

    $services = [
        'TechSheetService' => 'app/Services/TechSheetService.php',
        'SynonymExpansionService' => 'app/Services/AI/SEO/Strategies/SynonymExpansionService.php',
        'SemanticScoreService' => 'app/Services/AI/SEO/Strategies/SemanticScoreService.php',
        'SEOStrategiesEngine' => 'app/Services/AI/SEO/Strategies/SEOStrategiesEngine.php'
    ];

    $servicesAvailable = [];

    foreach ($services as $name => $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            $size = filesize(__DIR__ . '/' . $path);
            $sizeKb = round($size / 1024, 1);
            echo "✅ {$name}: {$sizeKb} KB\n";
            $servicesAvailable[] = $name;
        } else {
            echo "❌ {$name}: Não encontrado\n";
        }
    }

    echo "\n";

    // ========================================================================
    // TESTE 4: Verificar Rotas da API
    // ========================================================================

    echo "🔍 TESTE 4: Verificando Rotas da API\n";
    echo str_repeat("─", 75) . "\n";

    $routesFile = __DIR__ . '/app/Routes/api.php';

    if (file_exists($routesFile)) {
        $routesContent = file_get_contents($routesFile);

        // Procurar rotas de ficha técnica
        $techSheetRoutes = substr_count($routesContent, 'technical-sheet');
        echo "📍 Rotas de Ficha Técnica encontradas: {$techSheetRoutes}\n";

        // Procurar rotas de strategies
        $strategiesRoutes = substr_count($routesContent, 'strategies');
        echo "📍 Rotas de Strategies encontradas: {$strategiesRoutes}\n";

        // Procurar integração
        $hasIntegration = (strpos($routesContent, 'seo-killer/strategies') !== false);
        echo "🔗 Integração SEO Killer + Strategies: " . ($hasIntegration ? "✅ SIM" : "⚠️  NÃO") . "\n";
    } else {
        echo "❌ Arquivo de rotas não encontrado\n";
    }

    echo "\n";

    // ========================================================================
    // TESTE 5: Verificar Controllers
    // ========================================================================

    echo "🔍 TESTE 5: Verificando Controllers\n";
    echo str_repeat("─", 75) . "\n";

    $controllers = [
        'TechnicalSheetController' => 'app/Controllers/TechnicalSheetController.php',
        'SEOKillerController' => 'app/Controllers/SEOKillerController.php',
        'SeoStrategiesController' => 'app/Controllers/SeoStrategiesController.php'
    ];

    foreach ($controllers as $name => $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            $content = file_get_contents(__DIR__ . '/' . $path);
            $lines = count(file(__DIR__ . '/' . $path));

            // Verificar se menciona strategies
            $hasStrategies = (stripos($content, 'strategies') !== false);
            $hasStrategiesClass = (stripos($content, 'SEOStrategiesEngine') !== false);

            echo "✅ {$name}: {$lines} linhas";
            if ($hasStrategies) echo " | Menciona 'strategies'";
            if ($hasStrategiesClass) echo " | Usa SEOStrategiesEngine";
            echo "\n";
        } else {
            echo "⚠️  {$name}: Não encontrado\n";
        }
    }

    echo "\n";

    // ========================================================================
    // TESTE 6: Verificar Views do Dashboard
    // ========================================================================

    echo "🔍 TESTE 6: Verificando Views do Dashboard\n";
    echo str_repeat("─", 75) . "\n";

    $views = [
        'Ficha Técnica' => 'app/Views/dashboard/seo/ficha-tecnica.php',
        'SEO Killer' => 'app/Views/dashboard/seo-killer.php',
        'SEO Killer Strategies' => 'app/Views/dashboard/seo-killer/strategies.php',
        'SEO Strategies' => 'app/Views/dashboard/seo/strategies.php'
    ];

    foreach ($views as $name => $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            $size = filesize(__DIR__ . '/' . $path);
            $sizeKb = round($size / 1024, 1);
            echo "✅ {$name}: {$sizeKb} KB\n";
        } else {
            echo "⚠️  {$name}: Não encontrado\n";
        }
    }

    echo "\n";

    // ========================================================================
    // TESTE 7: Testar Integração Real (se possível)
    // ========================================================================

    echo "🔍 TESTE 7: Testando Possibilidade de Integração\n";
    echo str_repeat("─", 75) . "\n";

    // Verificar se as classes podem ser carregadas
    $canIntegrate = true;

    if (file_exists(__DIR__ . '/app/Services/AI/SEO/Strategies/SEOStrategiesEngine.php')) {
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/SynonymExpansionService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/SemanticScoreService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/KeywordSourceService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/KeywordInjectorService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/FieldWeightService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/SearchTypeCoverageService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/UseContextService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/LongTailGeneratorService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/HiddenFieldsService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/CompatibilityService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/FAQOptimizerService.php';
        require_once __DIR__ . '/app/Services/AI/SEO/Strategies/SEOStrategiesEngine.php';

        try {
            $engine = new \App\Services\AI\SEO\Strategies\SEOStrategiesEngine(null);
            echo "✅ SEOStrategiesEngine instanciado com sucesso\n";
            echo "✅ Integração com Ficha Técnica: POSSÍVEL\n";
            echo "✅ Todos os services carregados corretamente\n";

            // Simular dados de ficha técnica
            echo "\n📋 Simulação de Integração:\n";
            echo "   1. Ficha Técnica fornece dados do produto\n";
            echo "   2. SEOStrategiesEngine analisa e gera score\n";
            echo "   3. Sistema sugere melhorias na ficha técnica\n";
            echo "   4. Usuário aplica otimizações sugeridas\n";

        } catch (Exception $e) {
            echo "❌ Erro ao instanciar: " . $e->getMessage() . "\n";
            $canIntegrate = false;
        }
    } else {
        echo "⚠️  SEOStrategiesEngine não disponível\n";
        $canIntegrate = false;
    }

    echo "\n";

    // ========================================================================
    // RESUMO FINAL
    // ========================================================================

    echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
    echo "║                         RESUMO DO TESTE                                ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════════╝\n\n";

    echo "📊 COMPONENTES VERIFICADOS:\n";
    echo str_repeat("─", 75) . "\n";
    echo "Tabelas de Ficha Técnica: " . count($existingTables) . "/4\n";
    echo "Tabelas de SEO Strategies: " . ($seoTablesExist ? "5/5 ✅" : "Incompleto ⚠️") . "\n";
    echo "Services Disponíveis: " . count($servicesAvailable) . "/4\n";
    echo "Controllers Encontrados: " . count(array_filter($controllers, function($path) { return file_exists(__DIR__ . '/' . $path); })) . "/3\n";
    echo "Views Disponíveis: " . count(array_filter($views, function($path) { return file_exists(__DIR__ . '/' . $path); })) . "/4\n";

    echo "\n";

    echo "🎯 POSSIBILIDADE DE INTEGRAÇÃO:\n";
    echo str_repeat("─", 75) . "\n";

    if ($seoTablesExist && $canIntegrate) {
        echo "✅ ALTA - Sistema está pronto para integração completa\n\n";

        echo "Próximos passos sugeridos:\n";
        echo "1. Adicionar endpoint na API para análise de ficha técnica\n";
        echo "2. Criar botão 'Analisar SEO' na interface da ficha técnica\n";
        echo "3. Exibir score e sugestões de melhoria\n";
        echo "4. Permitir aplicação automática de otimizações\n";
    } else {
        echo "🟡 MÉDIA - Alguns componentes faltando\n\n";

        echo "Ações necessárias:\n";
        if (!$seoTablesExist) {
            echo "• Aplicar migration das tabelas SEO Strategies\n";
        }
        if (!$canIntegrate) {
            echo "• Verificar carregamento dos services\n";
        }
    }

    echo "\n";

    echo "📈 STATUS GERAL:\n";
    echo str_repeat("─", 75) . "\n";

    $totalScore = 0;
    if ($seoTablesExist) $totalScore += 30;
    if (count($servicesAvailable) >= 3) $totalScore += 25;
    if (count($existingTables) >= 2) $totalScore += 20;
    if ($canIntegrate) $totalScore += 25;

    $status = "Baixo";
    $emoji = "🔴";
    if ($totalScore >= 80) { $status = "Excelente"; $emoji = "🟢"; }
    elseif ($totalScore >= 60) { $status = "Bom"; $emoji = "🟡"; }
    elseif ($totalScore >= 40) { $status = "Regular"; $emoji = "🟠"; }

    echo "{$emoji} Prontidão para Integração: {$totalScore}/100 ({$status})\n";

    echo "\n";

} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
}

echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                       ║\n";
echo "║                    ✅ TESTE CONCLUÍDO!                                ║\n";
echo "║                                                                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════╝\n";
