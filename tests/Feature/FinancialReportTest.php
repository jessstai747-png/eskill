<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\FinancialService;

/**
 * Testes de relatorios financeiros — Fase 6 (Financeiro).
 *
 * FinancialService nao acessa DB no construtor (lazy-load).
 * E seguro instanciar sem DB; chamadas de metodos falham graciosamente.
 *
 * @covers \App\Services\FinancialService
 */
class FinancialReportTest extends TestCase
{
    private FinancialService $financial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financial = new FinancialService(null);
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testFinancialServiceClassExists(): void
    {
        $this->assertTrue(class_exists(FinancialService::class));
    }

    public function testFinancialServiceCanBeInstantiatedWithoutDb(): void
    {
        $this->assertInstanceOf(FinancialService::class, $this->financial);
    }

    /** @dataProvider financialMethodsProvider */
    public function testFinancialServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(FinancialService::class, $method),
            "FinancialService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function financialMethodsProvider(): array
    {
        return [
            'getPnL'                      => ['getPnL'],
            'getDailyRevenue'             => ['getDailyRevenue'],
            'getCashFlow'                 => ['getCashFlow'],
            'getMetrics'                  => ['getMetrics'],
            'comparePeriods'              => ['comparePeriods'],
            'getDashboardSummary'         => ['getDashboardSummary'],
            'generateReconciliationReport' => ['generateReconciliationReport'],
        ];
    }

    // ------------------------------------------------------------------
    // Instanciacao com diferentes accountIds (sem DB)
    // ------------------------------------------------------------------

    public function testFinancialServiceInstantiationWithNullAccountId(): void
    {
        $service = new FinancialService(null);
        $this->assertInstanceOf(FinancialService::class, $service);
    }

    public function testFinancialServiceInstantiationWithAccountId(): void
    {
        // Construtor nao faz DB; apenas armazena o ID
        $service = new FinancialService(42);
        $this->assertInstanceOf(FinancialService::class, $service);
    }

    // ------------------------------------------------------------------
    // Metodos que devem falhar graciosamente sem DB
    // ------------------------------------------------------------------

    public function testGetPnLThrowsOrReturnsArrayWhenNoDB(): void
    {
        try {
            $result = $this->financial->getPnL('2026-01-01', '2026-01-31');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            // Esperado quando DB indisponivel — nao deve ser silencioso
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testGetDailyRevenueThrowsOrReturnsArrayWhenNoDB(): void
    {
        try {
            $result = $this->financial->getDailyRevenue('2026-01-01', '2026-01-31');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testGetMetricsThrowsOrReturnsArrayWhenNoDB(): void
    {
        try {
            $result = $this->financial->getMetrics('2026-01-01', '2026-01-31');
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testGetDashboardSummaryThrowsOrReturnsArrayWhenNoDB(): void
    {
        try {
            $result = $this->financial->getDashboardSummary();
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Reflecao: assinaturas de metodos criticos
    // ------------------------------------------------------------------

    public function testGetPnLAcceptsDates(): void
    {
        $ref    = new \ReflectionMethod(FinancialService::class, 'getPnL');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'getPnL deve aceitar ao menos 2 parametros (startDate, endDate)');
    }

    public function testComparePeriodsTakesTwoPeriods(): void
    {
        $ref    = new \ReflectionMethod(FinancialService::class, 'comparePeriods');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'comparePeriods deve aceitar ao menos 2 periodos');
    }
}
