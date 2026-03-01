<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\DashboardService;
use App\Database;

/**
 * Testes do Dashboard com Dados Reais
 *
 * Cobre: métricas do dashboard, integração com ML, fallback degradado
 *
 * @covers \App\Services\DashboardService
 */
class DashboardRealDataTest extends TestCase
{
    private \PDO $db;
    private int $testUserId;
    private ?int $testAccountId = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::getInstance();
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB unavailable: ' . $e->getMessage());
            return;
        }

        $this->testUserId = $this->createTestUser();
        $this->testAccountId = $this->createTestMlAccount();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function createTestUser(): int
    {
        $email = 'dashboard-' . bin2hex(random_bytes(4)) . '@test.local';

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, status, created_at, updated_at)
            VALUES (:name, :email, :password, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            'name' => 'Dashboard Test User',
            'email' => $email,
            'password' => password_hash('TestPassword123!', PASSWORD_ARGON2ID),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function createTestMlAccount(): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ml_accounts (
                user_id, ml_user_id, nickname, email, site_id,
                access_token, refresh_token, token_expires_at, status,
                created_at, updated_at
            ) VALUES (
                :user_id, :ml_user_id, :nickname, :email, 'MLB',
                :access_token, :refresh_token, :expires_at, 'active',
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            'user_id' => $this->testUserId,
            'ml_user_id' => 'DASH_TEST_' . bin2hex(random_bytes(4)),
            'nickname' => 'DashboardTestSeller',
            'email' => 'dashseller@test.local',
            'access_token' => 'DASH_ACCESS_' . bin2hex(random_bytes(16)),
            'refresh_token' => 'DASH_REFRESH_' . bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+6 hours')),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function cleanupTestData(): void
    {
        try {
            if ($this->testAccountId) {
                $this->db->prepare('DELETE FROM ml_orders WHERE ml_account_id = :id')
                    ->execute(['id' => $this->testAccountId]);
                $this->db->prepare('DELETE FROM ml_accounts WHERE id = :id')
                    ->execute(['id' => $this->testAccountId]);
            }
            if ($this->testUserId) {
                $this->db->prepare('DELETE FROM users WHERE id = :id')
                    ->execute(['id' => $this->testUserId]);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // ===========================
    // METRICS STRUCTURE TESTS
    // ===========================

    public function testDeveRetornarMetricasComEstruturaCorreta(): void
    {
        $service = new DashboardService();

        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('recent_orders_count', $metrics);
        $this->assertArrayHasKey('total_revenue', $metrics);
        $this->assertArrayHasKey('total_items', $metrics);
        $this->assertArrayHasKey('active_items', $metrics);
    }

    public function testDeveRetornarSalesOverTimeComoArray(): void
    {
        $service = new DashboardService();

        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertArrayHasKey('sales_over_time', $metrics);
        $this->assertIsArray($metrics['sales_over_time']);
    }

    public function testDeveRetornarOrdersByStatusComoArray(): void
    {
        $service = new DashboardService();

        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertArrayHasKey('orders_by_status', $metrics);
        $this->assertIsArray($metrics['orders_by_status']);
    }

    // ===========================
    // METRICS VALUES TESTS
    // ===========================

    public function testMetricasDevemSerNumericas(): void
    {
        $service = new DashboardService();

        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertIsInt($metrics['recent_orders_count']);
        $this->assertIsFloat($metrics['total_revenue']);
        $this->assertIsInt($metrics['total_items']);
        $this->assertIsInt($metrics['active_items']);
    }

    public function testMetricasNaoDevemSerNegativas(): void
    {
        $service = new DashboardService();

        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertGreaterThanOrEqual(0, $metrics['recent_orders_count']);
        $this->assertGreaterThanOrEqual(0, $metrics['total_revenue']);
        $this->assertGreaterThanOrEqual(0, $metrics['total_items']);
        $this->assertGreaterThanOrEqual(0, $metrics['active_items']);
    }

    // ===========================
    // ACCOUNT ISOLATION TESTS
    // ===========================

    public function testDeveRetornarMetricasFiltadasPorConta(): void
    {
        // Criar order de teste para esta conta
        $this->createTestOrder();

        $service = new DashboardService();

        // Métricas para conta específica
        $metricsForAccount = $service->getMetrics($this->testAccountId);

        // Métricas sem filtro de conta
        $metricsAll = $service->getMetrics(null);

        // A conta específica deve ter pelo menos 1 order
        $this->assertGreaterThanOrEqual(1, $metricsForAccount['recent_orders_count']);

        // Métricas gerais devem incluir orders dessa conta
        $this->assertGreaterThanOrEqual(
            $metricsForAccount['recent_orders_count'],
            $metricsAll['recent_orders_count']
        );
    }

    private function createTestOrder(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ml_orders (
                ml_account_id, ml_order_id, status, total_amount, date_created, created_at
            ) VALUES (
                :account_id, :order_id, 'paid', 199.90, NOW(), NOW()
            )
        ");

        $stmt->execute([
            'account_id' => $this->testAccountId,
            'order_id' => 'TEST_ORDER_' . bin2hex(random_bytes(4)),
        ]);
    }

    // ===========================
    // DEGRADED MODE TESTS
    // ===========================

    public function testDeveTerMetodoBuildDegradedMetrics(): void
    {
        $service = new DashboardService();

        // Usar reflection para verificar método privado
        $reflection = new \ReflectionClass($service);

        $this->assertTrue(
            $reflection->hasMethod('buildDegradedMetrics'),
            'DashboardService deve ter método buildDegradedMetrics()'
        );
    }

    // ===========================
    // EXPIRING TOKENS METRIC
    // ===========================

    public function testDeveRetornarTokensExpirando(): void
    {
        // Atualizar token para expirar em 1 dia (dentro dos 3 dias de alerta)
        $this->db->prepare("
            UPDATE ml_accounts SET token_expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY)
            WHERE id = :id
        ")->execute(['id' => $this->testAccountId]);

        $service = new DashboardService();
        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertArrayHasKey('expiring_tokens', $metrics);
        $this->assertIsInt($metrics['expiring_tokens']);
        $this->assertGreaterThanOrEqual(1, $metrics['expiring_tokens']);
    }

    // ===========================
    // PENDING QUESTIONS METRIC
    // ===========================

    public function testDeveRetornarPerguntasPendentes(): void
    {
        $service = new DashboardService();
        $metrics = $service->getMetrics($this->testAccountId);

        $this->assertArrayHasKey('pending_questions', $metrics);
        $this->assertIsInt($metrics['pending_questions']);
    }
}
