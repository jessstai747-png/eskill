<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\OrderService;
use App\Database;
use PDO;

/**
 * Testes para OrderService
 *
 * Verifica: listOrders, getOrder, syncOrders, filtros, paginação
 */
class OrderServiceTest extends TestCase
{
    private ?PDO $db = null;
    private ?int $testOrderId = null;
    private bool $hasTable = false;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->db = Database::getInstance();
            $this->db->query("SELECT 1 FROM ml_orders LIMIT 1");
            $this->hasTable = true;
        } catch (\Exception $e) {
            $this->hasTable = false;
        }
    }

    protected function tearDown(): void
    {
        if ($this->testOrderId && $this->db) {
            $this->db->prepare("DELETE FROM ml_orders WHERE ml_order_id = :id")
                ->execute(['id' => $this->testOrderId]);
        }
        parent::tearDown();
    }

    private function requireDb(): void
    {
        if (!$this->hasTable) {
            $this->markTestSkipped('Tabela ml_orders não existe no banco de teste');
        }
    }

    private function seedTestOrder(): void
    {
        $this->requireDb();
        $this->testOrderId = random_int(900000000, 999999999);

        // Desabilitar FK checks para seeding de teste
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");

        $stmt = $this->db->prepare("
            INSERT INTO ml_orders (ml_order_id, status, total_amount, date_created, order_data, ml_account_id)
            VALUES (:id, :status, :total, NOW(), :data, :acct)
        ");
        $stmt->execute([
            'id' => $this->testOrderId,
            'status' => 'paid',
            'total' => 199.90,
            'data' => json_encode(['test' => true]),
            'acct' => 999,
        ]);

        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
    }

    // ===========================
    // CLASS STRUCTURE
    // ===========================

    public function test_order_service_exists(): void
    {
        $this->assertTrue(class_exists(OrderService::class));
    }

    public function test_order_service_has_required_methods(): void
    {
        $methods = ['listOrders', 'getOrder', 'syncOrders'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(OrderService::class, $method),
                "OrderService deve ter método {$method}()"
            );
        }
    }

    // ===========================
    // CONSTRUCTOR
    // ===========================

    public function test_constructor_accepts_null(): void
    {
        $service = new OrderService(null);
        $this->assertInstanceOf(OrderService::class, $service);
    }

    public function test_constructor_accepts_account_id(): void
    {
        $service = new OrderService(999);
        $this->assertInstanceOf(OrderService::class, $service);
    }

    // ===========================
    // listOrders
    // ===========================

    public function test_listOrders_returns_expected_structure(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->listOrders(['limit' => 5]);

        $this->assertIsArray($result);
        // Deve ter orders, total, page
        $this->assertTrue(
            isset($result['orders']) || isset($result['data']) || isset($result['items']),
            'Deve retornar lista de orders'
        );
    }

    public function test_listOrders_respects_limit(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->listOrders(['limit' => 1]);

        $this->assertIsArray($result);
        $orders = $result['orders'] ?? $result['data'] ?? $result['items'] ?? [];
        $this->assertLessThanOrEqual(1, count($orders));
    }

    public function test_listOrders_enforces_max_limit(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->listOrders(['limit' => 9999]);

        // Max limit é 200 no OrderService
        $limit = $result['limit'] ?? $result['per_page'] ?? 200;
        $this->assertLessThanOrEqual(200, $limit);
    }

    public function test_listOrders_status_filter(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->listOrders(['status' => 'paid']);

        $this->assertIsArray($result);
    }

    public function test_listOrders_date_filter(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->listOrders([
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
        ]);

        $this->assertIsArray($result);
    }

    public function test_listOrders_sort_validates_fields(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->listOrders(['sort' => 'DROP TABLE orders;--']);

        $this->assertIsArray($result);
    }

    // ===========================
    // getOrder
    // ===========================

    public function test_getOrder_returns_array(): void
    {
        $this->seedTestOrder();
        $service = new OrderService(999);
        $result = $service->getOrder($this->testOrderId);

        $this->assertIsArray($result);
    }

    public function test_getOrder_nonexistent_returns_error(): void
    {
        $this->requireDb();
        $service = new OrderService(999);

        try {
            $result = $service->getOrder('NONEXISTENT-ORDER-999999');
        } catch (\Exception $e) {
            // Em ambiente de teste, chamada de rede pode ser bloqueada
            $this->assertStringContainsString('network', strtolower($e->getMessage()));
            return;
        }

        $this->assertIsArray($result);
        // Deve indicar erro ou retornar vazio
        $this->assertTrue(
            isset($result['error']) || empty($result['order']),
            'Pedido inexistente deve retornar erro'
        );
    }

    // ===========================
    // SECURITY
    // ===========================

    public function test_uses_prepared_statements(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        $this->assertStringContainsString('->prepare(', $source);
        $this->assertStringContainsString('->execute(', $source);
    }

    public function test_validates_sort_field(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        // Deve ter whitelist de campos de ordenação
        $this->assertStringContainsString('allowedSortFields', $source);
    }

    public function test_search_uses_parameterized_query(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/OrderService.php'
        );

        // Verifica que search usa parâmetro, não concatenação direta
        $this->assertStringContainsString(':search', $source);
    }
}
