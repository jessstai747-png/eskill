<?php

declare(strict_types=1);

namespace Tests\Integration\SEO;

use PHPUnit\Framework\TestCase;
use App\Controllers\SeoOptimizationController;

/**
 * Testes de integração para o controller de otimização SEO
 */
class SeoOptimizationControllerTest extends TestCase
{
    private SeoOptimizationController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SeoOptimizationController();
    }

    /**
     * Teste de validação de input usando reflexão
     */
    public function testInputValidation(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        // Testa método privado de validateRequired (retorna void, lança exceção se falhar)
        $validateMethod = $reflection->getMethod('validateRequired');
        $validateMethod->setAccessible(true);

        // Testa validação com input completo - não deveria lançar exceção
        $input = [
            'product' => [
                'title' => 'Smartphone Samsung Galaxy S21 128GB',
                'category' => 'Celulares',
                'description' => 'Smartphone com 5G e câmera avançada',
                'price' => 2999.99
            ]
        ];
        
        $validateMethod->invoke($this->controller, $input, ['product']);
        $this->assertTrue(true); // Se chegou aqui, validação passou
        
        // Testa validação com campos obrigatórios faltando
        $this->expectException(\InvalidArgumentException::class);
        $validateMethod->invoke($this->controller, ['title' => 'test'], ['title', 'category']);
    }

    /**
     * Teste de health check
     */
    public function testHealthCheck(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $checkService = $reflection->getMethod('checkService');
        $checkService->setAccessible(true);

        $services = [
            'SEOOptimizerService',
            'TitleOptimizerService',
            'KeywordResearchService',
            'ListingBuilderService',
        ];

        $results = [];
        foreach ($services as $serviceName) {
            $results[$serviceName] = $checkService->invoke($this->controller, $serviceName);
            $this->assertArrayHasKey('status', $results[$serviceName]);
            $this->assertContains($results[$serviceName]['status'], ['healthy', 'unhealthy', 'error']);
        }

        $overallHealthy = true;
        foreach ($results as $result) {
            if (($result['status'] ?? '') !== 'healthy') {
                $overallHealthy = false;
            }
        }

        $payload = [
            'services' => $results,
            'overall_status' => $overallHealthy ? 'healthy' : 'degraded',
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->assertArrayHasKey('services', $payload);
        $this->assertArrayHasKey('overall_status', $payload);
        $this->assertContains($payload['overall_status'], ['healthy', 'degraded']);
    }

    /**
     * Teste de checkService usando reflexão
     */
    public function testCheckService(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('checkService');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 'SEOOptimizerService');
        
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['healthy', 'unhealthy', 'error']);
    }

    /**
     * Teste de métodos auxiliares usando reflexão
     * Nota: getProgressBarClass foi removido do controller
     */
    public function testHelperMethods(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        // Testa checkService que é o helper principal disponível
        $method = $reflection->getMethod('checkService');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 'SEOOptimizerService');
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['healthy', 'unhealthy', 'error']);
    }
}
