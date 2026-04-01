<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\TechSheetSEOIntegrationService;
use PDO;
use Tests\TestCase;

class TechSheetSEOIntegrationServiceApplyTest extends TestCase
{
    private int $accountId;
    private int $testUserId;
    private string $testMlUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = Database::getInstance();

        // Ensure APP_KEY for services that might require it indirectly
        if (!getenv('APP_KEY')) {
            putenv('APP_KEY=UnitTestFallbackKey_ThisIs32CharsLong!');
        }

        // Ensure users table + a user (common FK for ml_accounts)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255),
                email VARCHAR(255),
                password VARCHAR(255),
                role VARCHAR(50) DEFAULT 'admin',
                email_verified_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        } catch (\Throwable $e) {
            // best effort
        }

        $email = 'tsseo-' . bin2hex(random_bytes(4)) . '@test.local';
        try {
            $pdo->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)')
                ->execute([
                    'name' => 'Unit Test User',
                    'email' => $email,
                    'password' => password_hash('secret', PASSWORD_BCRYPT),
                ]);
            $this->testUserId = (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            // best effort
        }

        // Ensure ml_accounts exists and has at least one row (FK target for seo_optimization_history.account_id)
        // Observação: o schema pode variar (migrações antigas tinham access_token/refresh_token/token_expires_at NOT NULL).
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ml_accounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                ml_user_id VARCHAR(64) NOT NULL,
                nickname VARCHAR(255) NULL,
                email VARCHAR(255) NULL,
                site_id VARCHAR(10) DEFAULT 'MLB',
                access_token TEXT NULL,
                refresh_token TEXT NULL,
                token_expires_at DATETIME NULL,
                last_synced_at DATETIME NULL,
                scopes VARCHAR(255) NULL,
                status VARCHAR(20) DEFAULT 'active',
                tokens_encrypted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // best effort
        }

        try {
            $pdo->prepare("DELETE FROM ml_accounts WHERE ml_user_id LIKE 'MLUSER_TSSEO_TEST_%'")->execute();
        } catch (\Throwable $e) {
            // ignore
        }

        // Build insert dynamically based on current schema
        $columns = $pdo->query('SHOW COLUMNS FROM ml_accounts')->fetchAll(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $this->testMlUserId = 'MLUSER_TSSEO_TEST_' . bin2hex(random_bytes(4));

        $candidates = [
            'user_id' => $this->testUserId,
            'ml_user_id' => $this->testMlUserId,
            'nickname' => 'unittest-ml',
            'email' => 'ml-unittest@example.com',
            'site_id' => 'MLB',
            'access_token' => 'dummy-access-token',
            'refresh_token' => 'dummy-refresh-token',
            'token_expires_at' => $expiresAt,
            'last_synced_at' => null,
            'scopes' => null,
            'status' => 'active',
            'tokens_encrypted' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertData = [];
        foreach ($columns as $col) {
            $field = (string)($col['Field'] ?? '');
            if ($field === '' || $field === 'id') {
                continue;
            }

            $isRequired = (($col['Null'] ?? '') === 'NO') && ($col['Default'] === null);

            if (array_key_exists($field, $candidates)) {
                $insertData[$field] = $candidates[$field];
                continue;
            }

            if ($isRequired) {
                $type = strtolower((string)($col['Type'] ?? ''));
                if (str_contains($type, 'int')) {
                    $insertData[$field] = 1;
                } elseif (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
                    $insertData[$field] = 0;
                } elseif (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
                    $insertData[$field] = $now;
                } elseif (str_contains($type, 'enum')) {
                    // Pick first enum option when possible
                    if (preg_match("/enum\((.*)\)/i", $type, $m)) {
                        $opts = array_map(static fn($s) => trim($s, " '\""), explode(',', (string)$m[1]));
                        $insertData[$field] = $opts[0] ?? 'active';
                    } else {
                        $insertData[$field] = 'active';
                    }
                } else {
                    $insertData[$field] = 'x';
                }
            }
        }

        $this->accountId = 0;
        try {
            $cols = array_keys($insertData);
            $placeholders = array_map(static fn($c) => ':' . $c, $cols);
            $sql = 'INSERT INTO ml_accounts (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertData);
            $this->accountId = (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            // If insert fails, keep accountId as 0 and let the test surface the error
            $this->accountId = 0;
        }

        if ($this->accountId <= 0) {
            $existing = $pdo->query('SELECT id FROM ml_accounts ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $this->accountId = isset($existing['id']) ? (int)$existing['id'] : 0;
        }

        $this->assertGreaterThan(0, $this->accountId, 'Falha ao preparar ml_accounts (necessário para FK de versioning).');

        // Minimal table schema for VersioningService
        $pdo->exec("CREATE TABLE IF NOT EXISTS seo_optimization_history (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            item_id VARCHAR(64) NOT NULL,
            account_id INT UNSIGNED NULL,
            version INT NOT NULL,
            change_type VARCHAR(50) NOT NULL,
            changed_by VARCHAR(20) NOT NULL,
            user_id INT UNSIGNED NULL,
            before_data LONGTEXT NULL,
            after_data LONGTEXT NULL,
            diff LONGTEXT NULL,
            snapshot_path VARCHAR(255) NULL,
            can_rollback TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdo->exec('DELETE FROM seo_optimization_history');
    }

    protected function tearDown(): void
    {
        try {
            $pdo = Database::getInstance();
            if (isset($this->testMlUserId) && $this->testMlUserId !== '') {
                $pdo->prepare('DELETE FROM ml_accounts WHERE ml_user_id = :ml_user_id')->execute([
                    'ml_user_id' => $this->testMlUserId,
                ]);
            }
            if (isset($this->testUserId) && $this->testUserId > 0) {
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $this->testUserId]);
            }
        } catch (\Throwable $e) {
            // ignore cleanup failures in tests
        }

        parent::tearDown();
    }

    public function testApplyOptimizedTitleRejectsTooLongTitle(): void
    {
        $service = new TechSheetSEOIntegrationService($this->accountId);
        $this->setMlClientStub($service, new class($this->accountId) extends MercadoLivreClient {
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return ['id' => 'MLB1', 'title' => 'Original', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                return ['id' => 'MLB1'];
            }
        });

        $result = $service->applyOptimizedTitle('MLB1', str_repeat('a', 61), 1);

        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('60', (string)($result['error'] ?? ''));
    }

    public function testApplyOptimizedTitleCreatesSnapshotAndApplies(): void
    {
        $itemId = 'MLB1000';
        $newTitle = 'Titulo Otimizado';

        $service = new TechSheetSEOIntegrationService($this->accountId);
        $this->setMlClientStub($service, new class($this->accountId) extends MercadoLivreClient {
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return ['id' => 'MLB1000', 'title' => 'Titulo Original', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                return ['id' => 'MLB1000', 'title' => (string)($data['title'] ?? '')];
            }
        });

        $result = $service->applyOptimizedTitle($itemId, $newTitle, 1, ['reason' => 'unit-test']);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame($itemId, $result['item_id'] ?? null);
        $this->assertSame('title', $result['change_type'] ?? null);
        $this->assertSame($newTitle, $result['applied_title'] ?? null);
        $this->assertIsInt($result['version_id'] ?? null);

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM seo_optimization_history WHERE id = :id');
        $stmt->execute(['id' => $result['version_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame($itemId, $row['item_id']);
        $this->assertSame('title', $row['change_type']);

        $snapshotPath = $row['snapshot_path'] ?? '';
        $this->assertNotEmpty($snapshotPath);
        $this->assertFileExists($snapshotPath);
    }

    public function testApplyOptimizedTitleCleansUpSnapshotOnApiFailure(): void
    {
        $itemId = 'MLB2000';

        $service = new TechSheetSEOIntegrationService($this->accountId);
        $this->setMlClientStub($service, new class($this->accountId) extends MercadoLivreClient {
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return ['id' => 'MLB2000', 'title' => 'Titulo Original', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                return ['error' => 'bad_request', 'message' => 'Falha simulada'];
            }
        });

        $result = $service->applyOptimizedTitle($itemId, 'Novo Titulo', 1);

        $this->assertFalse($result['success'] ?? true);
        $this->assertSame('Falha simulada', $result['error'] ?? null);

        $pdo = Database::getInstance();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM seo_optimization_history')->fetchColumn();
        $this->assertSame(0, $count, 'Snapshot/history should be cleaned on failure');
    }

    public function testApplyOptimizedDescriptionCreatesSnapshotAndApplies(): void
    {
        $itemId = 'MLB3000';
        $newDesc = 'Descricao otimizadissima';

        $service = new TechSheetSEOIntegrationService($this->accountId);
        $this->setMlClientStub($service, new class($this->accountId) extends MercadoLivreClient {
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                if (str_contains($endpoint, '/description')) {
                    return ['plain_text' => 'Descricao original'];
                }

                return ['id' => 'MLB3000', 'title' => 'Titulo Original', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                if (str_contains($endpoint, '/description')) {
                    return ['id' => 'MLB3000'];
                }

                return ['error' => 'unexpected_call'];
            }
        });

        $result = $service->applyOptimizedDescription($itemId, $newDesc, 1, ['reason' => 'unit-test']);

        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('description', $result['change_type'] ?? null);
        $this->assertSame(mb_strlen($newDesc), $result['applied_description_length'] ?? null);

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM seo_optimization_history WHERE id = :id');
        $stmt->execute(['id' => $result['version_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('description', $row['change_type']);
        $this->assertFileExists((string)($row['snapshot_path'] ?? ''));
    }

    private function setMlClientStub(TechSheetSEOIntegrationService $service, MercadoLivreClient $client): void
    {
        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('mlClient');
        $prop->setAccessible(true);
        $prop->setValue($service, $client);
    }
}
