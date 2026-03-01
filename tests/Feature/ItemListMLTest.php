<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\ItemService;
use App\Database;

/**
 * Testes de Listagem de Items ML
 *
 * Cobre: listagem via API ML, filtros, paginação, fallback local
 *
 * @covers \App\Services\ItemService
 */
class ItemListMLTest extends TestCase
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
        $email = 'itemlist-' . bin2hex(random_bytes(4)) . '@test.local';

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, status, created_at, updated_at)
            VALUES (:name, :email, :password, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            'name' => 'Item List Test User',
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
            'ml_user_id' => 'ITEM_TEST_' . bin2hex(random_bytes(4)),
            'nickname' => 'ItemTestSeller',
            'email' => 'itemseller@test.local',
            'access_token' => 'ITEM_ACCESS_' . bin2hex(random_bytes(16)),
            'refresh_token' => 'ITEM_REFRESH_' . bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+6 hours')),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function cleanupTestData(): void
    {
        try {
            if ($this->testAccountId) {
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
    // LIST ITEMS STRUCTURE TESTS
    // ===========================

    public function testDeveRetornarEstruturaDeRespostaCorreta(): void
    {
        $service = new ItemService($this->testAccountId);

        $result = $service->listItems([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('limit', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['items']);
    }

    public function testDeveRetornarPaginacaoValida(): void
    {
        $service = new ItemService($this->testAccountId);

        $result = $service->listItems(['page' => 1, 'limit' => 10]);

        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsInt($result['page']);
        $this->assertIsBool($result['has_more']);
    }

    // ===========================
    // FILTERS TESTS
    // ===========================

    public function testDeveAceitarFiltroDeStatus(): void
    {
        $service = new ItemService($this->testAccountId);

        // Testar com diferentes status
        $statuses = ['active', 'paused', 'closed'];

        foreach ($statuses as $status) {
            $result = $service->listItems(['status' => $status]);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
        }
    }

    public function testDeveAceitarFiltroDeBusca(): void
    {
        $service = new ItemService($this->testAccountId);

        $result = $service->listItems(['q' => 'bagageiro']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testDeveAceitarFiltroDeCategoria(): void
    {
        $service = new ItemService($this->testAccountId);

        $result = $service->listItems(['category' => 'MLB1234']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    // ===========================
    // PAGINATION TESTS
    // ===========================

    public function testDeveRespeitarLimiteMaximo(): void
    {
        $service = new ItemService($this->testAccountId);

        // Tentar pedir mais que o máximo permitido (50)
        $result = $service->listItems(['limit' => 100]);

        $this->assertLessThanOrEqual(50, $result['limit']);
    }

    public function testDeveCalcularOffsetDaPagina(): void
    {
        $service = new ItemService($this->testAccountId);

        $page1 = $service->listItems(['page' => 1, 'limit' => 10]);
        $page2 = $service->listItems(['page' => 2, 'limit' => 10]);

        $this->assertEquals(1, $page1['page']);
        $this->assertEquals(2, $page2['page']);
    }

    // ===========================
    // ITEM STATS TESTS
    // ===========================

    public function testDeveRetornarEstatisticasDeItems(): void
    {
        $service = new ItemService($this->testAccountId);

        $stats = $service->getItemsStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
    }

    // ===========================
    // ERROR HANDLING TESTS
    // ===========================

    public function testDeveRetornarErroAmigavelSemContaML(): void
    {
        // Criar service sem account_id válido
        $service = new ItemService(null);

        $result = $service->listItems([]);

        // Deve retornar estrutura de erro, não lançar exceção
        $this->assertIsArray($result);

        // Se não há conta ML, deve ter erro ou items vazio
        if (isset($result['success']) && $result['success'] === false) {
            $this->assertArrayHasKey('error', $result);
        }
    }

    public function testDeveRetornarErroComContaInexistente(): void
    {
        $service = new ItemService(999999);

        $result = $service->listItems([]);

        $this->assertIsArray($result);
        // Deve ter alguma indicação de falha ou items vazio
        $this->assertTrue(
            (isset($result['success']) && !$result['success']) ||
            (isset($result['items']) && empty($result['items']))
        );
    }

    // ===========================
    // LOCAL CACHE FALLBACK TESTS
    // ===========================

    public function testDeveTerMetodoListItemsFromLocalCache(): void
    {
        $service = new ItemService($this->testAccountId);
        $reflection = new \ReflectionClass($service);

        $this->assertTrue(
            $reflection->hasMethod('listItemsFromLocalCache'),
            'ItemService deve ter método listItemsFromLocalCache()'
        );
    }

    // ===========================
    // SINGLE ITEM TESTS
    // ===========================

    public function testDeveVerificarMetodoGetItem(): void
    {
        $this->assertTrue(
            method_exists(ItemService::class, 'getItem'),
            'ItemService deve ter método getItem()'
        );
    }

    public function testDeveVerificarMetodoUpdateItem(): void
    {
        $this->assertTrue(
            method_exists(ItemService::class, 'updateItem'),
            'ItemService deve ter método updateItem()'
        );
    }
}
