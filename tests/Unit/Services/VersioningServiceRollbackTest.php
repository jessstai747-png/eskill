<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\SEO\VersioningService;
use PDO;
use Tests\TestCase;

class VersioningServiceRollbackTest extends TestCase
{
    private int $accountId;

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

        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
            $stmt->execute(['id' => 1]);
            if (!$stmt->fetch()) {
                $pdo->prepare('INSERT INTO users (id, name, email, password) VALUES (:id, :name, :email, :password)')
                    ->execute([
                        'id' => 1,
                        'name' => 'Unit Test User',
                        'email' => 'unittest@example.com',
                        'password' => password_hash('secret', PASSWORD_BCRYPT),
                    ]);
            }
        } catch (\Throwable $e) {
            // best effort
        }

        // Ensure ml_accounts exists and has at least one row (FK target for seo_optimization_history.account_id)
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

        // Cleanup previous runs of this test without nuking the whole table (some suites may share the DB).
        try {
            $pdo->prepare("DELETE FROM ml_accounts WHERE ml_user_id LIKE 'MLUSER_UNITTEST%'")->execute();
        } catch (\Throwable $e) {
            // ignore
        }

        // Build insert dynamically based on current schema
        $columns = $pdo->query('SHOW COLUMNS FROM ml_accounts')->fetchAll(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $candidates = [
            'user_id' => 1,
            'ml_user_id' => 'MLUSER_UNITTEST',
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

        // Avoid deleting shared fixtures: insert a unique ML user id per test run.
        // Some schemas enforce UNIQUE(ml_user_id).
        try {
            $candidates['ml_user_id'] = 'MLUSER_UNITTEST_' . bin2hex(random_bytes(4));
        } catch (\Throwable $e) {
            // ignore; fallback keeps deterministic value
        }

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
            $this->accountId = 0;
        }

        if ($this->accountId <= 0) {
            $existing = $pdo->query('SELECT id FROM ml_accounts ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $this->accountId = isset($existing['id']) ? (int)$existing['id'] : 0;
        }

        $this->assertGreaterThan(0, $this->accountId, 'Falha ao preparar ml_accounts (necessário para FK de versioning).');

        // Ensure seo_optimization_history exists
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

        // Add rollback columns when missing (older test schema)
        $cols = $pdo->query('SHOW COLUMNS FROM seo_optimization_history')->fetchAll(PDO::FETCH_ASSOC);
        $existingCols = array_map(static fn($c) => (string)$c['Field'], $cols);

        $this->addColumnIfMissing($pdo, $existingCols, 'rolled_back', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->addColumnIfMissing($pdo, $existingCols, 'rolled_back_at', 'DATETIME NULL');
        $this->addColumnIfMissing($pdo, $existingCols, 'rollback_reason', 'TEXT NULL');
        $this->addColumnIfMissing($pdo, $existingCols, 'applied_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

        // Keep table clean per-test to avoid cross-test coupling within this suite.
        $pdo->exec('DELETE FROM seo_optimization_history');

        $this->cleanupSnapshotFiles('UNIT_ROLLBACK_');
    }

    protected function tearDown(): void
    {
        $this->cleanupSnapshotFiles('UNIT_ROLLBACK_');
        parent::tearDown();
    }

    public function testRollbackCreatesNewVersionWithSameChangeTypeAndApplies(): void
    {
        $itemId = 'UNIT_ROLLBACK_1000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                if (str_contains($endpoint, '/description')) {
                    return ['plain_text' => 'Descricao atual'];
                }

                return ['id' => 'UNIT_ROLLBACK_1000', 'title' => 'Titulo Depois', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['id' => 'UNIT_ROLLBACK_1000'];
            }
        };
        $this->setMlClientStub($service, $client);

        $versionId = $service->createSnapshot(
            $itemId,
            'title',
            ['id' => $itemId, 'title' => 'Titulo Antes'],
            ['title' => 'Titulo Depois'],
            'user',
            1
        );

        $ok = $service->rollback($itemId, $versionId, 'unit-test');
        $this->assertTrue($ok);

        $this->assertNotEmpty($client->puts);
        $last = $client->puts[count($client->puts) - 1];
        $this->assertSame("/items/{$itemId}", $last['endpoint']);
        $this->assertSame('Titulo Antes', (string)($last['data']['title'] ?? ''));

        $pdo = Database::getInstance();
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM seo_optimization_history WHERE item_id = :item_id');
        $stmtCount->execute(['item_id' => $itemId]);
        $count = (int)$stmtCount->fetchColumn();
        $this->assertSame(2, $count);

        $stmtRow = $pdo->prepare('SELECT * FROM seo_optimization_history WHERE item_id = :item_id ORDER BY version DESC LIMIT 1');
        $stmtRow->execute(['item_id' => $itemId]);
        $row = $stmtRow->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('title', (string)($row['change_type'] ?? ''));

        $snapshotPath = (string)($row['snapshot_path'] ?? '');
        $this->assertNotSame('', $snapshotPath);
        $this->assertFileExists($snapshotPath);

        // Original should be marked as rolled back when column exists
        $orig = $pdo->query('SELECT rolled_back FROM seo_optimization_history WHERE id = ' . (int)$versionId)->fetch(PDO::FETCH_ASSOC);
        if (is_array($orig) && array_key_exists('rolled_back', $orig)) {
            $this->assertSame('1', (string)$orig['rolled_back']);
        }
    }

    public function testRollbackDoesNotSucceedOnApiErrorAndCleansUp(): void
    {
        $itemId = 'UNIT_ROLLBACK_2000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                if (str_contains($endpoint, '/description')) {
                    return ['plain_text' => 'Descricao atual'];
                }

                return ['id' => 'UNIT_ROLLBACK_2000', 'title' => 'Titulo Depois', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['error' => 'bad_request', 'message' => 'Falha simulada'];
            }
        };
        $this->setMlClientStub($service, $client);

        $versionId = $service->createSnapshot(
            $itemId,
            'title',
            ['id' => $itemId, 'title' => 'Titulo Antes'],
            ['title' => 'Titulo Depois'],
            'user',
            1
        );

        $ok = $service->rollback($itemId, $versionId, 'unit-test');
        $this->assertFalse($ok);

        $pdo = Database::getInstance();
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM seo_optimization_history WHERE item_id = :item_id');
        $stmtCount->execute(['item_id' => $itemId]);
        $count = (int)$stmtCount->fetchColumn();
        $this->assertSame(1, $count, 'Não deve deixar histórico extra quando rollback falha');

        $orig = $pdo->query('SELECT rolled_back FROM seo_optimization_history WHERE id = ' . (int)$versionId)->fetch(PDO::FETCH_ASSOC);
        if (is_array($orig) && array_key_exists('rolled_back', $orig)) {
            $this->assertSame('0', (string)$orig['rolled_back']);
        }
    }

    public function testRollbackDescriptionUsesCorrectEndpoint(): void
    {
        $itemId = 'UNIT_ROLLBACK_3000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                if (str_contains($endpoint, '/description')) {
                    return ['plain_text' => 'Descricao atual alterada'];
                }

                return ['id' => 'UNIT_ROLLBACK_3000', 'title' => 'Titulo Atual', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['id' => 'UNIT_ROLLBACK_3000'];
            }
        };
        $this->setMlClientStub($service, $client);

        // Create snapshot for description change
        $versionId = $service->createSnapshot(
            $itemId,
            'description',
            ['id' => $itemId, 'description_plain_text' => 'Descricao original antes da mudanca'],
            ['description_plain_text' => 'Descricao atual alterada'],
            'user',
            1
        );

        $ok = $service->rollback($itemId, $versionId, 'unit-test-description');
        $this->assertTrue($ok);

        // Description rollback should use /items/{id}/description endpoint
        $this->assertNotEmpty($client->puts);
        $last = $client->puts[count($client->puts) - 1];
        $this->assertSame("/items/{$itemId}/description", $last['endpoint']);
        $this->assertArrayHasKey('plain_text', $last['data']);
        $this->assertSame('Descricao original antes da mudanca', $last['data']['plain_text']);
    }

    public function testLoadSnapshotRejectsPathTraversal(): void
    {
        $itemId = 'UNIT_ROLLBACK_4000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return ['id' => 'UNIT_ROLLBACK_4000', 'title' => 'Titulo', 'category_id' => 'MLB123'];
            }

            public function put(string $endpoint, array $data = []): array
            {
                return ['id' => 'UNIT_ROLLBACK_4000'];
            }
        };
        $this->setMlClientStub($service, $client);

        // Create a valid version first
        $versionId = $service->createSnapshot(
            $itemId,
            'title',
            ['id' => $itemId, 'title' => 'Titulo Original'],
            ['title' => 'Titulo Novo'],
            'user',
            1
        );

        // Simulate a malicious path traversal by modifying the snapshot_path in database
        $pdo = Database::getInstance();
        $maliciousPath = '/etc/passwd';
        $pdo->prepare('UPDATE seo_optimization_history SET snapshot_path = :path WHERE id = :id')
            ->execute(['path' => $maliciousPath, 'id' => $versionId]);

        // Rollback should fail because the path is outside allowed directory
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Snapshot file not found');
        $service->rollback($itemId, $versionId, 'malicious-test');
    }

    public function testRollbackAttributesUsesCorrectPayload(): void
    {
        $itemId = 'UNIT_ROLLBACK_5000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return [
                    'id' => 'UNIT_ROLLBACK_5000',
                    'title' => 'Produto Teste',
                    'category_id' => 'MLB123',
                    'attributes' => [
                        ['id' => 'BRAND', 'value_name' => 'Nova Marca'],
                        ['id' => 'MODEL', 'value_name' => 'Modelo Novo']
                    ]
                ];
            }

            public function put(string $endpoint, array $data = []): array
            {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['id' => 'UNIT_ROLLBACK_5000'];
            }
        };
        $this->setMlClientStub($service, $client);

        $originalAttributes = [
            ['id' => 'BRAND', 'value_name' => 'Marca Original'],
            ['id' => 'MODEL', 'value_name' => 'Modelo Original']
        ];

        $versionId = $service->createSnapshot(
            $itemId,
            'attributes',
            ['id' => $itemId, 'attributes' => $originalAttributes],
            ['attributes' => [['id' => 'BRAND', 'value_name' => 'Nova Marca']]],
            'user',
            1
        );

        $ok = $service->rollback($itemId, $versionId, 'unit-test-attributes');
        $this->assertTrue($ok);

        $this->assertNotEmpty($client->puts);
        $last = $client->puts[count($client->puts) - 1];
        $this->assertSame("/items/{$itemId}", $last['endpoint']);
        $this->assertArrayHasKey('attributes', $last['data']);
        $this->assertCount(2, $last['data']['attributes']);
    }

    public function testRollbackPriceUsesCorrectPayload(): void
    {
        $itemId = 'UNIT_ROLLBACK_6000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return [
                    'id' => 'UNIT_ROLLBACK_6000',
                    'title' => 'Produto Teste',
                    'category_id' => 'MLB123',
                    'price' => 199.99
                ];
            }

            public function put(string $endpoint, array $data = []): array
            {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['id' => 'UNIT_ROLLBACK_6000'];
            }
        };
        $this->setMlClientStub($service, $client);

        $versionId = $service->createSnapshot(
            $itemId,
            'price',
            ['id' => $itemId, 'price' => 149.99],
            ['price' => 199.99],
            'user',
            1
        );

        $ok = $service->rollback($itemId, $versionId, 'unit-test-price');
        $this->assertTrue($ok);

        $this->assertNotEmpty($client->puts);
        $last = $client->puts[count($client->puts) - 1];
        $this->assertSame("/items/{$itemId}", $last['endpoint']);
        $this->assertArrayHasKey('price', $last['data']);
        $this->assertEquals(149.99, $last['data']['price']);
    }

    public function testRollbackImagesUsesCorrectPayload(): void
    {
        $itemId = 'UNIT_ROLLBACK_7000';

        $service = new VersioningService($this->accountId);
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;

            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }

            public function getAccountId(): ?int
            {
                return $this->aid;
            }

            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
            {
                return [
                    'id' => 'UNIT_ROLLBACK_7000',
                    'title' => 'Produto Teste',
                    'category_id' => 'MLB123',
                    'pictures' => [
                        ['id' => 'img1', 'url' => 'https://new-image.jpg']
                    ]
                ];
            }

            public function put(string $endpoint, array $data = []): array
            {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['id' => 'UNIT_ROLLBACK_7000'];
            }
        };
        $this->setMlClientStub($service, $client);

        $originalPictures = [
            ['id' => 'img1', 'url' => 'https://original-image1.jpg'],
            ['id' => 'img2', 'url' => 'https://original-image2.jpg']
        ];

        $versionId = $service->createSnapshot(
            $itemId,
            'images',
            ['id' => $itemId, 'pictures' => $originalPictures],
            ['pictures' => [['id' => 'img1', 'url' => 'https://new-image.jpg']]],
            'user',
            1
        );

        $ok = $service->rollback($itemId, $versionId, 'unit-test-images');
        $this->assertTrue($ok);

        $this->assertNotEmpty($client->puts);
        $last = $client->puts[count($client->puts) - 1];
        $this->assertSame("/items/{$itemId}", $last['endpoint']);
        $this->assertArrayHasKey('pictures', $last['data']);
        $this->assertCount(2, $last['data']['pictures']);
    }

    public function testCreateSnapshotRejectsInvalidChangeType(): void
    {
        $service = new VersioningService($this->accountId);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid change_type: invalid_type');
        
        $service->createSnapshot(
            'ITEM_INVALID_TYPE',
            'invalid_type',
            ['title' => 'Before'],
            ['title' => 'After'],
            'user',
            1
        );
    }

    public function testCreateSnapshotRejectsInvalidChangedBy(): void
    {
        $service = new VersioningService($this->accountId);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid changed_by: robot');
        
        $service->createSnapshot(
            'ITEM_INVALID_BY',
            'title',
            ['title' => 'Before'],
            ['title' => 'After'],
            'robot',
            1
        );
    }

    public function testGetHistoryReturnsVersionsInDescendingOrder(): void
    {
        $itemId = 'UNIT_HISTORY_TEST';
        $service = new VersioningService($this->accountId);
        
        // Create multiple versions
        $v1 = $service->createSnapshot($itemId, 'title', ['title' => 'V1'], ['title' => 'V2'], 'user', 1);
        $v2 = $service->createSnapshot($itemId, 'title', ['title' => 'V2'], ['title' => 'V3'], 'ai', null);
        $v3 = $service->createSnapshot($itemId, 'price', ['price' => 100], ['price' => 150], 'automation', null);
        
        $history = $service->getHistory($itemId, 10);
        
        $this->assertCount(3, $history);
        // Most recent first (descending by version)
        $this->assertEquals(3, $history[0]['version']);
        $this->assertEquals(2, $history[1]['version']);
        $this->assertEquals(1, $history[2]['version']);
        
        // Verify change types
        $this->assertEquals('price', $history[0]['change_type']);
        $this->assertEquals('title', $history[1]['change_type']);
        $this->assertEquals('title', $history[2]['change_type']);
        
        // Cleanup
        $this->cleanupSnapshotFiles($itemId);
    }

    public function testCompareVersionsReturnsCorrectDiff(): void
    {
        $itemId = 'UNIT_COMPARE_TEST';
        $service = new VersioningService($this->accountId);
        
        $v1Id = $service->createSnapshot(
            $itemId,
            'title',
            ['title' => 'Original'],
            ['title' => 'Version 1'],
            'user',
            1
        );
        
        $v2Id = $service->createSnapshot(
            $itemId,
            'title',
            ['title' => 'Version 1'],
            ['title' => 'Version 2'],
            'user',
            1
        );
        
        $comparison = $service->compareVersions($v1Id, $v2Id);
        
        $this->assertEquals($itemId, $comparison['item_id']);
        $this->assertEquals($v1Id, $comparison['version_1']['id']);
        $this->assertEquals($v2Id, $comparison['version_2']['id']);
        $this->assertEquals(1, $comparison['version_1']['version']);
        $this->assertEquals(2, $comparison['version_2']['version']);
        $this->assertArrayHasKey('diff', $comparison);
        
        // Cleanup
        $this->cleanupSnapshotFiles($itemId);
    }

    public function testCompareVersionsThrowsOnDifferentItems(): void
    {
        $service = new VersioningService($this->accountId);
        
        $v1Id = $service->createSnapshot('ITEM_A', 'title', ['title' => 'A'], ['title' => 'B'], 'user', 1);
        $v2Id = $service->createSnapshot('ITEM_B', 'title', ['title' => 'X'], ['title' => 'Y'], 'user', 1);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Versions belong to different items');
        
        $service->compareVersions($v1Id, $v2Id);
    }

    public function testRollbackFailsWithEmptyAttributes(): void
    {
        $itemId = 'UNIT_EMPTY_ATTRS';
        $service = new VersioningService($this->accountId);
        
        // Create snapshot with empty attributes
        $versionId = $service->createSnapshot(
            $itemId,
            'attributes',
            ['id' => $itemId, 'attributes' => []],
            ['attributes' => [['id' => 'BRAND', 'value_name' => 'Test']]],
            'user',
            1
        );
        
        // Mock ML client
        $client = new class($this->accountId) extends MercadoLivreClient {
            private int $aid;
            public function __construct(int $accountId) {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }
            public function getAccountId(): ?int { return $this->aid; }
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array {
                return ['id' => 'UNIT_EMPTY_ATTRS', 'title' => 'Test', 'attributes' => []];
            }
        };
        $this->setMlClientStub($service, $client);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Snapshot não contém atributos válidos para rollback');
        
        $service->rollback($itemId, $versionId, 'test-empty-attrs');
    }

    public function testRollbackFailsWithEmptyImages(): void
    {
        $itemId = 'UNIT_EMPTY_IMGS';
        $service = new VersioningService($this->accountId);
        
        $versionId = $service->createSnapshot(
            $itemId,
            'images',
            ['id' => $itemId, 'pictures' => []],
            ['pictures' => [['id' => 'img1', 'url' => 'https://test.jpg']]],
            'user',
            1
        );
        
        $client = new class($this->accountId) extends MercadoLivreClient {
            private int $aid;
            public function __construct(int $accountId) {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }
            public function getAccountId(): ?int { return $this->aid; }
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array {
                return ['id' => 'UNIT_EMPTY_IMGS', 'title' => 'Test', 'pictures' => []];
            }
        };
        $this->setMlClientStub($service, $client);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Snapshot não contém imagens válidas para rollback');
        
        $service->rollback($itemId, $versionId, 'test-empty-images');
    }

    public function testRollbackFailsWithZeroPrice(): void
    {
        $itemId = 'UNIT_ZERO_PRICE';
        $service = new VersioningService($this->accountId);
        
        $versionId = $service->createSnapshot(
            $itemId,
            'price',
            ['id' => $itemId, 'price' => 0],
            ['price' => 99.99],
            'user',
            1
        );
        
        $client = new class($this->accountId) extends MercadoLivreClient {
            private int $aid;
            public function __construct(int $accountId) {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }
            public function getAccountId(): ?int { return $this->aid; }
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array {
                return ['id' => 'UNIT_ZERO_PRICE', 'title' => 'Test', 'price' => 50];
            }
        };
        $this->setMlClientStub($service, $client);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Snapshot não contém preço válido para rollback');
        
        $service->rollback($itemId, $versionId, 'test-zero-price');
    }

    public function testGetVersionReturnsNullForNonExistent(): void
    {
        $service = new VersioningService($this->accountId);
        
        $version = $service->getVersion(999999999);
        
        $this->assertNull($version);
    }

    public function testRollbackThrowsOnNonExistentVersion(): void
    {
        $service = new VersioningService($this->accountId);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Version not found: 999999999');
        
        $service->rollback('SOME_ITEM', 999999999, 'test');
    }

    public function testCleanOldSnapshotsRejectsInvalidDays(): void
    {
        $service = new VersioningService($this->accountId);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('daysToKeep must be at least 1');
        
        $service->cleanOldSnapshots(0);
    }

    public function testCleanOldSnapshotsRemovesOldVersions(): void
    {
        $itemId = 'UNIT_CLEANUP_TEST';
        $service = new VersioningService($this->accountId);
        $pdo = Database::getInstance();
        
        // Create a snapshot
        $versionId = $service->createSnapshot(
            $itemId,
            'title',
            ['title' => 'Old Title'],
            ['title' => 'New Title'],
            'user',
            1
        );
        
        // Manually backdate the applied_at to simulate old record
        $oldDate = date('Y-m-d H:i:s', strtotime('-100 days'));
        $stmt = $pdo->prepare('UPDATE seo_optimization_history SET applied_at = :date WHERE id = :id');
        $stmt->execute(['date' => $oldDate, 'id' => $versionId]);
        
        // Run cleanup with 90 days retention
        $deletedCount = $service->cleanOldSnapshots(90);
        
        $this->assertGreaterThanOrEqual(1, $deletedCount);
        
        // Verify can_rollback is now FALSE
        $version = $service->getVersion($versionId);
        $this->assertNotNull($version);
        $this->assertEquals(0, (int)$version['can_rollback']);
        $this->assertNull($version['snapshot_path']);
    }

    public function testGetStatisticsReturnsCorrectCounts(): void
    {
        $itemId = 'UNIT_STATS_TEST';
        $service = new VersioningService($this->accountId);
        
        // Create some versions
        $service->createSnapshot($itemId, 'title', ['title' => 'A'], ['title' => 'B'], 'user', 1);
        $service->createSnapshot($itemId, 'price', ['price' => 100], ['price' => 200], 'ai', null);
        $service->createSnapshot($itemId . '_2', 'title', ['title' => 'X'], ['title' => 'Y'], 'automation', null);
        
        $stats = $service->getStatistics($this->accountId);
        
        $this->assertArrayHasKey('total_versions', $stats);
        $this->assertArrayHasKey('total_rollbacks', $stats);
        $this->assertArrayHasKey('rollbackable_versions', $stats);
        $this->assertArrayHasKey('items_with_history', $stats);
        
        $this->assertGreaterThanOrEqual(3, (int)$stats['total_versions']);
        $this->assertGreaterThanOrEqual(2, (int)$stats['items_with_history']);
        
        // Cleanup
        $this->cleanupSnapshotFiles($itemId);
        $this->cleanupSnapshotFiles($itemId . '_2');
    }

    public function testUpdateImpactStoresData(): void
    {
        $itemId = 'UNIT_IMPACT_TEST';
        $service = new VersioningService($this->accountId);
        
        $versionId = $service->createSnapshot(
            $itemId,
            'title',
            ['title' => 'Before'],
            ['title' => 'After'],
            'user',
            1
        );
        
        $impactData = [
            'visits_before' => 100,
            'visits_after' => 150,
            'conversion_rate_before' => 2.5,
            'conversion_rate_after' => 3.8,
            'measured_at' => date('Y-m-d H:i:s'),
        ];
        
        $service->updateImpact($versionId, $impactData);
        
        // Verify impact was stored
        $version = $service->getVersion($versionId);
        $this->assertNotNull($version);
        
        // actual_impact should be in the version data
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT actual_impact FROM seo_optimization_history WHERE id = :id');
        $stmt->execute(['id' => $versionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $storedImpact = json_decode($row['actual_impact'], true);
        $this->assertEquals(150, $storedImpact['visits_after']);
        $this->assertEquals(3.8, $storedImpact['conversion_rate_after']);
        
        // Cleanup
        $this->cleanupSnapshotFiles($itemId);
    }

    public function testRollbackMarksVersionAsRolledBack(): void
    {
        $itemId = 'UNIT_ROLLBACK_MARK';
        $service = new VersioningService($this->accountId);
        
        // Create mock client
        $client = new class($this->accountId) extends MercadoLivreClient {
            public array $puts = [];
            private int $aid;
            
            public function __construct(int $accountId) {
                parent::__construct($accountId);
                $this->aid = $accountId;
            }
            
            public function getAccountId(): ?int { return $this->aid; }
            
            public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array {
                return ['id' => 'UNIT_ROLLBACK_MARK', 'title' => 'Current Title', 'category_id' => 'MLB123'];
            }
            
            public function put(string $endpoint, array $data = []): array {
                $this->puts[] = ['endpoint' => $endpoint, 'data' => $data];
                return ['id' => 'UNIT_ROLLBACK_MARK'];
            }
        };
        
        $this->setMlClientStub($service, $client);
        
        $versionId = $service->createSnapshot(
            $itemId,
            'title',
            ['title' => 'Original Title'],
            ['title' => 'Changed Title'],
            'user',
            1
        );
        
        $ok = $service->rollback($itemId, $versionId, 'Testing rollback mark');
        $this->assertTrue($ok);
        
        // Verify original version is marked as rolled back
        $version = $service->getVersion($versionId);
        $this->assertEquals(1, (int)$version['rolled_back']);
        $this->assertNotNull($version['rolled_back_at']);
        $this->assertEquals('Testing rollback mark', $version['rollback_reason']);
        
        // Cleanup
        $this->cleanupSnapshotFiles($itemId);
    }

    private function setMlClientStub(VersioningService $service, MercadoLivreClient $client): void
    {
        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('mlClient');
        $prop->setAccessible(true);
        $prop->setValue($service, $client);
    }

    private function addColumnIfMissing(PDO $pdo, array $existingCols, string $column, string $definition): void
    {
        if (in_array($column, $existingCols, true)) {
            return;
        }

        try {
            $pdo->exec('ALTER TABLE seo_optimization_history ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // best effort
        }
    }

    private function cleanupSnapshotFiles(string $prefix): void
    {
        // The service writes to <project>/storage/seo_snapshots.
        // Previous versions of this test used <project>/tests/storage/seo_snapshots.
        $root = realpath(__DIR__ . '/../../../..');
        $dirs = [];
        if (is_string($root) && $root !== '') {
            $dirs[] = $root . '/storage/seo_snapshots';
        }
        $dirs[] = __DIR__ . '/../../../storage/seo_snapshots';

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/' . $prefix . '*');
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
