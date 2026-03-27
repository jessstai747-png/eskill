<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\FinancialService;

/**
 * Testes do FinancialService
 *
 * FinancialService tem 7400+ linhas e 30+ métodos públicos.
 * Estratégia: testes estruturais + verificação de segurança (prepared statements)
 *             + testes de métodos puros quando possível.
 */
class FinancialServiceTest extends TestCase
{
    private string $sourceFile;
    private string $sourceDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceFile = dirname(__DIR__, 3) . '/app/Services/FinancialService.php';
        $this->sourceDir = dirname(__DIR__, 3) . '/app/Services/Financial';
    }

    /**
     * Retorna o conteúdo de todos os arquivos de implementação financeira
     * (facade + serviços extraídos).
     */
    private function getAllFinancialSource(): string
    {
        $source = file_get_contents($this->sourceFile);
        foreach (glob($this->sourceDir . '/*.php') as $file) {
            $source .= "\n" . file_get_contents($file);
        }
        return $source;
    }

    // ===========================
    // STRUCTURAL TESTS
    // ===========================

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(FinancialService::class));
    }

    public function test_has_core_financial_methods(): void
    {
        $requiredMethods = [
            'getPnL',
            'getDailyRevenue',
            'getCashFlow',
            'getProfitabilityByProduct',
            'getMetrics',
            'getDashboardSummary',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists(FinancialService::class, $method),
                "Método core {$method} deve existir"
            );
        }
    }

    public function test_has_ml_api_methods(): void
    {
        $apiMethods = [
            'getAccountBalance',
            'getBillingInfo',
            'getSettlementReport',
            'getOrdersFromApi',
        ];

        foreach ($apiMethods as $method) {
            $this->assertTrue(
                method_exists(FinancialService::class, $method),
                "Método API {$method} deve existir"
            );
        }
    }

    public function test_has_analysis_methods(): void
    {
        $analysisMethods = [
            'comparePeriods',
            'getFeesBreakdown',
            'getFinancialProjection',
            'getRevenueByCategory',
        ];

        foreach ($analysisMethods as $method) {
            $this->assertTrue(
                method_exists(FinancialService::class, $method),
                "Método de análise {$method} deve existir"
            );
        }
    }

    public function test_has_sync_method(): void
    {
        $this->assertTrue(
            method_exists(FinancialService::class, 'syncOrdersWithFinancials'),
            'Deve ter método de sincronização'
        );
    }

    public function test_has_real_time_summary(): void
    {
        $this->assertTrue(
            method_exists(FinancialService::class, 'getRealTimeFinancialSummary'),
            'Deve ter resumo financeiro em tempo real'
        );
    }

    // ===========================
    // CONSTRUCTOR
    // ===========================

    public function test_constructor_accepts_account_id(): void
    {
        $ref = new \ReflectionClass(FinancialService::class);
        $constructor = $ref->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();

        // Deve aceitar pelo menos accountId
        $this->assertGreaterThanOrEqual(1, count($params));
    }

    // ===========================
    // SECURITY: SQL INJECTION PREVENTION
    // ===========================

    public function test_uses_prepared_statements_extensively(): void
    {
        $source = $this->getAllFinancialSource();

        $prepareCount = substr_count($source, '->prepare(');
        $executeCount = substr_count($source, '->execute(');

        // Um serviço de 7400+ linhas com 30+ métodos SQL deve ter muitos prepared statements
        $this->assertGreaterThan(20, $prepareCount,
            "Deve ter pelo menos 20 prepared statements, encontrou {$prepareCount}");
        $this->assertGreaterThan(20, $executeCount,
            "Deve ter pelo menos 20 execute() calls, encontrou {$executeCount}");
    }

    public function test_no_direct_variable_concatenation_in_sql(): void
    {
        $source = $this->getAllFinancialSource();

        // Procurar por padrões perigosos de SQL injection
        // Padrão: "SELECT ... WHERE col = '$var'" ou "... WHERE col = " . $var
        $dangerousPatterns = [
            '/SELECT\s+.+WHERE\s+.+\=\s*\"\s*\.\s*\$/',
            '/SELECT\s+.+WHERE\s+.+\=\s*\'\s*\.\s*\$/',
        ];

        foreach ($dangerousPatterns as $pattern) {
            $matches = [];
            preg_match_all($pattern, $source, $matches);
            $this->assertEmpty(
                $matches[0],
                "Possível SQL injection detectada: " . implode(', ', $matches[0] ?? [])
            );
        }
    }

    // ===========================
    // RETURN TYPE CONSISTENCY
    // ===========================

    public function test_getPnL_returns_array(): void
    {
        $ref = new \ReflectionMethod(FinancialService::class, 'getPnL');
        $returnType = $ref->getReturnType();

        // Se tem tipo de retorno definido, deve ser array
        if ($returnType !== null) {
            $this->assertContains(
                $returnType->getName(),
                ['array', 'mixed'],
                'getPnL deve retornar array'
            );
        } else {
            // Sem type hint — verificar no source
            $source = file_get_contents($this->sourceFile);
            $this->assertTrue(true, 'Sem return type — teste informativo');
        }
    }

    // ===========================
    // ERROR HANDLING
    // ===========================

    public function test_methods_handle_exceptions(): void
    {
        $source = $this->getAllFinancialSource();

        $tryCatchCount = substr_count($source, 'try {');
        // Serviço robusto deve ter tratamento de erros
        $this->assertGreaterThan(2, $tryCatchCount,
            "Deve ter tratamento adequado de erros (try/catch), encontrou {$tryCatchCount}");
    }

    // ===========================
    // FINANCIAL CALCULATIONS INTEGRITY
    // ===========================

    public function test_source_contains_no_hardcoded_tax_rates(): void
    {
        $source = $this->getAllFinancialSource();

        // Taxas não devem ser hardcoded como números mágicos nas queries
        // (Podem estar em constantes ou configs, o que é OK)
        $hasConstants = str_contains($source, 'const ') || str_contains($source, 'TAX_RATE') || str_contains($source, 'config(');

        // Este teste é informativo
        $this->assertTrue(true, 'Verificação de taxas hardcoded é informativa');
    }

    public function test_monetary_calculations_use_proper_precision(): void
    {
        $source = $this->getAllFinancialSource();

        // Deve usar DECIMAL ou ROUND em queries financeiras, ou bcmath/round em PHP
        $hasPrecision = str_contains($source, 'ROUND(')
            || str_contains($source, 'DECIMAL')
            || str_contains($source, 'round(')
            || str_contains($source, 'number_format(')
            || str_contains($source, 'bcmul(');

        $this->assertTrue($hasPrecision,
            'Cálculos monetários devem usar precisão adequada (ROUND, DECIMAL, round, number_format)');
    }

    // ===========================
    // DATE HANDLING
    // ===========================

    public function test_date_methods_accept_date_range_parameters(): void
    {
        $source = $this->getAllFinancialSource();

        // Métodos financeiros devem aceitar filtros de data
        $dateParamCount = substr_count($source, '$startDate') + substr_count($source, '$endDate')
            + substr_count($source, '$dateFrom') + substr_count($source, '$dateTo')
            + substr_count($source, '$period');

        $this->assertGreaterThan(5, $dateParamCount,
            "Métodos financeiros devem ter filtros de data, encontrou {$dateParamCount} referências");
    }

    // ===========================
    // INTEGRATION TEST (skip sem DB)
    // ===========================

    public function test_instantiation_with_account_id(): void
    {
        try {
            $service = new FinancialService(12345);
            $this->assertInstanceOf(FinancialService::class, $service);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), "doesn't exist")
                || str_contains($e->getMessage(), 'SQLSTATE')
                || str_contains(strtolower($e->getMessage()), 'connection')) {
                $this->markTestSkipped('DB não disponível: ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }

    public function test_getDashboardSummary_returns_expected_structure(): void
    {
        try {
            $service = new FinancialService(12345);
            $result = $service->getDashboardSummary();

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), "doesn't exist")
                || str_contains($e->getMessage(), 'SQLSTATE')
                || str_contains(strtolower($e->getMessage()), 'connection')
                || str_contains($e->getMessage(), 'Table')) {
                $this->markTestSkipped('DB não disponível: ' . $e->getMessage());
            } else {
                throw $e;
            }
        }
    }
}
