<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use Tests\TestCase;
use App\Services\MercadoLivre\SmartQAService;

/**
 * Testes unitários para SmartQAService.
 * Verifica existência de todos os métodos e lógica pura de análise de perguntas.
 */
class SmartQAServiceTest extends TestCase
{
    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasRequiredPublicMethods(): void
    {
        $methods = [
            'processAutoResponses',
            'generateProactiveQA',
            'processBatchAnswers',
            'updateKnowledgeBase',
            'getQAAnalytics',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(SmartQAService::class, $method),
                "SmartQAService deve ter método público {$method}()"
            );
        }
    }

    public function testHasAllPrivateHelperMethods(): void
    {
        $requiredPrivateMethods = [
            'processSingleQuestion',
            'analyzeQuestion',
            'shouldAutoRespond',
            'generateAutoAnswer',
            'generateQAForProduct',
            'sendAutoAnswer',
            'analyzeQuestionWithAI',
            'generateAIAnswer',
            'getPendingQuestions',
            'loadQAConfig',
            'generateBatchId',
            'getProductInfo',
            'getProductsNeedingQA',
            'loadKnowledgeBase',
            'processKnowledgeEntry',
            'addToKnowledgeBase',
            'rebuildKnowledgeBaseCache',
            'hasKnowledgeBaseAnswer',
            'findKnowledgeBaseAnswer',
            'incrementKBUsage',
            'analyzeQuestionWithRules',
            'requiresInventoryCheck',
            'requiresShippingInfo',
            'shouldEscalate',
            'calculateAnswerConfidence',
            'calculateQuestionPriority',
            'personalizeAnswer',
            'getTemplateAnswer',
            'getQuestionTemplates',
            'generateQuestionFromTemplate',
            'generateCompetitorQuestions',
            'generateShippingQuestion',
            'generateWarrantyQuestion',
            'saveProactiveQA',
            'estimateQAImpact',
            'optimizeBatchResponses',
            'logAutoResponse',
            'buildAnalysisPrompt',
            'buildAnswerPrompt',
            'getQAOverview',
            'getResponsePerformanceAnalytics',
            'getQuestionTrends',
            'getAutoResponseEffectiveness',
            'getEscalationPatterns',
            'getKnowledgeBasePerformance',
            'getProductQuestionHeatmap',
            'getTimeToAnswerAnalysis',
            'generateProcessingSummary',
        ];

        foreach ($requiredPrivateMethods as $method) {
            $this->assertTrue(
                method_exists(SmartQAService::class, $method),
                "SmartQAService deve ter método {$method}()"
            );
        }
    }

    public function testHasRequiredDependencies(): void
    {
        $reflection = new \ReflectionClass(SmartQAService::class);

        $this->assertTrue($reflection->hasProperty('mlClient'), 'Deve ter propriedade mlClient');
        $this->assertTrue($reflection->hasProperty('db'), 'Deve ter propriedade db');
        $this->assertTrue($reflection->hasProperty('cache'), 'Deve ter propriedade cache');
        $this->assertTrue($reflection->hasProperty('accountId'), 'Deve ter propriedade accountId');
    }

    // =============================
    // TESTES DE LÓGICA PURA
    // =============================

    private function getInstance(): SmartQAService
    {
        return (new \ReflectionClass(SmartQAService::class))->newInstanceWithoutConstructor();
    }

    private function invokePrivate(string $method, ...$args)
    {
        $ref = new \ReflectionMethod(SmartQAService::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->getInstance(), ...$args);
    }

    // --- requiresInventoryCheck ---
    public function testRequiresInventoryCheckPositive(): void
    {
        $result = $this->invokePrivate('requiresInventoryCheck', ['question_text' => 'Tem estoque disponível?']);
        $this->assertTrue($result);
    }

    public function testRequiresInventoryCheckNegative(): void
    {
        $result = $this->invokePrivate('requiresInventoryCheck', ['question_text' => 'Qual a cor do produto?']);
        $this->assertFalse($result);
    }

    public function testRequiresInventoryCheckQuantidade(): void
    {
        $result = $this->invokePrivate('requiresInventoryCheck', ['question_text' => 'Qual a quantidade máxima?']);
        $this->assertTrue($result);
    }

    // --- requiresShippingInfo ---
    public function testRequiresShippingInfoPositive(): void
    {
        $result = $this->invokePrivate('requiresShippingInfo', ['question_text' => 'Qual o prazo de entrega?']);
        $this->assertTrue($result);
    }

    public function testRequiresShippingInfoFrete(): void
    {
        $result = $this->invokePrivate('requiresShippingInfo', ['question_text' => 'O frete é grátis?']);
        $this->assertTrue($result);
    }

    public function testRequiresShippingInfoNegative(): void
    {
        $result = $this->invokePrivate('requiresShippingInfo', ['question_text' => 'Aceita cartão?']);
        $this->assertFalse($result);
    }

    // --- calculateAnswerConfidence ---
    public function testCalculateAnswerConfidenceShortAnswer(): void
    {
        $result = $this->invokePrivate('calculateAnswerConfidence', ['question_text' => 'pergunta'], 'sim');
        $this->assertGreaterThanOrEqual(0.5, $result);
        $this->assertLessThanOrEqual(0.95, $result);
    }

    public function testCalculateAnswerConfidenceLongAnswerWithOverlap(): void
    {
        $question = ['question_text' => 'Qual a voltagem deste motor elétrico?'];
        $answer = 'Este motor elétrico funciona em 110V e 220V (bivolt). A voltagem pode ser selecionada no seletor na parte traseira do motor.';

        $result = $this->invokePrivate('calculateAnswerConfidence', $question, $answer);
        // Long answer + keyword overlap should give higher confidence
        $this->assertGreaterThan(0.6, $result);
    }

    // --- calculateQuestionPriority ---
    public function testQuestionPriorityCritical(): void
    {
        $question = ['date_created' => date('Y-m-d H:i:s', strtotime('-24 hours'))];
        $analysis = ['sentiment' => 'negative', 'urgency' => 'high'];

        $result = $this->invokePrivate('calculateQuestionPriority', $question, $analysis);
        $this->assertEquals('critical', $result);
    }

    public function testQuestionPriorityLow(): void
    {
        $question = ['date_created' => date('Y-m-d H:i:s')];
        $analysis = ['sentiment' => 'neutral', 'urgency' => 'low', 'complexity' => 'low'];

        $result = $this->invokePrivate('calculateQuestionPriority', $question, $analysis);
        $this->assertEquals('low', $result);
    }

    public function testQuestionPriorityMedium(): void
    {
        $question = ['date_created' => date('Y-m-d H:i:s', strtotime('-6 hours'))];
        $analysis = ['sentiment' => 'neutral', 'urgency' => 'low', 'complexity' => 'high'];

        $result = $this->invokePrivate('calculateQuestionPriority', $question, $analysis);
        $this->assertContains($result, ['medium', 'high']);
    }

    // --- personalizeAnswer ---
    public function testPersonalizeAnswerReplacesPlaceholders(): void
    {
        $result = $this->invokePrivate(
            'personalizeAnswer',
            '{saudacao}! O {produto} está disponível.',
            ['item_title' => 'Furadeira Bosch']
        );

        $this->assertStringContainsString('Furadeira Bosch', $result);
        $this->assertStringNotContainsString('{produto}', $result);
        $this->assertStringNotContainsString('{saudacao}', $result);
    }

    // --- generateBatchId ---
    public function testGenerateBatchIdFormat(): void
    {
        $result = $this->invokePrivate('generateBatchId');
        $this->assertIsString($result);
        $this->assertStringStartsWith('batch_', $result);
    }

    // --- getQuestionTemplates ---
    public function testGetQuestionTemplatesReturnsArray(): void
    {
        $result = $this->invokePrivate('getQuestionTemplates', 'MLB12345');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    // --- generateQuestionFromTemplate ---
    public function testGenerateQuestionFromTemplate(): void
    {
        $template = ['template' => 'Este {produto} acompanha garantia?', 'type' => 'warranty'];
        $productInfo = ['title' => 'Notebook Dell', 'id' => 'MLB123'];

        $result = $this->invokePrivate('generateQuestionFromTemplate', $template, $productInfo);
        $this->assertArrayHasKey('question', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertStringContainsString('Notebook Dell', $result['question']);
    }

    // --- generateShippingQuestion ---
    public function testGenerateShippingQuestion(): void
    {
        $result = $this->invokePrivate('generateShippingQuestion', ['title' => 'Produto X', 'id' => 'MLB1']);
        $this->assertArrayHasKey('question', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('shipping', $result['type']);
    }

    // --- generateWarrantyQuestion ---
    public function testGenerateWarrantyQuestion(): void
    {
        $result = $this->invokePrivate('generateWarrantyQuestion', ['title' => 'Produto Y', 'id' => 'MLB2']);
        $this->assertArrayHasKey('question', $result);
        $this->assertEquals('warranty', $result['type']);
    }

    // --- generateCompetitorQuestions ---
    public function testGenerateCompetitorQuestions(): void
    {
        $result = $this->invokePrivate('generateCompetitorQuestions', ['title' => 'Produto Z', 'id' => 'MLB3', 'category_id' => 'CAT1']);
        $this->assertIsArray($result);
    }

    // --- estimateQAImpact ---
    public function testEstimateQAImpact(): void
    {
        $proactiveQA = [
            ['questions' => [['q' => '1'], ['q' => '2']]],
            ['questions' => [['q' => '3']]],
        ];

        $result = $this->invokePrivate('estimateQAImpact', $proactiveQA);
        $this->assertArrayHasKey('total_proactive_questions', $result);
        $this->assertArrayHasKey('estimated_reduction_pct', $result);
        $this->assertEquals(3, $result['total_proactive_questions']);
    }

    // --- buildAnalysisPrompt ---
    public function testBuildAnalysisPrompt(): void
    {
        $question = ['question_text' => 'Tem na cor azul?'];
        $productInfo = ['title' => 'Camiseta', 'price' => 59.90];

        $result = $this->invokePrivate('buildAnalysisPrompt', $question, $productInfo);
        $this->assertIsString($result);
        $this->assertStringContainsString('azul', $result);
    }

    // --- buildAnswerPrompt ---
    public function testBuildAnswerPrompt(): void
    {
        $question = ['question_text' => 'Aceita troca?'];
        $analysis = ['intent' => 'exchange', 'sentiment' => 'neutral'];

        $result = $this->invokePrivate('buildAnswerPrompt', $question, $analysis);
        $this->assertIsString($result);
        $this->assertStringContainsString('troca', $result);
    }

    // --- generateProcessingSummary ---
    public function testGenerateProcessingSummaryEmpty(): void
    {
        $result = $this->invokePrivate('generateProcessingSummary', []);
        $this->assertArrayHasKey('total_processed', $result);
        $this->assertEquals(0, $result['total_processed']);
    }

    public function testGenerateProcessingSummaryWithResults(): void
    {
        $results = [
            ['auto_respond' => true, 'confidence' => 0.85, 'sentiment' => 'positive'],
            ['auto_respond' => true, 'confidence' => 0.90, 'sentiment' => 'neutral'],
            ['escalate' => true, 'confidence' => 0.30, 'sentiment' => 'negative'],
        ];

        $result = $this->invokePrivate('generateProcessingSummary', $results);
        $this->assertEquals(3, $result['total_processed']);
        $this->assertArrayHasKey('auto_answered', $result);
    }
}
