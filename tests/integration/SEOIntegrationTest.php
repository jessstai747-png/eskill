<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\AISEOOptimizerService;
use App\Services\TitleOptimizerService;
use App\Services\KeywordResearchService;
use App\Services\CompetitorAnalysisService;
use App\Services\AIContentGeneratorService;

/**
 * Teste de Integração - Sistema SEO Profissional
 * 
 * Este teste valida que todas as funcionalidades implementadas
 * estão funcionando corretamente com integração real de IA.
 */
class SEOIntegrationTest
{
    private AISEOOptimizerService $aiSeoOptimizer;
    private TitleOptimizerService $titleOptimizer;
    private KeywordResearchService $keywordResearch;
    private CompetitorAnalysisService $competitorAnalysis;
    private AIContentGeneratorService $aiContentGenerator;

    public function __construct()
    {
        // Inicializar serviços
        $this->aiSeoOptimizer = new AISEOOptimizerService();
        $this->titleOptimizer = new TitleOptimizerService();
        $this->keywordResearch = new KeywordResearchService();
        $this->competitorAnalysis = new CompetitorAnalysisService();
        $this->aiContentGenerator = new AIContentGeneratorService();
    }

    /**
     * Executar todos os testes de integração
     */
    public function runAllTests(): array
    {
        echo "🧪 INICIANDO TESTES DE INTEGRAÇÃO SEO\n";
        echo str_repeat("=", 60) . "\n";

        $results = [
            'tests_run' => 0,
            'tests_passed' => 0,
            'tests_failed' => 0,
            'details' => []
        ];

        // Testar AISEOOptimizerService
        $this->runTest('AISEOOptimizerService - Análise SEO', 
            [$this, 'testAISEOAnalysis'], $results);

        // Testar TitleOptimizerService
        $this->runTest('TitleOptimizerService - Otimização de Título', 
            [$this, 'testTitleOptimization'], $results);

        // Testar KeywordResearchService
        $this->runTest('KeywordResearchService - Pesquisa de Keywords', 
            [$this, 'testKeywordResearch'], $results);

        // Testar CompetitorAnalysisService
        $this->runTest('CompetitorAnalysisService - Análise de Concorrência', 
            [$this, 'testCompetitorAnalysis'], $results);

        // Testar AIContentGeneratorService
        $this->runTest('AIContentGeneratorService - Geração de Conteúdo', 
            [$this, 'testAIContentGeneration'], $results);

        // Testar integração completa
        $this->runTest('Integração Completa - Otimização de Produto', 
            [$this, 'testCompleteProductOptimization'], $results);

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 RESULTADOS FINAIS:\n";
        echo "- Testes Executados: {$results['tests_run']}\n";
        echo "- Testes Passaram: {$results['tests_passed']}\n";
        echo "- Testes Falharam: {$results['tests_failed']}\n";
        echo "- Taxa de Sucesso: " . round(($results['tests_passed'] / max(1, $results['tests_run'])) * 100, 2) . "%\n";

        if ($results['tests_failed'] === 0) {
            echo "\n✅ TODOS OS TESTES PASSARAM! Sistema SEO está funcionando corretamente.\n";
        } else {
            echo "\n⚠️  ALGUNS TESTES FALHARAM. Verifique os detalhes acima.\n";
        }

        return $results;
    }

    /**
     * Executar um teste individual
     */
    private function runTest(string $testName, callable $testFunction, array &$results): void
    {
        $results['tests_run']++;

        echo "\n🔹 Testando: {$testName}... ";

        try {
            $result = $testFunction();
            
            if ($result['success']) {
                echo "✅ PASSOU\n";
                $results['tests_passed']++;
                
                if (isset($result['details'])) {
                    echo "   - " . $result['details'] . "\n";
                }
            } else {
                echo "❌ FALHOU\n";
                $results['tests_failed']++;
                
                if (isset($result['error'])) {
                    echo "   - Erro: " . $result['error'] . "\n";
                }
            }
            
            $results['details'][] = [
                'test' => $testName,
                'success' => $result['success'] ?? false,
                'details' => $result['details'] ?? null,
                'error' => $result['error'] ?? null
            ];

        } catch (\Exception $e) {
            echo "❌ FALHOU (EXCEÇÃO)\n";
            echo "   - Exceção: " . $e->getMessage() . "\n";
            $results['tests_failed']++;
            
            $results['details'][] = [
                'test' => $testName,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testar análise SEO com IA
     */
    public function testAISEOAnalysis(): array
    {
        $productData = [
            'id' => 'TEST123',
            'title' => 'Notebook Dell Inspiron i15 5501-A20P Intel Core i5 8GB 256GB SSD 15.6" Full HD Windows 11',
            'description' => 'Notebook Dell Inspiron com processador Intel Core i5, 8GB de RAM, 256GB SSD, tela Full HD de 15.6 polegadas e Windows 11. Ideal para trabalho e estudos.',
            'category_id' => 'MLB1000',
            'price' => 3499.99,
            'attributes' => [
                ['id' => 'BRAND', 'name' => 'Marca', 'value_name' => 'Dell'],
                ['id' => 'MODEL', 'name' => 'Modelo', 'value_name' => 'Inspiron i15 5501-A20P'],
                ['id' => 'PROCESSOR', 'name' => 'Processador', 'value_name' => 'Intel Core i5'],
                ['id' => 'RAM', 'name' => 'Memória RAM', 'value_name' => '8GB'],
                ['id' => 'STORAGE', 'name' => 'Armazenamento', 'value_name' => '256GB SSD'],
            ]
        ];

        try {
            $result = $this->aiSeoOptimizer->analyzeSEO($productData);
            
            if ($result['success']) {
                $score = $result['overall_seo_score'] ?? 0;
                $analysisCount = count($result['detailed_analysis'] ?? []);
                
                return [
                    'success' => true,
                    'details' => "Análise SEO concluída com score: {$score}/100, {$analysisCount} análises detalhadas"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Análise SEO falhou'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testar otimização de título
     */
    public function testTitleOptimization(): array
    {
        $currentTitle = 'Notebook Dell Inspiron i15 5501-A20P Intel Core i5 8GB 256GB SSD 15.6" Full HD Windows 11';
        
        $productInfo = [
            'brand' => 'Dell',
            'model' => 'Inspiron i15 5501-A20P',
            'category_id' => 'MLB1000',
            'attributes' => [
                ['id' => 'BRAND', 'name' => 'Marca', 'value_name' => 'Dell'],
                ['id' => 'MODEL', 'name' => 'Modelo', 'value_name' => 'Inspiron i15 5501-A20P'],
            ]
        ];

        try {
            $result = $this->titleOptimizer->optimize($currentTitle, $productInfo);
            
            $originalLength = strlen($result['original_title']);
            $optimizedLength = strlen($result['optimized_title']);
            $scoreImprovement = $result['score_after'] - $result['score_before'];
            
            if ($optimizedLength <= 60) {
                return [
                    'success' => true,
                    'details' => "Título otimizado: {$originalLength}→{$optimizedLength} chars, melhoria de score: {$scoreImprovement} pontos"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Título otimizado excede 60 caracteres: {$optimizedLength}"
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testar pesquisa de keywords
     */
    public function testKeywordResearch(): array
    {
        try {
            $result = $this->keywordResearch->researchKeywords('MLB1000', 'notebook');
            
            $primaryKeywords = count($result['primary_keywords'] ?? []);
            $trendingKeywords = count($result['trending_keywords'] ?? []);
            
            if ($primaryKeywords > 0) {
                return [
                    'success' => true,
                    'details' => "Pesquisa de keywords concluída: {$primaryKeywords} primárias, {$trendingKeywords} tendências"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Nenhuma keyword encontrada"
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testar análise de concorrência
     */
    public function testCompetitorAnalysis(): array
    {
        try {
            $result = $this->competitorAnalysis->analyzeCompetition('MLB1000', 'Dell');
            
            $totalSellers = $result['total_sellers'] ?? 0;
            $avgPrice = $result['market_avg_price'] ?? 0;
            
            if ($totalSellers > 0) {
                return [
                    'success' => true,
                    'details' => "Análise de concorrência: {$totalSellers} vendedores, preço médio R$ " . number_format($avgPrice, 2)
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Nenhum vendedor encontrado"
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testar geração de conteúdo com IA
     */
    public function testAIContentGeneration(): array
    {
        $productData = [
            'title' => 'Notebook Dell Inspiron',
            'brand' => 'Dell',
            'category_id' => 'MLB1000',
            'attributes' => [
                ['name' => 'Processador', 'value' => 'Intel Core i5'],
                ['name' => 'Memória', 'value' => '8GB RAM'],
                ['name' => 'Armazenamento', 'value' => '256GB SSD'],
                ['name' => 'Tela', 'value' => '15.6" Full HD'],
            ]
        ];

        try {
            $result = $this->aiContentGenerator->generateProductDescription($productData);
            
            if ($result['success']) {
                $descriptionLength = strlen($result['description'] ?? '');
                $qualityScore = $result['quality_score'] ?? 0;
                
                return [
                    'success' => true,
                    'details' => "Descrição gerada: {$descriptionLength} caracteres, qualidade: {$qualityScore}/100"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Geração de conteúdo falhou'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Testar otimização completa de produto
     */
    public function testCompleteProductOptimization(): array
    {
        $productData = [
            'id' => 'COMPLETE_TEST_001',
            'title' => 'Smartphone Samsung Galaxy A52s 5G 128GB 8GB RAM Câm. Quádrupla Câm. Selfie 32MP',
            'description' => 'Smartphone Samsung Galaxy A52s 5G com 128GB de armazenamento, 8GB de RAM, câmera quádrupla traseira e câmera frontal de 32MP. Tela Super AMOLED de 6.5 polegadas com taxa de atualização de 90Hz.',
            'category_id' => 'MLB1055',
            'price' => 2499.99,
            'brand' => 'Samsung',
            'model' => 'Galaxy A52s 5G',
            'attributes' => [
                ['id' => 'BRAND', 'name' => 'Marca', 'value_name' => 'Samsung'],
                ['id' => 'MODEL', 'name' => 'Modelo', 'value_name' => 'Galaxy A52s 5G'],
                ['id' => 'STORAGE', 'name' => 'Armazenamento', 'value_name' => '128GB'],
                ['id' => 'RAM', 'name' => 'Memória RAM', 'value_name' => '8GB'],
                ['id' => 'CAMERA', 'name' => 'Câmera Traseira', 'value_name' => 'Quádrupla'],
                ['id' => 'CAMERA_FRONT', 'name' => 'Câmera Frontal', 'value_name' => '32MP'],
            ]
        ];

        try {
            // Testar análise SEO completa
            $analysisResult = $this->aiSeoOptimizer->analyzeSEO($productData);
            
            if (!$analysisResult['success']) {
                return [
                    'success' => false,
                    'error' => "Análise SEO falhou: " . ($analysisResult['error'] ?? 'Erro desconhecido')
                ];
            }

            // Testar otimização completa
            $optimizationResult = $this->aiSeoOptimizer->optimizeProduct($productData);
            
            if ($optimizationResult['success']) {
                $improvement = $optimizationResult['improvement'] ?? 0;
                $originalScore = $optimizationResult['before_score'] ?? 0;
                $finalScore = $optimizationResult['after_score'] ?? 0;
                
                return [
                    'success' => true,
                    'details' => "Otimização completa: {$originalScore}→{$finalScore} (melhoria: {$improvement} pontos)"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $optimizationResult['error'] ?? 'Otimização falhou'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Executar os testes se chamado diretamente
if (php_sapi_name() === 'cli') {
    $test = new SEOIntegrationTest();
    $results = $test->runAllTests();
    
    // Retornar código de saída para CI/CD
    exit($results['tests_failed'] > 0 ? 1 : 0);
}