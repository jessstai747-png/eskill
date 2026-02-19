<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\GapHunterService;

/**
 * Testes estruturais do GapHunterService
 *
 * @covers \App\Services\GapHunterService
 */
class GapHunterServiceTest extends TestCase
{
    private static string $sourceCode = '';
    private static \ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$reflection = new \ReflectionClass(GapHunterService::class);
        self::$sourceCode = (string) file_get_contents((string) self::$reflection->getFileName());
    }

    public function testHasStrictTypesDeclaration(): void
    {
        $this->assertMatchesRegularExpression(
            '/declare\s*\(\s*strict_types\s*=\s*1\s*\)/',
            self::$sourceCode
        );
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GapHunterService::class));
    }

    /**
     * @dataProvider publicMethodsProvider
     */
    public function testHasPublicMethod(string $method): void
    {
        $this->assertTrue(method_exists(GapHunterService::class, $method));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicMethodsProvider(): array
    {
        return [
            'analyzeCategory' => ['analyzeCategory'],
            'getHistory' => ['getHistory'],
        ];
    }

    public function testAnalyzeCategorySignature(): void
    {
        $ref = new \ReflectionMethod(GapHunterService::class, 'analyzeCategory');
        $params = $ref->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('categoryId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testGetHistorySignature(): void
    {
        $ref = new \ReflectionMethod(GapHunterService::class, 'getHistory');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
        $this->assertEquals('limit', $params[1]->getName());
        $this->assertEquals(50, $params[1]->getDefaultValue());
    }

    public function testConstructorHasNullableAccountId(): void
    {
        $ref = new \ReflectionMethod(GapHunterService::class, '__construct');
        $params = $ref->getParameters();
        $this->assertNotEmpty($params);
        $this->assertTrue($params[0]->isDefaultValueAvailable());
    }

    public function testUsesMercadoLivreClient(): void
    {
        $this->assertStringContainsString('MercadoLivreClient', self::$sourceCode);
    }

    public function testUsesDatabase(): void
    {
        $this->assertStringContainsString('Database::getInstance', self::$sourceCode);
    }

    public function testUsesStructuredLogService(): void
    {
        $this->assertStringContainsString('StructuredLogService', self::$sourceCode);
    }

    public function testUsesPreparedStatements(): void
    {
        $this->assertStringContainsString('prepare(', self::$sourceCode);
        $this->assertStringContainsString('execute(', self::$sourceCode);
    }

    public function testUsesProperErrorHandling(): void
    {
        $this->assertStringContainsString('logger->error', self::$sourceCode);
        $this->assertStringContainsString('logger->warning', self::$sourceCode);
    }

    public function testPersistsGapSnapshots(): void
    {
        $this->assertStringContainsString('gap_trend_snapshots', self::$sourceCode);
    }

    public function testUsesSiteId(): void
    {
        $this->assertStringContainsString('siteId', self::$sourceCode);
    }

    public function testNoErrorLogUsage(): void
    {
        $this->assertStringNotContainsString('error_log(', self::$sourceCode);
    }

    public function testNoVarDumpUsage(): void
    {
        $this->assertStringNotContainsString('var_dump(', self::$sourceCode);
    }
}
