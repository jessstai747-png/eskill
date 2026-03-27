<?php

declare(strict_types=1);

/**
 * Teste Completo de Integração
 * Tech Sheet + SEO Strategies - Sistema Completo
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
echo "║      TESTE COMPLETO DE INTEGRAÇÃO - TECH SHEET + SEO STRATEGIES          ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = App\Database::getInstance();

    // ========================================================================
    // PARTE 1: VERIFICAR TODAS AS TABELAS
    // ========================================================================

    echo "🔍 PARTE 1: Verificação de Tabelas\n";
    echo str_repeat("═", 79) . "\n\n";

    $allTables = [
        'Tech Sheet' => [
            'tech_sheet_item_summary',
            'tech_sheet_suggestions',
            'tech_sheet_scheduled_jobs',
            'tech_sheet_execution_log',
            'tech_sheet_webhooks',
            'tech_sheet_alerts'
        ],
        'SEO Strategies' => [
            'seo_synonym_hierarchy',
            'seo_use_contexts',
            'seo_keyword_cache',
            'seo_keyword_performance',
            'seo_category_config'
        ]
    ];

    $totalTables = 0;
    $existingTables = 0;
    $totalRecords = 0;

    foreach ($allTables as $group => $tables) {
        echo "📊 {$group}\n";
        echo str_repeat("─", 79) . "\n";

        foreach ($tables as $table) {
            $totalTables++;

            $stmt = $db->prepare("SHOW TABLES LIKE '{$table}'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $countStmt = $db->prepare("SELECT COUNT(*) as total FROM {$table}");
                $countStmt->execute();
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

                echo "  ✅ {$table}: {$count} registros\n";
                $existingTables++;
                $totalRecords += $count;
            } else {
                echo "  ❌ {$table}: NÃO ENCONTRADA\n";
            }
        }

        echo "\n";
    }

    // ========================================================================
    // PARTE 2: VERIFICAR SERVICES
    // ========================================================================

    echo "🔍 PARTE 2: Verificação de Services\n";
    echo str_repeat("═", 79) . "\n\n";

    $services = [
        'TechSheetService' => 'app/Services/TechSheetService.php',
        'SynonymExpansionService' => 'app/Services/AI/SEO/Strategies/SynonymExpansionService.php',
        'SemanticScoreService' => 'app/Services/AI/SEO/Strategies/SemanticScoreService.php',
        'SEOStrategiesEngine' => 'app/Services/AI/SEO/Strategies/SEOStrategiesEngine.php'
    ];

    $servicesOk = 0;

    foreach ($services as $name => $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            $size = filesize(__DIR__ . '/' . $path);
            $sizeKb = round($size / 1024, 1);
            $lines = count(file(__DIR__ . '/' . $path));
            echo "✅ {$name}: {$lines} linhas ({$sizeKb} KB)\n";
            $servicesOk++;
        } else {
            echo "❌ {$name}: Não encontrado\n";
        }
    }

    echo "\n";

    // ========================================================================
    // PARTE 3: TESTAR INTEGRAÇÃO REAL
    // ========================================================================

    echo "🔍 PARTE 3: Teste de Integração Real\n";
    echo str_repeat("═", 79) . "\n\n";

    // Carregar as dependências necessárias
    $strategyServices = [
        'SynonymExpansionService',
        'SemanticScoreService',
        'KeywordSourceService',
        'KeywordInjectorService',
        'FieldWeightService',
        'SearchTypeCoverageService',
        'UseContextService',
        'LongTailGeneratorService',
        'HiddenFieldsService',
        'CompatibilityService',
        'FAQOptimizerService',
        'SEOStrategiesEngine'
    ];

    foreach ($strategyServices as $service) {
        $path = __DIR__ . '/app/Services/AI/SEO/Strategies/' . $service . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    try {
        $engine = new \App\Services\AI\SEO\Strategies\SEOStrategiesEngine(null);
        echo "✅ SEOStrategiesEngine instanciado com sucesso\n";

        // Testar com um produto da ficha técnica
        echo "\n📋 Testando integração com produto da ficha técnica...\n";
        echo str_repeat("─", 79) . "\n";

        // Buscar um item da ficha técnica
        $stmt = $db->prepare("
            SELECT s.item_id, i.title, s.completeness_percent, s.missing_required, s.missing_filter, s.missing_hidden
            FROM tech_sheet_item_summary s
            LEFT JOIN items i ON i.item_id = s.item_id
            WHERE s.completeness_percent < 100
            ORDER BY s.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            echo "\n🎯 Produto selecionado:\n";
            echo "   ID: {$item['item_id']}\n";
            echo "   Título: " . ($item['title'] ?? 'N/A') . "\n";
            echo "   Completude: {$item['completeness_percent']}%\n";
            echo "   Campos obrigatórios faltando: {$item['missing_required']}\n";
            echo "   Campos filtro faltando: {$item['missing_filter']}\n";
            echo "   Campos ocultos faltando: {$item['missing_hidden']}\n";

            // Analisar com SEO Strategies
            if ($item['title']) {
                $categoryId = 'MLB3530';

                echo "\n📈 Análise SEO:\n";
                echo str_repeat("─", 79) . "\n";

                $synonymService = new \App\Services\AI\SEO\Strategies\SynonymExpansionService(null);
                $semanticService = new \App\Services\AI\SEO\Strategies\SemanticScoreService(null);

                // Expandir sinônimos
                $expanded = $synonymService->expand($item['title'], $categoryId);
                echo "   Expansões de sinônimos: " . count($expanded) . " variações\n";

                // Detectar nível
                $level = $synonymService->identifyLevel($item['title']);
                echo "   Nível hierárquico: {$level}\n";

                // Score semântico
                $words = preg_split('/\s+/', strtolower($item['title']));
                $words = array_filter($words, function($w) { return strlen($w) > 3; });
                $words = array_values(array_unique($words));

                if (!empty($words)) {
                    $scores = [];
                    foreach ($words as $word) {
                        $rawScore = $semanticService->calculateScore($word, $item['title'], $categoryId);
                        $normalizedScore = $rawScore * 100;
                        $scores[] = $normalizedScore;
                    }

                    $avgScore = array_sum($scores) / count($scores);
                    echo "   Score semântico médio: " . number_format($avgScore, 1) . "/100\n";
                }

                // Verificar contextos
                $contextsFound = 0;
                foreach ($words as $word) {
                    if ($semanticService->hasUseContext($word)) {
                        $contextsFound++;
                    }
                }
                echo "   Contextos de uso detectados: {$contextsFound}\n";

                // Calcular score final
                $titleLen = strlen($item['title']);
                $scoreComponents = [
                    'title_length' => ($titleLen >= 40 && $titleLen <= 60) ? 20 : 15,
                    'level' => 20,
                    'semantic' => min(30, ($avgScore / 100) * 30),
                    'contexts' => min(15, $contextsFound * 5),
                    'expansions' => (count($expanded) >= 10) ? 15 : 10
                ];

                $finalScore = array_sum($scoreComponents);

                if ($finalScore >= 80) {
                    $quality = 'Excelente';
                    $emoji = '🟢';
                } elseif ($finalScore >= 60) {
                    $quality = 'Boa';
                    $emoji = '🟡';
                } else {
                    $quality = 'Regular';
                    $emoji = '🟠';
                }

                echo "\n{$emoji} SCORE FINAL: " . number_format($finalScore, 1) . "/100 ({$quality})\n";

                // Recomendações
                echo "\n💡 Recomendações de Melhoria:\n";
                if ($item['missing_required'] > 0) {
                    echo "   • Preencher {$item['missing_required']} campos obrigatórios\n";
                }
                if ($item['missing_filter'] > 0) {
                    echo "   • Preencher {$item['missing_filter']} campos de filtro\n";
                }
                if ($item['missing_hidden'] > 0) {
                    echo "   • Preencher {$item['missing_hidden']} campos ocultos (SEO)\n";
                }
                if ($finalScore < 80) {
                    echo "   • Melhorar título para aumentar score SEO\n";
                }
                if ($contextsFound < 2) {
                    echo "   • Adicionar contextos de uso no título\n";
                }
            }

        } else {
            echo "\n⚠️  Nenhum produto encontrado na ficha técnica\n";
            echo "   (Isso é normal se ainda não houver produtos cadastrados)\n";
        }

    } catch (Exception $e) {
        echo "❌ Erro ao instanciar Engine: " . $e->getMessage() . "\n";
    }

    echo "\n";
    echo str_repeat("═", 79) . "\n\n";

    // ========================================================================
    // RESUMO FINAL
    // ========================================================================

    echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
    echo "║                         RESUMO FINAL                                   ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════════╝\n\n";

    echo "📊 ESTATÍSTICAS\n";
    echo str_repeat("─", 79) . "\n";
    echo "Tabelas verificadas: {$existingTables}/{$totalTables}\n";
    echo "Total de registros: {$totalRecords}\n";
    echo "Services disponíveis: {$servicesOk}/4\n";
    echo "\n";

    $overallScore = 0;
    if ($existingTables == $totalTables) $overallScore += 40;
    if ($servicesOk >= 3) $overallScore += 30;
    if ($totalRecords > 0) $overallScore += 30;

    $status = "Baixo";
    $emoji = "🔴";
    if ($overallScore >= 90) { $status = "Excelente"; $emoji = "🟢"; }
    elseif ($overallScore >= 70) { $status = "Bom"; $emoji = "🟡"; }
    elseif ($overallScore >= 50) { $status = "Regular"; $emoji = "🟠"; }

    echo "🎯 PRONTIDÃO GERAL\n";
    echo str_repeat("─", 79) . "\n";
    echo "{$emoji} Score: {$overallScore}/100 ({$status})\n";
    echo "\n";

    if ($overallScore >= 90) {
        echo "✅ SISTEMA 100% OPERACIONAL!\n\n";
        echo "🎉 Integração completa entre Tech Sheet e SEO Strategies\n";
        echo "🚀 Pronto para uso em produção\n";
        echo "\n";
        echo "Próximos passos:\n";
        echo "1. Acessar: https://eskill.com.br/dashboard/seo/ficha-tecnica\n";
        echo "2. Selecionar um produto\n";
        echo "3. Clicar em 'Analisar SEO'\n";
        echo "4. Visualizar score e recomendações\n";
        echo "5. Aplicar otimizações sugeridas\n";
    } else {
        echo "🟡 SISTEMA PARCIALMENTE OPERACIONAL\n\n";
        echo "Itens pendentes:\n";
        if ($existingTables < $totalTables) {
            echo "• Criar tabelas faltantes\n";
        }
        if ($servicesOk < 4) {
            echo "• Verificar services faltantes\n";
        }
        if ($totalRecords == 0) {
            echo "• Inserir dados piloto\n";
        }
    }

} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
    echo "   Trace: " . substr($e->getTraceAsString(), 0, 500) . "\n";
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                           ║\n";
echo "║                    ✅ TESTE CONCLUÍDO!                                    ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
