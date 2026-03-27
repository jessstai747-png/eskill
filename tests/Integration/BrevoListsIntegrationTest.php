<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Integrations\Brevo\BrevoClient;
use App\Services\Integrations\Brevo\BrevoListsService;
use PHPUnit\Framework\TestCase;

class BrevoListsIntegrationTest extends TestCase
{
    public function testRealApiCreateUpdateDeleteListWhenConfigured(): void
    {
        $apiKey = (string)($_ENV['BREVO_API_KEY'] ?? '');
        if ($apiKey === '') {
            $this->markTestSkipped('Teste de integração Brevo requer BREVO_API_KEY');
        }

        $client = new BrevoClient();
        $svc = new BrevoListsService($client);

        $name = 'Eskill IT ' . date('YmdHis') . bin2hex(random_bytes(2));

        $create = $svc->createList($name);
        $this->assertTrue($create['success']);
        $listId = (int)($create['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $listId);

        $update = $svc->updateList($listId, $name . ' Updated');
        $this->assertTrue($update['success']);

        $delete = $svc->deleteList($listId);
        $this->assertTrue($delete['success']);
    }
}

