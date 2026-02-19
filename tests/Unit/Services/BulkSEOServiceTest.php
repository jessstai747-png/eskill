<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \App\Services\BulkSEOService
 *
 * Testes comportamentais para BulkSEOService.
 * Foco em metodos puros que nao requerem DB/API:
 * - Normalizacao e comparacao de texto
 * - Calculo de diffs e similaridade
 * - Avaliacao de riscos
 * - Sanitizacao de inputs
 * - Interpretacao de erros ML
 */
class BulkSEOServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\BulkSEOService::class);
    }

    /**
     * Helper: invoca metodo privado sem instancia (via closure bind em mock)
     */
    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        // Criar instancia sem construtor
        $instance = $this->reflection->newInstanceWithoutConstructor();
        return $method->invokeArgs($instance, $args);
    }

    // =========================================================================
    // Structural Tests
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Services\BulkSEOService::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = (new ReflectionClass(\App\Services\BulkSEOService::class))->getFileName();
        $this->assertNotFalse($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testClassHasExpectedConstants(): void
    {
        $constants = $this->reflection->getConstants();
        $this->assertArrayHasKey('MAX_ITEMS_PER_BATCH', $constants);
        $this->assertArrayHasKey('RATE_LIMIT_DELAY_MS', $constants);
        $this->assertArrayHasKey('MAX_TITLE_LENGTH', $constants);
        $this->assertArrayHasKey('MIN_DESCRIPTION_LENGTH', $constants);
        $this->assertArrayHasKey('RISK_NONE', $constants);
        $this->assertArrayHasKey('RISK_LOW', $constants);
        $this->assertArrayHasKey('RISK_MEDIUM', $constants);
        $this->assertArrayHasKey('RISK_HIGH', $constants);
    }

    public function testConstantValues(): void
    {
        $constants = $this->reflection->getConstants();
        $this->assertSame(50, $constants['MAX_ITEMS_PER_BATCH']);
        $this->assertSame(200, $constants['RATE_LIMIT_DELAY_MS']);
        $this->assertSame(60, $constants['MAX_TITLE_LENGTH']);
        $this->assertSame(100, $constants['MIN_DESCRIPTION_LENGTH']);
        $this->assertSame('none', $constants['RISK_NONE']);
        $this->assertSame('low', $constants['RISK_LOW']);
        $this->assertSame('medium', $constants['RISK_MEDIUM']);
        $this->assertSame('high', $constants['RISK_HIGH']);
    }

    /**
     * @dataProvider publicMethodsProvider
     */
    public function testPublicMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function publicMethodsProvider(): array
    {
        return [
            ['dryRunBatch'],
            ['applyBatch'],
            ['startBatchJob'],
            ['getJobStatus'],
            ['getBulkHistory'],
            ['getBatchHistory'],
            ['rollbackBatch'],
        ];
    }

    /**
     * @dataProvider privateMethodsProvider
     */
    public function testPrivateMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPrivate());
    }

    public static function privateMethodsProvider(): array
    {
        return [
            ['dryRunSingleItem'],
            ['applySingleItem'],
            ['hasRealChange'],
            ['normalizeForComparison'],
            ['generateTextDiff'],
            ['generateDescriptionDiff'],
            ['calculateSimilarity'],
            ['assessTitleRisks'],
            ['assessDescriptionRisks'],
            ['calculateOverallRisk'],
            ['getRiskSummary'],
            ['generateSummaryMessage'],
            ['interpretMLError'],
            ['isRetryableError'],
            ['sanitizeItemIds'],
            ['getItemDescription'],
            ['dispatchBackgroundJob'],
            ['processJobSync'],
            ['updateJobStatus'],
            ['getJobStatusFromDb'],
            ['assertBulkJobsTableExists'],
            ['isExecDisabled'],
            ['isShellExecDisabled'],
        ];
    }

    public function testConstructorRequiresAccountId(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('accountId', $params[0]->getName());
        $this->assertSame('int', $params[0]->getType()->getName());
    }

    // =========================================================================
    // Behavioral: normalizeForComparison
    // =========================================================================

    public function testNormalizeForComparisonLowersCase(): void
    {
        $result = $this->invokePrivateMethod('normalizeForComparison', ['HELLO WORLD']);
        $this->assertSame('hello world', $result);
    }

    public function testNormalizeForComparisonTrimsWhitespace(): void
    {
        $result = $this->invokePrivateMethod('normalizeForComparison', ['  hello  ']);
        $this->assertSame('hello', $result);
    }

    public function testNormalizeForComparisonCollapsesSpaces(): void
    {
        $result = $this->invokePrivateMethod('normalizeForComparison', ['hello    world   foo']);
        $this->assertSame('hello world foo', $result);
    }

    public function testNormalizeForComparisonRemovesPunctuation(): void
    {
        $result = $this->invokePrivateMethod('normalizeForComparison', ['hello, world! (foo)']);
        $this->assertSame('hello world foo', $result);
    }

    public function testNormalizeForComparisonHandlesEmptyString(): void
    {
        $result = $this->invokePrivateMethod('normalizeForComparison', ['']);
        $this->assertSame('', $result);
    }

    // =========================================================================
    // Behavioral: hasRealChange
    // =========================================================================

    public function testHasRealChangeReturnsFalseForIdentical(): void
    {
        $result = $this->invokePrivateMethod('hasRealChange', ['Hello World', 'Hello World']);
        $this->assertFalse($result);
    }

    public function testHasRealChangeReturnsFalseForCaseDifference(): void
    {
        $result = $this->invokePrivateMethod('hasRealChange', ['Hello World', 'hello world']);
        $this->assertFalse($result);
    }

    public function testHasRealChangeReturnsFalseForWhitespaceDifference(): void
    {
        $result = $this->invokePrivateMethod('hasRealChange', ['Hello  World', 'Hello World']);
        $this->assertFalse($result);
    }

    public function testHasRealChangeReturnsTrueForDifferentWords(): void
    {
        $result = $this->invokePrivateMethod('hasRealChange', ['Hello World', 'Goodbye World']);
        $this->assertTrue($result);
    }

    public function testHasRealChangeReturnsFalseForPunctuationOnly(): void
    {
        $result = $this->invokePrivateMethod('hasRealChange', ['Hello, World!', 'Hello World']);
        $this->assertFalse($result);
    }

    // =========================================================================
    // Behavioral: calculateSimilarity
    // =========================================================================

    public function testCalculateSimilarityIdenticalStrings(): void
    {
        $result = $this->invokePrivateMethod('calculateSimilarity', ['hello', 'hello']);
        $this->assertSame(100.0, $result);
    }

    public function testCalculateSimilarityBothEmpty(): void
    {
        $result = $this->invokePrivateMethod('calculateSimilarity', ['', '']);
        $this->assertSame(100.0, $result);
    }

    public function testCalculateSimilarityOneEmpty(): void
    {
        $result = $this->invokePrivateMethod('calculateSimilarity', ['hello', '']);
        $this->assertSame(0.0, $result);

        $result2 = $this->invokePrivateMethod('calculateSimilarity', ['', 'hello']);
        $this->assertSame(0.0, $result2);
    }

    public function testCalculateSimilarityReturnsFloat(): void
    {
        $result = $this->invokePrivateMethod('calculateSimilarity', ['hello', 'hallo']);
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0.0, $result);
        $this->assertLessThan(100.0, $result);
    }

    // =========================================================================
    // Behavioral: generateTextDiff
    // =========================================================================

    public function testGenerateTextDiffReturnsExpectedStructure(): void
    {
        $result = $this->invokePrivateMethod('generateTextDiff', ['hello world', 'hello foo']);
        $this->assertArrayHasKey('before', $result);
        $this->assertArrayHasKey('after', $result);
        $this->assertArrayHasKey('before_length', $result);
        $this->assertArrayHasKey('after_length', $result);
        $this->assertArrayHasKey('length_change', $result);
        $this->assertArrayHasKey('words_removed', $result);
        $this->assertArrayHasKey('words_added', $result);
        $this->assertArrayHasKey('similarity', $result);
    }

    public function testGenerateTextDiffDetectsWordChanges(): void
    {
        $result = $this->invokePrivateMethod('generateTextDiff', ['hello world', 'hello foo']);
        $this->assertContains('world', $result['words_removed']);
        $this->assertContains('foo', $result['words_added']);
    }

    public function testGenerateTextDiffCalculatesLengthChange(): void
    {
        $result = $this->invokePrivateMethod('generateTextDiff', ['short', 'much longer text here']);
        $this->assertSame(mb_strlen('short'), $result['before_length']);
        $this->assertSame(mb_strlen('much longer text here'), $result['after_length']);
        $this->assertSame(
            mb_strlen('much longer text here') - mb_strlen('short'),
            $result['length_change']
        );
    }

    // =========================================================================
    // Behavioral: generateDescriptionDiff
    // =========================================================================

    public function testGenerateDescriptionDiffReturnsExpectedStructure(): void
    {
        $result = $this->invokePrivateMethod('generateDescriptionDiff', ['old desc', 'new description']);
        $this->assertArrayHasKey('before_length', $result);
        $this->assertArrayHasKey('after_length', $result);
        $this->assertArrayHasKey('length_change', $result);
        $this->assertArrayHasKey('length_change_percent', $result);
        $this->assertArrayHasKey('before_preview', $result);
        $this->assertArrayHasKey('after_preview', $result);
        $this->assertArrayHasKey('similarity', $result);
    }

    public function testGenerateDescriptionDiffTruncatesPreview(): void
    {
        $longText = str_repeat('a', 300);
        $result = $this->invokePrivateMethod('generateDescriptionDiff', [$longText, 'short']);
        $this->assertStringEndsWith('...', $result['before_preview']);
        $this->assertLessThanOrEqual(203, mb_strlen($result['before_preview']));
    }

    public function testGenerateDescriptionDiffCalculatesPercent(): void
    {
        $result = $this->invokePrivateMethod('generateDescriptionDiff', ['1234567890', '12345']);
        $this->assertSame(-50.0, $result['length_change_percent']);
    }

    public function testGenerateDescriptionDiffEmptyBefore(): void
    {
        $result = $this->invokePrivateMethod('generateDescriptionDiff', ['', 'new text']);
        $this->assertEquals(100, $result['length_change_percent']);
    }

    // =========================================================================
    // Behavioral: assessTitleRisks
    // =========================================================================

    public function testAssessTitleRisksNoRisks(): void
    {
        $result = $this->invokePrivateMethod('assessTitleRisks', [
            'Bagageiro CG 160 Titan',
            'Bagageiro CG 160 Titan Fan',
        ]);
        $highRisks = array_filter($result, fn($r) => $r['level'] === 'high');
        $this->assertEmpty($highRisks);
    }

    public function testAssessTitleRisksTooLong(): void
    {
        $longTitle = str_repeat('Bagageiro ', 10);
        $result = $this->invokePrivateMethod('assessTitleRisks', [
            'Bagageiro CG',
            $longTitle,
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('title_too_long', $types);
    }

    public function testAssessTitleRisksTooShort(): void
    {
        $result = $this->invokePrivateMethod('assessTitleRisks', [
            'Bagageiro CG 160 Titan para Motos',
            'CG',
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('title_too_short', $types);
    }

    public function testAssessTitleRisksMajorChange(): void
    {
        $result = $this->invokePrivateMethod('assessTitleRisks', [
            'Bagageiro CG 160 Titan Original',
            'Kit Completo de Ferramentas Manuais',
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('major_change', $types);
    }

    public function testAssessTitleRisksLengthReduction(): void
    {
        $result = $this->invokePrivateMethod('assessTitleRisks', [
            'Bagageiro CG 160 Titan Fan Bros XRE 300 Original',
            'Bagageiro CG',
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('length_reduction', $types);
    }

    // =========================================================================
    // Behavioral: assessDescriptionRisks
    // =========================================================================

    public function testAssessDescriptionRisksTooShort(): void
    {
        $result = $this->invokePrivateMethod('assessDescriptionRisks', [
            str_repeat('x', 200),
            'curta',
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('description_too_short', $types);
    }

    public function testAssessDescriptionRisksLengthReduction(): void
    {
        $result = $this->invokePrivateMethod('assessDescriptionRisks', [
            str_repeat('a', 1000),
            str_repeat('b', 200),
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('length_reduction', $types);
    }

    public function testAssessDescriptionRisksMajorIncrease(): void
    {
        $result = $this->invokePrivateMethod('assessDescriptionRisks', [
            str_repeat('a', 100),
            str_repeat('b', 400),
        ]);
        $types = array_column($result, 'type');
        $this->assertContains('major_increase', $types);
    }

    // =========================================================================
    // Behavioral: calculateOverallRisk
    // =========================================================================

    public function testCalculateOverallRiskEmptyRisks(): void
    {
        $result = $this->invokePrivateMethod('calculateOverallRisk', [[]]);
        $this->assertSame('none', $result);
    }

    public function testCalculateOverallRiskHighWins(): void
    {
        $risks = [
            'title' => [
                ['level' => 'low', 'type' => 'foo'],
                ['level' => 'high', 'type' => 'bar'],
            ],
        ];
        $result = $this->invokePrivateMethod('calculateOverallRisk', [$risks]);
        $this->assertSame('high', $result);
    }

    public function testCalculateOverallRiskMediumWinsOverLow(): void
    {
        $risks = [
            'title' => [['level' => 'low', 'type' => 'foo']],
            'description' => [['level' => 'medium', 'type' => 'bar']],
        ];
        $result = $this->invokePrivateMethod('calculateOverallRisk', [$risks]);
        $this->assertSame('medium', $result);
    }

    public function testCalculateOverallRiskOnlyLow(): void
    {
        $risks = [
            'title' => [['level' => 'low', 'type' => 'foo']],
        ];
        $result = $this->invokePrivateMethod('calculateOverallRisk', [$risks]);
        $this->assertSame('low', $result);
    }

    // =========================================================================
    // Behavioral: getRiskSummary
    // =========================================================================

    public function testGetRiskSummaryNoRisks(): void
    {
        $result = $this->invokePrivateMethod('getRiskSummary', [[]]);
        $this->assertSame('Sem riscos detectados', $result);
    }

    public function testGetRiskSummaryWithRisks(): void
    {
        $risks = [
            'title' => [
                ['level' => 'high', 'type' => 'title_too_long', 'message' => 'Titulo excede 70 chars'],
            ],
        ];
        $result = $this->invokePrivateMethod('getRiskSummary', [$risks]);
        $this->assertStringContainsString('Title', $result);
        $this->assertStringContainsString('Titulo excede 70 chars', $result);
    }

    // =========================================================================
    // Behavioral: generateSummaryMessage
    // =========================================================================

    public function testGenerateSummaryMessageEmpty(): void
    {
        $stats = [
            'titles_applied' => 0,
            'descriptions_applied' => 0,
            'no_op' => 0,
            'errors' => 0,
        ];
        $result = $this->invokePrivateMethod('generateSummaryMessage', [$stats]);
        $this->assertStringContainsString('processada', $result);
    }

    public function testGenerateSummaryMessageWithTitles(): void
    {
        $stats = [
            'titles_applied' => 3,
            'descriptions_applied' => 0,
            'no_op' => 1,
            'errors' => 0,
        ];
        $result = $this->invokePrivateMethod('generateSummaryMessage', [$stats]);
        $this->assertStringContainsString('3', $result);
    }

    public function testGenerateSummaryMessageWithErrors(): void
    {
        $stats = [
            'titles_applied' => 2,
            'descriptions_applied' => 1,
            'no_op' => 0,
            'errors' => 3,
        ];
        $result = $this->invokePrivateMethod('generateSummaryMessage', [$stats]);
        $this->assertStringContainsString('3', $result);
    }

    // =========================================================================
    // Behavioral: sanitizeItemIds
    // =========================================================================

    public function testSanitizeItemIdsFiltersEmpty(): void
    {
        $result = $this->invokePrivateMethod('sanitizeItemIds', [['MLB123', '', 'MLB456']]);
        $this->assertSame(['MLB123', 'MLB456'], $result);
    }

    public function testSanitizeItemIdsRemovesDuplicates(): void
    {
        $result = $this->invokePrivateMethod('sanitizeItemIds', [['MLB123', 'MLB123', 'MLB456']]);
        $this->assertSame(['MLB123', 'MLB456'], $result);
    }

    public function testSanitizeItemIdsConvertsToString(): void
    {
        $result = $this->invokePrivateMethod('sanitizeItemIds', [[123, 456]]);
        $this->assertSame(['123', '456'], $result);
    }

    public function testSanitizeItemIdsEmptyArray(): void
    {
        $result = $this->invokePrivateMethod('sanitizeItemIds', [[]]);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Behavioral: interpretMLError
    // =========================================================================

    public function testInterpretMLErrorForbidden(): void
    {
        $result = $this->invokePrivateMethod('interpretMLError', [
            ['error' => 'forbidden'],
        ]);
        $this->assertStringContainsString('permiss', $result);
    }

    public function testInterpretMLErrorNotFound(): void
    {
        $result = $this->invokePrivateMethod('interpretMLError', [
            ['error' => 'not_found'],
        ]);
        $this->assertStringContainsString('encontrado', $result);
    }

    public function testInterpretMLErrorWithCauses(): void
    {
        $result = $this->invokePrivateMethod('interpretMLError', [
            ['cause' => [['message' => 'Title too long']]],
        ]);
        $this->assertStringContainsString('Title too long', $result);
    }

    /**
     * @dataProvider httpStatusErrorProvider
     */
    public function testInterpretMLErrorByStatus(int $status, string $expectedSubstring): void
    {
        $result = $this->invokePrivateMethod('interpretMLError', [
            ['status' => $status],
        ]);
        $this->assertStringContainsString($expectedSubstring, $result);
    }

    public static function httpStatusErrorProvider(): array
    {
        return [
            '400' => [400, 'inv'],
            '401' => [401, 'oken'],
            '403' => [403, 'permiss'],
            '404' => [404, 'encontrado'],
            '429' => [429, 'Limite'],
            '500' => [500, 'tente novamente'],
            '502' => [502, 'tente novamente'],
            '503' => [503, 'tente novamente'],
        ];
    }

    public function testInterpretMLErrorFallback(): void
    {
        $result = $this->invokePrivateMethod('interpretMLError', [
            ['status' => 999],
            'testContext',
        ]);
        $this->assertStringContainsString('testContext', $result);
    }

    // =========================================================================
    // Behavioral: isRetryableError
    // =========================================================================

    /**
     * @dataProvider retryableStatusProvider
     */
    public function testIsRetryableErrorWithRecoverableStatus(int $status): void
    {
        $result = $this->invokePrivateMethod('isRetryableError', [
            ['status' => $status],
        ]);
        $this->assertTrue($result);
    }

    public static function retryableStatusProvider(): array
    {
        return [
            '429' => [429],
            '500' => [500],
            '502' => [502],
            '503' => [503],
            '504' => [504],
        ];
    }

    public function testIsRetryableErrorWithTemporarilyUnavailable(): void
    {
        $result = $this->invokePrivateMethod('isRetryableError', [
            ['error' => 'temporarily_unavailable'],
        ]);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider nonRetryableStatusProvider
     */
    public function testIsRetryableErrorReturnsFalseForNonRecoverable(int $status): void
    {
        $result = $this->invokePrivateMethod('isRetryableError', [
            ['status' => $status],
        ]);
        $this->assertFalse($result);
    }

    public static function nonRetryableStatusProvider(): array
    {
        return [
            '400' => [400],
            '401' => [401],
            '403' => [403],
            '404' => [404],
        ];
    }

    // =========================================================================
    // Behavioral: dryRunBatch validation
    // =========================================================================

    public function testDryRunBatchRejectsEmptyItems(): void
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('dryRunBatch');
        $method->setAccessible(true);

        $result = $method->invokeArgs($instance, [[]]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhum item', $result['error']);
    }

    public function testDryRunBatchRejectsOversizedBatch(): void
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('dryRunBatch');
        $method->setAccessible(true);

        $items = array_map(fn($i) => "MLB{$i}", range(1, 51));
        $result = $method->invokeArgs($instance, [$items]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Limite', $result['error']);
    }

    public function testApplyBatchRejectsEmpty(): void
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('applyBatch');
        $method->setAccessible(true);

        $result = $method->invokeArgs($instance, [[], 1]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhum item', $result['error']);
    }

    public function testApplyBatchRejectsOversizedBatch(): void
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('applyBatch');
        $method->setAccessible(true);

        $items = array_map(fn($i) => ['item_id' => "MLB{$i}"], range(1, 51));
        $result = $method->invokeArgs($instance, [$items, 1]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Limite', $result['error']);
    }

    public function testStartBatchJobRejectsEmpty(): void
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('startBatchJob');
        $method->setAccessible(true);

        $result = $method->invokeArgs($instance, [[], 1]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhum item', $result['error']);
    }

    public function testRollbackBatchRejectsEmpty(): void
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        $method = $this->reflection->getMethod('rollbackBatch');
        $method->setAccessible(true);

        $result = $method->invokeArgs($instance, [[], 1]);
        $this->assertFalse($result['success']);
    }
}
