<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\MercadoLivreAuthService;
use App\Services\UnifiedTokenRefreshService;
use App\Database;

/**
 * Testes do Ciclo de Refresh de Token ML
 *
 * Cobre: refresh automático, expiração, re-autenticação
 *
 * @covers \App\Services\MercadoLivreAuthService
 * @covers \App\Services\UnifiedTokenRefreshService
 */
class TokenRefreshCycleTest extends TestCase
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
        $email = 'tokenrefresh-' . bin2hex(random_bytes(4)) . '@test.local';

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, status, created_at, updated_at)
            VALUES (:name, :email, :password, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            'name' => 'Token Refresh Test User',
            'email' => $email,
            'password' => password_hash('TestPassword123!', PASSWORD_ARGON2ID),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function createTestMlAccount(): int
    {
        // Criar conta ML fake para testes (tokens dummy)
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
            'ml_user_id' => 'TEST_' . bin2hex(random_bytes(4)),
            'nickname' => 'TestSeller',
            'email' => 'testseller@test.local',
            'access_token' => 'TEST_ACCESS_' . bin2hex(random_bytes(16)),
            'refresh_token' => 'TEST_REFRESH_' . bin2hex(random_bytes(16)),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+6 hours')),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function cleanupTestData(): void
    {
        try {
            if ($this->testAccountId) {
                $this->db->prepare('DELETE FROM token_refresh_audit WHERE account_id = :id')
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
    // TOKEN EXPIRATION DETECTION
    // ===========================

    public function testDeveDetectarTokenProximoDeExpirar(): void
    {
        // Atualizar token para expirar em 20 minutos (dentro do buffer de 30 min)
        $this->db->prepare("
            UPDATE ml_accounts SET token_expires_at = DATE_ADD(NOW(), INTERVAL 20 MINUTE)
            WHERE id = :id
        ")->execute(['id' => $this->testAccountId]);

        $service = new MercadoLivreAuthService();
        $needsRefresh = $service->tokenNeedsRefresh($this->testAccountId);

        $this->assertTrue($needsRefresh, 'Token expirando em 20min deve precisar de refresh');
    }

    public function testDeveNaoRefrescarTokenComVidaLonga(): void
    {
        // Token expira em 5 horas — não deve precisar de refresh
        $this->db->prepare("
            UPDATE ml_accounts SET token_expires_at = DATE_ADD(NOW(), INTERVAL 5 HOUR)
            WHERE id = :id
        ")->execute(['id' => $this->testAccountId]);

        $service = new MercadoLivreAuthService();
        $needsRefresh = $service->tokenNeedsRefresh($this->testAccountId);

        $this->assertFalse($needsRefresh, 'Token com 5h de vida não deve precisar de refresh');
    }

    // ===========================
    // UNIFIED REFRESH SERVICE
    // ===========================

    public function testUnifiedServiceDeveExistir(): void
    {
        $this->assertTrue(
            class_exists(UnifiedTokenRefreshService::class),
            'UnifiedTokenRefreshService deve existir'
        );
    }

    public function testUnifiedServiceDeveProcessarFilaDeContas(): void
    {
        $service = new UnifiedTokenRefreshService();

        $this->assertTrue(
            method_exists($service, 'processRefreshQueue') || method_exists($service, 'refreshTokensNearExpiry'),
            'UnifiedTokenRefreshService deve ter método para processar fila'
        );
    }

    // ===========================
    // AUDIT TRAIL
    // ===========================

    public function testDeveGravarAuditDeRefreshAttempt(): void
    {
        $service = new MercadoLivreAuthService();

        // Tentar refresh (vai falhar pois o token é fake)
        $service->refreshToken($this->testAccountId);

        // Verificar que foi gravado audit
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM token_refresh_audit
            WHERE account_id = :account_id AND action LIKE 'refresh%'
        ");
        $stmt->execute(['account_id' => $this->testAccountId]);
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, 'Deve gravar audit de tentativa de refresh');
    }

    // ===========================
    // TOKEN STATUS
    // ===========================

    public function testDeveRetornarStatusDeToken(): void
    {
        $service = new MercadoLivreAuthService();

        $status = $service->getTokenStatus($this->testAccountId);

        $this->assertIsArray($status);
        $this->assertArrayHasKey('expires_at', $status);
        $this->assertArrayHasKey('status', $status);
    }

    public function testDeveCalcularSecondsRemaining(): void
    {
        $service = new MercadoLivreAuthService();

        $status = $service->getTokenStatus($this->testAccountId);

        $this->assertArrayHasKey('seconds_remaining', $status);
        $this->assertIsInt($status['seconds_remaining']);
        $this->assertGreaterThan(0, $status['seconds_remaining']);
    }

    // ===========================
    // WORKER INTEGRATION
    // ===========================

    public function testWorkerScriptDeveExistir(): void
    {
        $workerPath = __DIR__ . '/../../bin/auto-token-refresh-worker.php';
        $this->assertFileExists($workerPath, 'Worker de refresh deve existir em bin/');
    }

    public function testWorkerDeveTerModoOneShot(): void
    {
        // Verificar que o worker pode rodar em modo one-shot (para testes)
        $workerContent = file_get_contents(__DIR__ . '/../../bin/auto-token-refresh-worker.php');

        $hasOneShotMode = (
            strpos($workerContent, '--once') !== false ||
            strpos($workerContent, 'one-shot') !== false ||
            strpos($workerContent, 'single-run') !== false ||
            strpos($workerContent, 'max_iterations') !== false
        );

        // Se não tem modo explícito, verificar se é configurável
        $isConfigurable = strpos($workerContent, 'getenv') !== false;

        $this->assertTrue(
            $hasOneShotMode || $isConfigurable,
            'Worker deve ter modo one-shot ou ser configurável para testes'
        );
    }
}
