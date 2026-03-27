<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AI\Core\AIProviderManager;
use App\Services\MercadoLivre\MercadoLivreAIIntegrationService;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class MercadoLivreAIIntegrationServicePipelineTest extends TestCase
{
    public function testFullPipelineFailsWhenAutoApplyFails(): void
    {
        $service = $this->getMockBuilder(MercadoLivreAIIntegrationService::class)
            ->setConstructorArgs([0])
            ->onlyMethods(['optimizeWithContext', 'applyOptimizations'])
            ->getMock();

        $service->method('optimizeWithContext')->willReturn([
            'success' => true,
            'optimizations' => [
                'title' => ['title' => 'Novo título'],
            ],
        ]);

        $service->method('applyOptimizations')->willReturn([
            'success' => false,
            'errors' => ['Falha ao aplicar no ML'],
        ]);

        $result = $service->fullPipeline('MLB123', [], true);

        $this->assertFalse($result['success']);
        $this->assertSame('apply', $result['step_failed']);
        $this->assertTrue($result['auto_apply']);
        $this->assertSame('Falha ao aplicar no ML', $result['error']);
    }

    public function testFullPipelineSucceedsWhenAutoApplySucceeds(): void
    {
        $service = $this->getMockBuilder(MercadoLivreAIIntegrationService::class)
            ->setConstructorArgs([0])
            ->onlyMethods(['optimizeWithContext', 'applyOptimizations'])
            ->getMock();

        $service->method('optimizeWithContext')->willReturn([
            'success' => true,
            'optimizations' => [
                'title' => ['title' => 'Novo título'],
            ],
        ]);

        $service->method('applyOptimizations')->willReturn([
            'success' => true,
            'applied' => ['title'],
            'errors' => [],
        ]);

        $result = $service->fullPipeline('MLB123', [], true);

        $this->assertTrue($result['success']);
        $this->assertSame('MLB123', $result['item_id']);
        $this->assertTrue($result['auto_apply']);
        $this->assertSame(['title'], $result['applied']['applied']);
    }

    public function testFullPipelineWithoutAutoApplySkipsApplyStep(): void
    {
        $service = $this->getMockBuilder(MercadoLivreAIIntegrationService::class)
            ->setConstructorArgs([0])
            ->onlyMethods(['optimizeWithContext', 'applyOptimizations'])
            ->getMock();

        $service->expects($this->never())->method('applyOptimizations');
        $service->method('optimizeWithContext')->willReturn([
            'success' => true,
            'optimizations' => [
                'description' => ['description' => 'Descrição otimizada'],
            ],
        ]);

        $result = $service->fullPipeline('MLB123', ['optimize_description' => true], false);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['auto_apply']);
        $this->assertNull($result['applied']);
    }

    public function testApplyOptimizationsReturnsExplicitErrorWhenNothingIsApplicable(): void
    {
        $service = new MercadoLivreAIIntegrationService(0);
        $result = $service->applyOptimizations('MLB123', []);

        $this->assertFalse($result['success']);
        $this->assertContains('No valid optimizations generated for apply', $result['errors']);
    }

    public function testOptimizeWithAIFallsBackToTemplateWhenAiReturnsEmptyText(): void
    {
        $service = new MercadoLivreAIIntegrationService(0);
        $aiProviderManager = $this->getMockBuilder(AIProviderManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['chat'])
            ->getMock();

        $aiProviderManager->method('chat')->willReturn(['content' => '']);

        $property = new ReflectionProperty(MercadoLivreAIIntegrationService::class, 'aiProviderManager');
        $property->setAccessible(true);
        $property->setValue($service, $aiProviderManager);

        $method = new ReflectionMethod(MercadoLivreAIIntegrationService::class, 'optimizeWithAI');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            [
                'id' => 'MLB123',
                'title' => 'TÍTULO TOTALMENTE EM CAPS',
                'description' => '',
                'attributes' => [],
                'brand' => 'Honda',
                'model' => 'CG 160',
            ],
            [
                'optimize_title' => true,
                'optimize_description' => true,
                'optimize_attributes' => false,
            ]
        );

        $this->assertSame('template', $result['title']['provider']);
        $this->assertSame('template', $result['description']['provider']);
        $this->assertNotSame('', trim((string)$result['title']['optimized_title']));
        $this->assertNotSame('', trim((string)$result['description']['description']));
    }
}
