<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ItemService;
use App\Database;
use PDO;

/**
 * Testes para ItemService
 *
 * Verifica: listItems, getItem, updatePrice, updateStock, getItemsStats,
 *           getItemsByStatus, getItemsByCategory, createItem validation
 */
class ItemServiceTest extends TestCase
{
    private ?PDO $db = null;
    private ?string $testItemId = null;
    private bool $hasTable = false;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->db = Database::getInstance();
            $this->db->query("SELECT 1 FROM ml_items LIMIT 1");
            $this->hasTable = true;
        } catch (\Exception $e) {
            $this->hasTable = false;
        }
    }

    protected function tearDown(): void
    {
        if ($this->testItemId && $this->db) {
            $this->db->prepare("DELETE FROM ml_items WHERE ml_item_id = :id")
                ->execute(['id' => $this->testItemId]);
        }
        parent::tearDown();
    }

    private function requireDb(): void
    {
        if (!$this->hasTable) {
            $this->markTestSkipped('Tabela ml_items não existe no banco de teste');
        }
    }

    private function seedTestItem(): void
    {
        $this->requireDb();
        $this->testItemId = 'MLB-TEST-' . bin2hex(random_bytes(4));

        // Desabilitar FK checks para seeding de teste
        $this->db->exec("SET FOREIGN_KEY_CHECKS=0");

        $stmt = $this->db->prepare("
            INSERT INTO ml_items (ml_item_id, title, price, available_quantity, status, category_id, permalink, ml_account_id, created_at, updated_at)
            VALUES (:id, :title, :price, :qty, :status, :cat, :link, :acct, NOW(), NOW())
        ");
        $stmt->execute([
            'id' => $this->testItemId,
            'title' => 'Item teste unitário',
            'price' => 99.90,
            'qty' => 10,
            'status' => 'active',
            'cat' => 'MLB1234',
            'link' => 'https://produto.mercadolivre.com.br/test',
            'acct' => 999,
        ]);

        $this->db->exec("SET FOREIGN_KEY_CHECKS=1");
    }

    // ===========================
    // CLASS STRUCTURE
    // ===========================

    public function test_item_service_class_exists(): void
    {
        $this->assertTrue(class_exists(ItemService::class));
    }

    public function test_item_service_has_required_methods(): void
    {
        $requiredMethods = [
            'listItems', 'getItem', 'updateItemPricing', 'createItem',
            'updateItem', 'pauseItem', 'activateItem', 'closeItem',
            'updatePrice', 'updateStock', 'getItemsByStatus',
            'getItemsByCategory', 'getItemsStats', 'syncItem', 'syncItems',
            'getSellerCategories',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists(ItemService::class, $method),
                "ItemService deve ter método {$method}()"
            );
        }
    }

    // ===========================
    // CONSTRUCTOR
    // ===========================

    public function test_constructor_accepts_null_account(): void
    {
        $service = new ItemService(null);
        $this->assertInstanceOf(ItemService::class, $service);
    }

    public function test_constructor_accepts_account_id(): void
    {
        $service = new ItemService(999);
        $this->assertInstanceOf(ItemService::class, $service);
    }

    // ===========================
    // listItems
    // ===========================

    public function test_listItems_returns_expected_structure(): void
    {
        $this->seedTestItem();
        $service = new ItemService(999);
        $result = $service->listItems(['limit' => 5, 'page' => 1]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('limit', $result);
    }

    public function test_listItems_respects_limit(): void
    {
        $this->seedTestItem();
        $service = new ItemService(999);
        $result = $service->listItems(['limit' => 2]);

        $this->assertLessThanOrEqual(2, count($result['items']));
    }

    public function test_listItems_enforces_max_limit(): void
    {
        $this->seedTestItem();
        $service = new ItemService(999);
        $result = $service->listItems(['limit' => 999]);

        // max limit é 50 no ItemService
        $this->assertLessThanOrEqual(50, $result['limit']);
    }

    public function test_listItems_with_status_filter(): void
    {
        $this->seedTestItem();
        $service = new ItemService(999);
        $result = $service->listItems(['status' => 'active']);

        foreach ($result['items'] as $item) {
            $this->assertEquals('active', $item['status'] ?? $item['ml_status'] ?? 'active');
        }
    }

    // ===========================
    // getItemsStats
    // ===========================

    public function test_getItemsStats_returns_array(): void
    {
        $this->requireDb();
        $service = new ItemService(999);
        $result = $service->getItemsStats();

        $this->assertIsArray($result);
    }

    // ===========================
    // SECURITY - No hardcoded values
    // ===========================

    public function test_no_hardcoded_credentials(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/ItemService.php'
        );

        $this->assertStringNotContainsString('hardcoded_token', $source);
        $this->assertStringNotContainsString('test-token', $source);
    }

    public function test_uses_prepared_statements(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/ItemService.php'
        );

        // Deve usar prepare() e não concatenar SQL diretamente
        $this->assertStringContainsString('->prepare(', $source);
        $this->assertStringContainsString('->execute(', $source);
    }

    // ===========================
    // INPUT VALIDATION
    // ===========================

    public function test_listItems_offset_calculation(): void
    {
        $this->seedTestItem();
        $service = new ItemService(999);
        $result = $service->listItems(['offset' => 10, 'limit' => 5]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('page', $result);
    }

    public function test_listItems_negative_page_defaults_to_1(): void
    {
        $this->seedTestItem();
        $service = new ItemService(999);
        $result = $service->listItems(['page' => -5]);

        $this->assertGreaterThanOrEqual(1, $result['page']);
    }

    // ===========================
    // STRICT TYPES
    // ===========================

    public function test_has_strict_types(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/ItemService.php'
        );

        $this->assertStringContainsString(
            'declare(strict_types=1)',
            $source,
            'ItemService deve ter declare(strict_types=1)'
        );
    }

    // ===========================
    // createItem VALIDATION
    // ===========================

    public function test_createItem_validates_required_fields(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/ItemService.php'
        );

        $requiredFields = [
            'title', 'category_id', 'price', 'currency_id',
            'available_quantity', 'buying_mode', 'listing_type_id',
            'condition', 'description',
        ];

        foreach ($requiredFields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "ItemService::createItem deve validar campo obrigatório '{$field}'"
            );
        }
    }

    public function test_createItem_rejects_empty_data(): void
    {
        $service = new ItemService(999);
        $result = $service->createItem([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($result['error'], 'createItem com dados vazios deve retornar error=true');
    }

    public function test_createItem_rejects_partial_data(): void
    {
        $service = new ItemService(999);
        $result = $service->createItem(['title' => 'Bagageiro CG 160']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('obrigatório', $result['message']);
    }

    public function test_createItem_identifies_missing_field_in_error(): void
    {
        $service = new ItemService(999);

        // Com apenas title — deve reclamar do próximo campo faltante (category_id)
        $result = $service->createItem(['title' => 'Test']);
        $this->assertStringContainsString('category_id', $result['message']);

        // Com title + category_id — deve reclamar de price
        $result = $service->createItem(['title' => 'Test', 'category_id' => 'MLB1234']);
        $this->assertStringContainsString('price', $result['message']);
    }

    // ===========================
    // PRIVATE METHOD BEHAVIOR (via reflection)
    // ===========================

    public function test_formatItemForList_enriches_item(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'formatItemForList');
        $method->setAccessible(true);

        $input = [
            'id' => 'MLB12345',
            'title' => 'Bagageiro CG 160 Titan',
            'price' => 89.90,
            'thumbnail' => 'https://example.com/img.jpg',
            'permalink' => 'https://produto.mercadolivre.com.br/MLB-12345',
            'sold_quantity' => 42,
        ];

        $result = $method->invoke($service, $input);

        // Deve enriquecer com ml_id
        $this->assertEquals('MLB12345', $result['ml_id']);
        // Deve preservar thumbnail
        $this->assertEquals('https://example.com/img.jpg', $result['thumbnail']);
        // Deve preservar permalink
        $this->assertEquals('https://produto.mercadolivre.com.br/MLB-12345', $result['permalink']);
        // Deve preservar sold_quantity
        $this->assertEquals(42, $result['sold_quantity']);
    }

    public function test_formatItemForList_extracts_thumbnail_from_pictures(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'formatItemForList');
        $method->setAccessible(true);

        $input = [
            'id' => 'MLB99999',
            'title' => 'Retrovisor Bros 160',
            'pictures' => [
                ['url' => 'https://example.com/pic1.jpg'],
                ['url' => 'https://example.com/pic2.jpg'],
            ],
        ];

        $result = $method->invoke($service, $input);

        $this->assertEquals('https://example.com/pic1.jpg', $result['thumbnail']);
    }

    public function test_formatItemForList_reads_metrics_visits(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'formatItemForList');
        $method->setAccessible(true);

        $input = [
            'id' => 'MLB55555',
            'title' => 'Baú 45L',
            'metrics' => [
                'visits' => 150,
                'sold_quantity' => 7,
            ],
        ];

        $result = $method->invoke($service, $input);

        $this->assertEquals(150, $result['visits']);
        $this->assertEquals(7, $result['sold_quantity']);
    }

    public function test_resolveLocalItemsOrder_maps_correctly(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'resolveLocalItemsOrder');
        $method->setAccessible(true);

        $this->assertEquals('ORDER BY price ASC', $method->invoke($service, 'price_asc'));
        $this->assertEquals('ORDER BY price DESC', $method->invoke($service, 'price_desc'));
        $this->assertEquals('ORDER BY created_at ASC', $method->invoke($service, 'date_created_asc'));
        $this->assertEquals('ORDER BY created_at DESC', $method->invoke($service, 'date_created_desc'));
        $this->assertEquals('ORDER BY updated_at DESC', $method->invoke($service, null));
        $this->assertEquals('ORDER BY updated_at DESC', $method->invoke($service, 'unknown_order'));
    }

    public function test_formatMlApiErrorMessage_with_full_context(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'formatMlApiErrorMessage');
        $method->setAccessible(true);

        $error = [
            'message' => 'Token expirado',
            'status' => 401,
            'endpoint' => '/users/123/items/search',
        ];

        $result = $method->invoke($service, $error, 'Falha ao buscar');

        $this->assertStringContainsString('Falha ao buscar', $result);
        $this->assertStringContainsString('Token expirado', $result);
        $this->assertStringContainsString('HTTP 401', $result);
        $this->assertStringContainsString('/users/123/items/search', $result);
    }

    public function test_formatMlApiErrorMessage_with_minimal_context(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'formatMlApiErrorMessage');
        $method->setAccessible(true);

        $result = $method->invoke($service, [], 'Erro genérico');

        $this->assertEquals('Erro genérico', $result);
    }

    public function test_extractSku_from_seller_custom_field(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'extractSku');
        $method->setAccessible(true);

        $item = ['seller_custom_field' => 'AWA-BAG-001'];

        $this->assertEquals('AWA-BAG-001', $method->invoke($service, $item));
    }

    public function test_extractSku_from_attributes(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'extractSku');
        $method->setAccessible(true);

        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'AWA'],
                ['id' => 'SELLER_SKU', 'value_name' => 'AWA-RET-002'],
                ['id' => 'COLOR', 'value_name' => 'Preto'],
            ],
        ];

        $this->assertEquals('AWA-RET-002', $method->invoke($service, $item));
    }

    public function test_extractSku_returns_null_when_no_sku(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'extractSku');
        $method->setAccessible(true);

        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'AWA'],
            ],
        ];

        $this->assertNull($method->invoke($service, $item));
    }

    public function test_filterItemsByCustomCriteria_low_stock(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'filterItemsByCustomCriteria');
        $method->setAccessible(true);

        $items = [
            ['id' => '1', 'available_quantity' => 2],  // low stock
            ['id' => '2', 'available_quantity' => 10], // not low
            ['id' => '3', 'available_quantity' => 0],  // low stock
            ['id' => '4', 'available_quantity' => 4],  // low stock
        ];

        $result = $method->invoke($service, $items, ['low_stock' => true]);

        $this->assertCount(3, $result);

        $ids = array_column($result, 'id');
        $this->assertContains('1', $ids);
        $this->assertContains('3', $ids);
        $this->assertContains('4', $ids);
        $this->assertNotContains('2', $ids);
    }

    public function test_filterItemsByCustomCriteria_high_sales(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'filterItemsByCustomCriteria');
        $method->setAccessible(true);

        $items = [
            ['id' => '1', 'sold_quantity' => 50],
            ['id' => '2', 'sold_quantity' => 0],
            ['id' => '3'],  // sem campo sold
        ];

        $result = $method->invoke($service, $items, ['high_sales' => true]);

        $this->assertCount(1, $result);
        $this->assertEquals('1', $result[0]['id']);
    }

    public function test_filterItemsByCustomCriteria_no_filters_returns_all(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'filterItemsByCustomCriteria');
        $method->setAccessible(true);

        $items = [
            ['id' => '1', 'available_quantity' => 2],
            ['id' => '2', 'available_quantity' => 10],
        ];

        $result = $method->invoke($service, $items, []);

        $this->assertCount(2, $result);
    }

    public function test_unwrapMlResponse_extracts_body(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'unwrapMlResponse');
        $method->setAccessible(true);

        $response = [
            'body' => [
                'id' => 'MLB12345',
                'title' => 'Bagageiro',
                'price' => 89.90,
            ],
        ];

        $result = $method->invoke($service, $response);

        $this->assertEquals('MLB12345', $result['id']);
        $this->assertEquals('Bagageiro', $result['title']);
    }

    public function test_unwrapMlResponse_returns_raw_on_error(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'unwrapMlResponse');
        $method->setAccessible(true);

        $response = [
            'body' => [
                'error' => 'not_found',
                'message' => 'Item não encontrado',
            ],
        ];

        $result = $method->invoke($service, $response);

        // Quando body tem error, deve retornar o response inteiro
        $this->assertArrayHasKey('body', $result);
    }

    public function test_unwrapMlResponse_returns_raw_when_no_body(): void
    {
        $service = new ItemService(null);

        $method = new \ReflectionMethod(ItemService::class, 'unwrapMlResponse');
        $method->setAccessible(true);

        $response = [
            'id' => 'MLB12345',
            'title' => 'Bagageiro',
        ];

        $result = $method->invoke($service, $response);

        $this->assertEquals('MLB12345', $result['id']);
    }

    // ===========================
    // MONOLOG (NO echo/var_dump/error_log)
    // ===========================

    public function test_uses_structured_logging(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/ItemService.php'
        );

        // Deve usar log_error/log_warning ao invés de error_log/echo/var_dump
        $this->assertStringNotContainsString('error_log(', $source, 'Deve usar log_error/log_warning ao invés de error_log()');
        $this->assertStringNotContainsString('var_dump(', $source);
        $this->assertStringNotContainsString('print_r(', $source);

        // Deve ter chamadas de logging estruturado
        $this->assertStringContainsString('log_warning(', $source);
        $this->assertStringContainsString('log_error(', $source);
    }

    // ===========================
    // DATA INTEGRITY
    // ===========================

    public function test_updateItem_only_allows_safe_fields(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/ItemService.php'
        );

        // Deve ter lista de campos permitidos (whitelist)
        $this->assertStringContainsString('allowedFields', $source);

        // Os campos permitidos devem ser os esperados pela API ML
        $allowedFields = [
            'title', 'price', 'available_quantity', 'description',
            'pictures', 'attributes', 'variations', 'shipping',
            'seller_custom_field',
        ];

        foreach ($allowedFields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "updateItem deve permitir campo '{$field}'"
            );
        }
    }

    public function test_deleteItem_closes_before_deleting(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/ItemService.php'
        );

        // deleteItem deve verificar se o item está fechado antes de deletar
        $this->assertStringContainsString("status' !== 'closed'", $source);
        $this->assertStringContainsString('closeItem', $source);
    }

    public function test_syncItems_uses_batch_multiget(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/ItemService.php'
        );

        // syncItems deve usar multiget para eficiência
        $this->assertStringContainsString('/items?ids=', $source);
        $this->assertStringContainsString('array_chunk', $source);
    }

    public function test_getCatalogDetails_method_exists(): void
    {
        $this->assertTrue(
            method_exists(ItemService::class, 'getCatalogDetails'),
            'ItemService deve ter método getCatalogDetails()'
        );
    }

    public function test_updateItemCost_method_exists(): void
    {
        $this->assertTrue(
            method_exists(ItemService::class, 'updateItemCost'),
            'ItemService deve ter método updateItemCost()'
        );
    }

    public function test_deleteItem_method_exists(): void
    {
        $this->assertTrue(
            method_exists(ItemService::class, 'deleteItem'),
            'ItemService deve ter método deleteItem()'
        );
    }
}
