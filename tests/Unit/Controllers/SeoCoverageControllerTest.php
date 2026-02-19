<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\SeoCoverageController;

/**
 * Testes estruturais do SeoCoverageController
 *
 * @covers \App\Controllers\SeoCoverageController
 */
class SeoCoverageControllerTest extends TestCase
{
    private static string $sourceCode = '';
    private static \ReflectionClass $reflection;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$reflection = new \ReflectionClass(SeoCoverageController::class);
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
        $this->assertTrue(class_exists(SeoCoverageController::class));
    }

    public function testExtendsBaseController(): void
    {
        $this->assertTrue(self::$reflection->isSubclassOf('App\Controllers\BaseController'));
    }

    public function testIsDeprecated(): void
    {
        $doc = self::$reflection->getDocComment();
        $this->assertNotFalse($doc);
        $this->assertStringContainsString('@deprecated', $doc);
    }

    /**
     * @dataProvider endpointMethodsProvider
     */
    public function testHasEndpoint(string $method): void
    {
        $this->assertTrue(method_exists(SeoCoverageController::class, $method));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointMethodsProvider(): array
    {
        return [
            'detectHiddenFields' => ['detectHiddenFields'],
            'generateHiddenFieldValues' => ['generateHiddenFieldValues'],
            'applyHiddenFields' => ['applyHiddenFields'],
            'analyzeCoverage' => ['analyzeCoverage'],
            'getCoverageGaps' => ['getCoverageGaps'],
            'listCompatibility' => ['listCompatibility'],
        ];
    }

    /**
     * @dataProvider stringParameterMethodsProvider
     */
    public function testMethodAcceptsStringParameter(string $method, string $param): void
    {
        $ref = new \ReflectionMethod(SeoCoverageController::class, $method);
        $params = $ref->getParameters();
        $found = false;
        foreach ($params as $p) {
            if ($p->getName() === $param) {
                $this->assertEquals('string', $p->getType()->getName());
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function stringParameterMethodsProvider(): array
    {
        return [
            'detectHiddenFields(itemId)' => ['detectHiddenFields', 'itemId'],
            'analyzeCoverage(itemId)' => ['analyzeCoverage', 'itemId'],
            'getCoverageGaps(itemId)' => ['getCoverageGaps', 'itemId'],
            'listCompatibility(categoryId)' => ['listCompatibility', 'categoryId'],
        ];
    }

    /**
     * @dataProvider dependenciesProvider
     */
    public function testHasDependency(string $property): void
    {
        $properties = self::$reflection->getProperties(\ReflectionProperty::IS_PRIVATE);
        $names = array_map(fn(\ReflectionProperty $p): string => $p->getName(), $properties);
        $this->assertContains($property, $names);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dependenciesProvider(): array
    {
        return [
            'hiddenDetector' => ['hiddenDetector'],
            'coverageService' => ['coverageService'],
            'compatibilityService' => ['compatibilityService'],
        ];
    }

    public function testUsesHiddenAttributesDetector(): void
    {
        $this->assertStringContainsString('HiddenAttributesDetector', self::$sourceCode);
    }

    public function testUsesSearchCoverageService(): void
    {
        $this->assertStringContainsString('SearchCoverageService', self::$sourceCode);
    }

    public function testUsesCompatibilityService(): void
    {
        $this->assertStringContainsString('CompatibilityService', self::$sourceCode);
    }

    public function testHasJsonResponseHelper(): void
    {
        $this->assertStringContainsString('jsonResponse', self::$sourceCode);
        $this->assertStringContainsString('Content-Type: application/json', self::$sourceCode);
    }

    public function testUsesErrorHandling(): void
    {
        $this->assertStringContainsString('catch', self::$sourceCode);
        $this->assertStringContainsString('success', self::$sourceCode);
    }

    public function testValidatesInput(): void
    {
        $this->assertStringContainsString('Item ID and Fields required', self::$sourceCode);
        $this->assertStringContainsString('Title or Item required', self::$sourceCode);
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
