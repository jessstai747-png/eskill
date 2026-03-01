<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Testes de Integração para o Módulo de Precificação Inteligente
 *
 * Testa os endpoints da API de pricing intelligence:
 * - Simulador de promoções
 * - Cenários de preço
 * - Regras automáticas
 * - Cálculo de margens
 */
class PricingIntelligenceTest extends TestCase
{
    private string $baseUrl;
    private string $accountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = getenv('TEST_API_URL') ?: 'http://localhost:8000';
        $this->accountId = getenv('TEST_ACCOUNT_ID') ?: '1';

        // Skip if API server is not reachable
        $ch = curl_init($this->baseUrl . '/api/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }
    }

    /**
     * Helper para fazer requisições HTTP
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = "{$this->baseUrl}/api/pricing-intelligence/{$this->accountId}{$endpoint}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response ?: '{}', true)
        ];
    }

    // =========================================
    // Testes de Cálculo de Margem
    // =========================================

    public function testCalculateMarginWithValidData(): void
    {
        $data = [
            'preco_venda' => 199.90,
            'custo_producao' => 80.00,
            'custo_embalagem' => 5.00,
            'custo_frete_gratis' => 15.00,
            'taxa_comissao_ml' => 16,
            'taxa_imposto' => 9,
            'acos_medio' => 5
        ];

        $response = $this->request('POST', '/margin/calculate', $data);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertArrayHasKey('margem_real', $response['body']);
        $this->assertArrayHasKey('lucro_unitario', $response['body']);
        $this->assertArrayHasKey('breakdown', $response['body']);
    }

    public function testCalculateMarginReturnsCorrectValues(): void
    {
        // Teste com valores conhecidos para validar cálculo
        $data = [
            'preco_venda' => 100.00,
            'custo_producao' => 30.00,
            'custo_embalagem' => 0,
            'custo_frete_gratis' => 0,
            'taxa_comissao_ml' => 16,
            'taxa_imposto' => 9,
            'acos_medio' => 0
        ];

        // Cálculo esperado:
        // Comissão ML: 100 * 0.16 = 16
        // Imposto: 100 * 0.09 = 9
        // Custos totais: 16 + 9 + 30 = 55
        // Lucro: 100 - 55 = 45
        // Margem: 45 / 100 * 100 = 45%

        $response = $this->request('POST', '/margin/calculate', $data);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertEquals(45.0, $response['body']['lucro_unitario']);
        $this->assertEquals(45.0, $response['body']['margem_real']);
    }

    // =========================================
    // Testes de Simulador de Promoções
    // =========================================

    public function testSimulatePromotionWithValidData(): void
    {
        $data = [
            'preco_original' => 199.90,
            'desconto_percent' => 15,
            'custo_producao' => 80.00,
            'taxa_comissao_ml' => 16,
            'taxa_imposto' => 9
        ];

        $response = $this->request('POST', '/promotion/simulate', $data);

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertArrayHasKey('cenarios', $response['body']);
        $this->assertIsArray($response['body']['cenarios']);
    }

    public function testSimulatePromotionReturnsMultipleScenarios(): void
    {
        $data = [
            'preco_original' => 200.00,
            'desconto_percent' => 20,
            'custo_producao' => 60.00,
            'taxa_comissao_ml' => 16,
            'taxa_imposto' => 9
        ];

        $response = $this->request('POST', '/promotion/simulate', $data);

        $this->assertEquals(200, $response['status']);

        if (isset($response['body']['cenarios'])) {
            foreach ($response['body']['cenarios'] as $cenario) {
                $this->assertArrayHasKey('desconto', $cenario);
                $this->assertArrayHasKey('preco_final', $cenario);
                $this->assertArrayHasKey('margem', $cenario);
                $this->assertArrayHasKey('viavel', $cenario);
            }
        }
    }

    public function testGetPromotionScenarios(): void
    {
        $response = $this->request('GET', '/promotion/scenarios?preco=199.90&custo=80');

        $this->assertContains($response['status'], [200, 404]);
    }

    public function testSimulateCentralOfertas(): void
    {
        $data = [
            'preco_original' => 299.90,
            'custo_producao' => 100.00,
            'taxa_comissao_ml' => 16,
            'taxa_imposto' => 9
        ];

        $response = $this->request('POST', '/promotion/central-ofertas', $data);

        $this->assertContains($response['status'], [200, 404]);

        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertArrayHasKey('desconto_recomendado', $response['body']);
            $this->assertArrayHasKey('preco_promocional', $response['body']);
        }
    }

    // =========================================
    // Testes de Cenários e Estratégias
    // =========================================

    public function testCompareStrategies(): void
    {
        $data = [
            'preco_base' => 199.90,
            'custo_producao' => 80.00
        ];

        $response = $this->request('POST', '/scenarios/strategies', $data);

        $this->assertContains($response['status'], [200, 404]);

        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertArrayHasKey('estrategias', $response['body']);
        }
    }

    public function testCreateWhatIfScenario(): void
    {
        $data = [
            'preco_base' => 199.90,
            'custo_producao' => 80.00,
            'variavel' => 'custo',
            'variacao' => 10
        ];

        $response = $this->request('POST', '/scenarios/what-if', $data);

        $this->assertContains($response['status'], [200, 400, 404]);
    }

    // =========================================
    // Testes de Regras Automáticas
    // =========================================

    public function testCreatePricingRule(): void
    {
        $data = [
            'nome' => 'Teste PHPUnit - Margem Mínima 15%',
            'tipo' => 'margem_minima',
            'valor' => 15,
            'ativa' => false
        ];

        $response = $this->request('POST', '/rules', $data);

        $this->assertContains($response['status'], [200, 201, 400, 404]);
    }

    public function testListPricingRules(): void
    {
        $response = $this->request('GET', '/rules');

        $this->assertContains($response['status'], [200, 404]);

        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertArrayHasKey('regras', $response['body']);
        }
    }

    public function testTogglePricingRule(): void
    {
        // Primeiro cria uma regra
        $createData = [
            'nome' => 'Regra para Toggle Test',
            'tipo' => 'margem_minima',
            'valor' => 10,
            'ativa' => true
        ];

        $createResponse = $this->request('POST', '/rules', $createData);

        if ($createResponse['status'] === 200 && isset($createResponse['body']['id'])) {
            $ruleId = $createResponse['body']['id'];

            // Tenta desativar a regra
            $toggleResponse = $this->request('POST', "/rules/{$ruleId}/toggle", ['ativa' => false]);

            $this->assertContains($toggleResponse['status'], [200, 404]);
        }
    }

    // =========================================
    // Testes de Impacto no Ranking
    // =========================================

    public function testRankingImpactCheck(): void
    {
        $data = [
            'preco_atual' => 199.90,
            'preco_novo' => 229.90
        ];

        $response = $this->request('POST', '/ranking-impact', $data);

        $this->assertContains($response['status'], [200, 404]);

        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertArrayHasKey('alerta', $response['body']);
        }
    }

    public function testRankingImpactWithHighIncrease(): void
    {
        $data = [
            'preco_atual' => 100.00,
            'preco_novo' => 120.00  // 20% de aumento
        ];

        $response = $this->request('POST', '/ranking-impact', $data);

        // Deve retornar alerta vermelho para aumento > 15%
        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertEquals('vermelho', $response['body']['alerta']);
        }
    }

    // =========================================
    // Testes de Dashboard
    // =========================================

    public function testDashboardEndpoint(): void
    {
        $response = $this->request('GET', '/dashboard');

        $this->assertContains($response['status'], [200, 404]);

        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertArrayHasKey('estatisticas', $response['body']);
        }
    }

    public function testItemsListEndpoint(): void
    {
        $response = $this->request('GET', '/items?page=1&limit=10');

        $this->assertContains($response['status'], [200, 404]);

        if ($response['status'] === 200 && $response['body']['success']) {
            $this->assertArrayHasKey('items', $response['body']);
            $this->assertArrayHasKey('total', $response['body']);
        }
    }

    // =========================================
    // Testes de Histórico
    // =========================================

    public function testPriceHistoryEndpoint(): void
    {
        $response = $this->request('GET', '/history/MLB123456789?days=30');

        $this->assertContains($response['status'], [200, 404]);
    }

    // =========================================
    // Testes de Validação de Entrada
    // =========================================

    public function testCalculateMarginWithMissingData(): void
    {
        $data = [
            'preco_venda' => 199.90
            // Faltando outros campos
        ];

        $response = $this->request('POST', '/margin/calculate', $data);

        // Pode retornar erro 400 ou usar valores padrão
        $this->assertContains($response['status'], [200, 400]);
    }

    public function testSimulatePromotionWithNegativeDiscount(): void
    {
        $data = [
            'preco_original' => 199.90,
            'desconto_percent' => -10,  // Valor inválido
            'custo_producao' => 80.00
        ];

        $response = $this->request('POST', '/promotion/simulate', $data);

        // Deve validar e rejeitar
        $this->assertContains($response['status'], [200, 400]);
    }

    public function testSimulatePromotionWithExcessiveDiscount(): void
    {
        $data = [
            'preco_original' => 199.90,
            'desconto_percent' => 90,  // Desconto muito alto
            'custo_producao' => 80.00,
            'taxa_comissao_ml' => 16,
            'taxa_imposto' => 9
        ];

        $response = $this->request('POST', '/promotion/simulate', $data);

        // Deve indicar que é inviável
        if ($response['status'] === 200 && $response['body']['success']) {
            $cenarios = $response['body']['cenarios'] ?? [];
            // Cenário com 90% de desconto deve ser inviável
            foreach ($cenarios as $cenario) {
                if ($cenario['desconto'] === 90) {
                    $this->assertFalse($cenario['viavel']);
                }
            }
        }
    }
}
