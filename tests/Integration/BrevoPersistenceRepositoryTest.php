<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Database;
use App\Services\Integrations\Brevo\BrevoPersistenceRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class BrevoPersistenceRepositoryTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
    }

    public function testUpsertAndSoftDeleteListPreservesFolderId(): void
    {
        $repo = new BrevoPersistenceRepository($this->db);

        $listId = 9999991;

        $this->db->prepare('DELETE FROM brevo_lists WHERE brevo_list_id = :id')
            ->execute(['id' => $listId]);

        $repo->upsertList(['id' => $listId, 'name' => 'Lista Teste', 'folderId' => 123], $this->now());

        $row = $this->fetchOne('SELECT * FROM brevo_lists WHERE brevo_list_id = :id', ['id' => $listId]);
        $this->assertNotNull($row);
        $this->assertSame('Lista Teste', $row['name']);
        $this->assertSame('123', (string)$row['folder_id']);
        $this->assertNull($row['deleted_at']);

        $repo->softDeleteList($listId, $this->now());
        $row = $this->fetchOne('SELECT * FROM brevo_lists WHERE brevo_list_id = :id', ['id' => $listId]);
        $this->assertNotNull($row);
        $this->assertNotNull($row['deleted_at']);

        // Upsert sem folderId não deve apagar folder_id existente
        $repo->upsertList(['id' => $listId, 'name' => 'Lista Teste 2'], $this->now());
        $row = $this->fetchOne('SELECT * FROM brevo_lists WHERE brevo_list_id = :id', ['id' => $listId]);
        $this->assertNotNull($row);
        $this->assertSame('Lista Teste 2', $row['name']);
        $this->assertSame('123', (string)$row['folder_id']);
        $this->assertNull($row['deleted_at']);
    }

    public function testUpsertAndSoftDeleteContactPreservesOptionalFields(): void
    {
        $repo = new BrevoPersistenceRepository($this->db);

        $email = 'brevo_persist_test+' . date('YmdHis') . bin2hex(random_bytes(2)) . '@example.com';

        $this->db->prepare('DELETE FROM brevo_contacts WHERE email = :email')
            ->execute(['email' => $email]);

        $repo->upsertContact([
            'email' => $email,
            'attributes' => ['FIRSTNAME' => 'Teste', 'LASTNAME' => 'Persist'],
            'listIds' => [10, 20],
            'emailBlacklisted' => true,
            'smsBlacklisted' => false,
        ], $this->now());

        $row = $this->fetchOne('SELECT * FROM brevo_contacts WHERE email = :email', ['email' => $email]);
        $this->assertNotNull($row);
        $this->assertSame('1', (string)$row['email_blacklisted']);
        $this->assertSame('0', (string)$row['sms_blacklisted']);

        $attrs = json_decode((string)$row['attributes_json'], true);
        $this->assertIsArray($attrs);
        $this->assertSame('Teste', $attrs['FIRSTNAME']);

        $listIds = json_decode((string)$row['list_ids_json'], true);
        $this->assertSame([10, 20], $listIds);

        $repo->softDeleteContact($email, $this->now());
        $row = $this->fetchOne('SELECT * FROM brevo_contacts WHERE email = :email', ['email' => $email]);
        $this->assertNotNull($row);
        $this->assertNotNull($row['deleted_at']);

        // Upsert só com attributes não deve apagar listIds nem resetar blacklisted
        $repo->upsertContact([
            'email' => $email,
            'attributes' => ['LASTNAME' => 'Persist2'],
        ], $this->now());

        $row = $this->fetchOne('SELECT * FROM brevo_contacts WHERE email = :email', ['email' => $email]);
        $this->assertNotNull($row);
        $this->assertNull($row['deleted_at']);
        $this->assertSame('1', (string)$row['email_blacklisted']);

        $listIds = json_decode((string)$row['list_ids_json'], true);
        $this->assertSame([10, 20], $listIds);
    }

    public function testSyncRunLifecycle(): void
    {
        $repo = new BrevoPersistenceRepository($this->db);

        $runId = $repo->startSyncRun('lists', ['limit' => 50]);
        $this->assertGreaterThan(0, $runId);

        $repo->finishSyncRun($runId, 'success', 10, 0, 200, null, ['pages' => 1]);

        $row = $this->fetchOne('SELECT * FROM brevo_sync_runs WHERE id = :id', ['id' => $runId]);
        $this->assertNotNull($row);
        $this->assertSame('lists', $row['entity']);
        $this->assertSame('success', $row['status']);
        $this->assertSame('10', (string)$row['processed']);
        $this->assertNotNull($row['finished_at']);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
