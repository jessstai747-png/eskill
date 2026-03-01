<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\MercadoLivreAuthService;
use App\Database;

/**
 * Testes do Fluxo OAuth Mercado Livre
 *
 * Cobre: geração de URL de autorização, troca de código, armazenamento de tokens
 *
 * @covers \App\Services\MercadoLivreAuthService
 */
class MLOAuthFlowTest extends TestCase
{
    private \PDO $db;
    private int $testUserId;

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

        // Iniciar sessão para testes
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $_SESSION = [];
        parent::tearDown();
    }

    private function createTestUser(): int
    {
        $email = 'mloauth-' . bin2hex(random_bytes(4)) . '@test.local';

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, status, created_at, updated_at)
            VALUES (:name, :email, :password, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            'name' => 'ML OAuth Test User',
            'email' => $email,
            'password' => password_hash('TestPassword123!', PASSWORD_ARGON2ID),
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function cleanupTestData(): void
    {
        try {
            $this->db->prepare('DELETE FROM ml_accounts WHERE user_id = :id')
                ->execute(['id' => $this->testUserId]);
            $this->db->prepare('DELETE FROM users WHERE id = :id')
                ->execute(['id' => $this->testUserId]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // ===========================
    // AUTH URL GENERATION TESTS
    // ===========================

    public function testDeveGerarUrlDeAutorizacaoComStateSeguro(): void
    {
        $service = new MercadoLivreAuthService();

        $url = $service->getAuthUrl($this->testUserId);

        $this->assertTrue(
            str_starts_with($url, 'https://auth.mercadolibre.com')
            || str_starts_with($url, 'https://auth.mercadolivre.com.br'),
            'URL de autorização deve apontar para domínio oficial do Mercado Livre'
        );
        $this->assertStringContainsString('state=', $url);
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('client_id=', $url);
    }

    public function testDeveArmazenarStateNaSessao(): void
    {
        $service = new MercadoLivreAuthService();

        $service->getAuthUrl($this->testUserId);

        $this->assertArrayHasKey('ml_oauth_state', $_SESSION);
        $this->assertStringContainsString((string)$this->testUserId . ':', $_SESSION['ml_oauth_state']);
    }

    public function testDeveArmazenarCodeVerifierParaPKCE(): void
    {
        $service = new MercadoLivreAuthService();

        $service->getAuthUrl($this->testUserId);

        $this->assertArrayHasKey('ml_oauth_pkce', $_SESSION);
        $this->assertIsArray($_SESSION['ml_oauth_pkce']);
        $this->assertNotEmpty($_SESSION['ml_oauth_pkce']);
    }

    // ===========================
    // STATE VALIDATION TESTS
    // ===========================

    public function testDeveLancarExcecaoQuandoStateInvalido(): void
    {
        $service = new MercadoLivreAuthService();

        // Gerar URL para criar state válido
        $service->getAuthUrl($this->testUserId);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Estado OAuth inválido');

        // Tentar trocar código com state errado
        $service->exchangeCodeForTokens('fake-code', 'state-invalido');
    }

    public function testDeveLancarExcecaoQuandoCodeVerifierAusente(): void
    {
        $service = new MercadoLivreAuthService();

        // Criar state manualmente sem PKCE
        $fakeState = $this->testUserId . ':' . bin2hex(random_bytes(16));
        $_SESSION['ml_oauth_state'] = $fakeState;
        $_SESSION['ml_oauth_pkce'] = []; // PKCE vazio

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('code_verifier ausente');

        $service->exchangeCodeForTokens('fake-code', $fakeState);
    }

    // ===========================
    // ACCOUNT STORAGE TESTS
    // ===========================

    public function testDeveVerificarEstruturaDaTabelaMlAccounts(): void
    {
        $stmt = $this->db->query("DESCRIBE ml_accounts");
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $requiredColumns = [
            'id', 'user_id', 'ml_user_id', 'nickname', 'email',
            'access_token', 'refresh_token', 'token_expires_at', 'status'
        ];

        foreach ($requiredColumns as $col) {
            $this->assertContains($col, $columns, "Coluna {$col} deve existir em ml_accounts");
        }
    }

    // ===========================
    // TOKEN REFRESH FLOW TESTS
    // ===========================

    public function testDeveVerificarMetodoRefreshTokenExiste(): void
    {
        $service = new MercadoLivreAuthService();

        $this->assertTrue(
            method_exists($service, 'refreshToken'),
            'MercadoLivreAuthService deve ter método refreshToken()'
        );
    }

    public function testDeveVerificarMetodoEnsureValidTokenExiste(): void
    {
        $service = new MercadoLivreAuthService();

        $this->assertTrue(
            method_exists($service, 'ensureValidToken'),
            'MercadoLivreAuthService deve ter método ensureValidToken()'
        );
    }

    public function testRefreshTokenDeveFalharSemContaCadastrada(): void
    {
        $service = new MercadoLivreAuthService();

        // ID de conta inexistente
        $result = $service->refreshToken(999999);

        $this->assertFalse($result, 'refreshToken deve retornar false para conta inexistente');
    }

    // ===========================
    // AUDIT LOGGING TESTS
    // ===========================

    public function testDeveVerificarTabelaDeAuditExiste(): void
    {
        $stmt = $this->db->query("SHOW TABLES LIKE 'token_refresh_audit'");
        $table = $stmt->fetch();

        $this->assertNotFalse($table, 'Tabela token_refresh_audit deve existir');
    }
}
