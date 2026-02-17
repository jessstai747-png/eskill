<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Structural tests for MercadoLivreAIIntegrationService and MLAIIntegrationController.
 *
 * Validates method signatures, parameter types, return types, and class dependencies
 * WITHOUT needing database or API connectivity.
 *
 * @covers \App\Services\MercadoLivre\MercadoLivreAIIntegrationService
 * @covers \App\Controllers\MLAIIntegrationController
 */
class MLAIIntegrationServiceStructureTest extends TestCase
{
    private ReflectionClass $serviceReflection;
    private ReflectionClass $controllerReflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceReflection = new ReflectionClass(
            \App\Services\MercadoLivre\MercadoLivreAIIntegrationService::class
        );
        $this->controllerReflection = new ReflectionClass(
            \App\Controllers\MLAIIntegrationController::class
        );
    }

    // =========================================================================
    // Service — Public Method Existence
    // =========================================================================

    /**
     * @dataProvider servicePublicMethodsProvider
     */
    public function testServiceHasPublicMethod(string $methodName): void
    {
        $this->assertTrue(
            $this->serviceReflection->hasMethod($methodName),
            "Service deve ter o metodo publico {$methodName}()"
        );
        $method = $this->serviceReflection->getMethod($methodName);
        $this->assertTrue($method->isPublic(), "{$methodName}() deve ser public");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function servicePublicMethodsProvider(): array
    {
        return [
            'constructor' => ['__construct'],
            'getHealthStatus' => ['getHealthStatus'],
            'getEnrichedItemData' => ['getEnrichedItemData'],
            'optimizeWithContext' => ['optimizeWithContext'],
            'applyOptimizations' => ['applyOptimizations'],
            'fullPipeline' => ['fullPipeline'],
            'batchPipeline' => ['batchPipeline'],
            'getOptimizationHistory' => ['getOptimizationHistory'],
            'rollbackOptimization' => ['rollbackOptimization'],
            'getOptimizationStats' => ['getOptimizationStats'],
            'compareVersions' => ['compareVersions'],
            'trackImpact' => ['trackImpact'],
            'cleanupSnapshots' => ['cleanupSnapshots'],
            'getItemsForOptimization' => ['getItemsForOptimization'],
            'getItemStatus' => ['getItemStatus'],
            'updateDescription' => ['updateDescription'],
        ];
    }

    // =========================================================================
    // Service — Return Type Declarations
    // =========================================================================

    /**
     * @dataProvider serviceReturnTypesProvider
     */
    public function testServiceMethodHasReturnType(string $methodName, string $expectedType): void
    {
        $method = $this->serviceReflection->getMethod($methodName);
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, "{$methodName}() deve declarar return type");
        $this->assertEquals($expectedType, (string)$returnType, "{$methodName}() return type incorreto");
    }

    /**
     * @return array<int, array{string, string}>
     */
    public static function serviceReturnTypesProvider(): array
    {
        return [
            ['getHealthStatus', 'array'],
            ['getEnrichedItemData', '?array'],
            ['optimizeWithContext', 'array'],
            ['applyOptimizations', 'array'],
            ['fullPipeline', 'array'],
            ['batchPipeline', 'array'],
            ['getOptimizationHistory', 'array'],
            ['rollbackOptimization', 'array'],
            ['getOptimizationStats', 'array'],
            ['compareVersions', 'array'],
            ['trackImpact', 'array'],
            ['cleanupSnapshots', 'array'],
            ['getItemsForOptimization', 'array'],
            ['getItemStatus', '?array'],
            ['updateDescription', 'array'],
        ];
    }

    // =========================================================================
    // Service — Constructor Signature
    // =========================================================================

    public function testConstructorRequiresAccountId(): void
    {
        $constructor = $this->serviceReflection->getConstructor();
        $this->assertNotNull($constructor, 'Deve ter construtor');

        $params = $constructor->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params), 'Construtor deve ter ao menos 1 parametro');
        $this->assertEquals('accountId', $params[0]->getName());
        $this->assertEquals('int', (string)$params[0]->getType());
    }

    // =========================================================================
    // Service — Key Method Parameter Signatures
    // =========================================================================

    public function testCompareVersionsParameterSignature(): void
    {
        $method = $this->serviceReflection->getMethod('compareVersions');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'compareVersions deve ter 2 parametros');
        $this->assertEquals('versionId1', $params[0]->getName());
        $this->assertEquals('int', (string)$params[0]->getType());
        $this->assertEquals('versionId2', $params[1]->getName());
        $this->assertEquals('int', (string)$params[1]->getType());
    }

    public function testTrackImpactParameterSignature(): void
    {
        $method = $this->serviceReflection->getMethod('trackImpact');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'trackImpact deve ter 2 parametros');
        $this->assertEquals('versionId', $params[0]->getName());
        $this->assertEquals('int', (string)$params[0]->getType());
        $this->assertEquals('impactData', $params[1]->getName());
        $this->assertEquals('array', (string)$params[1]->getType());
    }

    public function testCleanupSnapshotsParameterSignature(): void
    {
        $method = $this->serviceReflection->getMethod('cleanupSnapshots');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'cleanupSnapshots deve ter 1 parametro');
        $this->assertEquals('daysToKeep', $params[0]->getName());
        $this->assertEquals('int', (string)$params[0]->getType());
        $this->assertTrue($params[0]->isDefaultValueAvailable(), 'daysToKeep deve ter default');
        $this->assertEquals(90, $params[0]->getDefaultValue());
    }

    public function testOptimizeWithContextAcceptsOptions(): void
    {
        $method = $this->serviceReflection->getMethod('optimizeWithContext');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('itemId', $params[0]->getName());
        $this->assertEquals('string', (string)$params[0]->getType());

        if (count($params) > 1) {
            $this->assertEquals('options', $params[1]->getName());
            $this->assertTrue($params[1]->isDefaultValueAvailable());
        }
    }

    // =========================================================================
    // Service — Dependencies (Properties)
    // =========================================================================

    public function testServiceHasVersioningServiceDependency(): void
    {
        $this->assertTrue(
            $this->serviceReflection->hasProperty('versioningService'),
            'Service deve ter propriedade versioningService'
        );
    }

    public function testServiceHasLoggerDependency(): void
    {
        $this->assertTrue(
            $this->serviceReflection->hasProperty('logger'),
            'Service deve ter propriedade logger'
        );
    }

    public function testServiceHasMlClientDependency(): void
    {
        $this->assertTrue(
            $this->serviceReflection->hasProperty('mlClient'),
            'Service deve ter propriedade mlClient'
        );
    }

    // =========================================================================
    // Service — Uses NormalizesMLItems Trait
    // =========================================================================

    public function testServiceUsesNormalizesMLItemsTrait(): void
    {
        $traits = $this->serviceReflection->getTraitNames();
        $this->assertContains(
            'App\Traits\NormalizesMLItems',
            $traits,
            'Service deve usar trait NormalizesMLItems'
        );
    }

    // =========================================================================
    // Controller — Public Method Existence
    // =========================================================================

    /**
     * @dataProvider controllerPublicMethodsProvider
     */
    public function testControllerHasPublicMethod(string $methodName): void
    {
        $this->assertTrue(
            $this->controllerReflection->hasMethod($methodName),
            "Controller deve ter o metodo publico {$methodName}()"
        );
        $method = $this->controllerReflection->getMethod($methodName);
        $this->assertTrue($method->isPublic(), "{$methodName}() deve ser public");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function controllerPublicMethodsProvider(): array
    {
        return [
            'health' => ['health'],
            'enrich' => ['enrich'],
            'optimize' => ['optimize'],
            'apply' => ['apply'],
            'pipeline' => ['pipeline'],
            'batch' => ['batch'],
            'listItems' => ['listItems'],
            'itemStatus' => ['itemStatus'],
            'updateDescription' => ['updateDescription'],
            'history' => ['history'],
            'rollback' => ['rollback'],
            'stats' => ['stats'],
            'compare' => ['compare'],
            'impact' => ['impact'],
            'cleanup' => ['cleanup'],
        ];
    }

    // =========================================================================
    // Controller — Return Types (void)
    // =========================================================================

    /**
     * @dataProvider controllerPublicMethodsProvider
     */
    public function testControllerMethodsReturnVoid(string $methodName): void
    {
        $method = $this->controllerReflection->getMethod($methodName);
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, "{$methodName}() deve declarar return type");
        $this->assertEquals('void', (string)$returnType, "Controller methods devem retornar void");
    }

    // =========================================================================
    // Controller — Extends BaseController
    // =========================================================================

    public function testControllerExtendsBaseController(): void
    {
        $this->assertTrue(
            $this->controllerReflection->isSubclassOf(\App\Controllers\BaseController::class),
            'MLAIIntegrationController deve estender BaseController'
        );
    }

    // =========================================================================
    // Controller — Impact Method Parameter
    // =========================================================================

    public function testImpactMethodAcceptsVersionId(): void
    {
        $method = $this->controllerReflection->getMethod('impact');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'impact() deve ter 1 parametro');
        $this->assertEquals('versionId', $params[0]->getName());
        $this->assertEquals('string', (string)$params[0]->getType());
    }

    // =========================================================================
    // Service — Method Count (detect accidental removal)
    // =========================================================================

    public function testServiceHasMinimumPublicMethods(): void
    {
        $publicMethods = array_filter(
            $this->serviceReflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn(ReflectionMethod $m) => $m->getDeclaringClass()->getName() === $this->serviceReflection->getName()
        );

        $this->assertGreaterThanOrEqual(
            15,
            count($publicMethods),
            'Service deve ter ao menos 15 metodos publicos (16 incluindo __construct)'
        );
    }

    public function testControllerHasMinimumEndpoints(): void
    {
        $publicMethods = array_filter(
            $this->controllerReflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn(ReflectionMethod $m) => $m->getDeclaringClass()->getName() === $this->controllerReflection->getName()
        );

        $this->assertGreaterThanOrEqual(
            15,
            count($publicMethods),
            'Controller deve ter ao menos 15 endpoints publicos'
        );
    }
}
