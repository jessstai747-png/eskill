<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Integrations\Brevo\BrevoClient;
use App\Services\Integrations\Brevo\BrevoContactsService;
use PHPUnit\Framework\TestCase;

class BrevoIntegrationTest extends TestCase
{
    public function testRealApiCrudContactWhenConfigured(): void
    {
        $apiKey = (string)($_ENV['BREVO_API_KEY'] ?? '');
        $testEmail = (string)($_ENV['BREVO_TEST_EMAIL'] ?? '');

        if ($apiKey === '' || $testEmail === '') {
            $this->markTestSkipped('Teste de integração Brevo requer BREVO_API_KEY e BREVO_TEST_EMAIL');
        }

        $uniqueEmail = $this->uniqueEmail($testEmail);

        $client = new BrevoClient();
        $svc = new BrevoContactsService($client);

        $create = $svc->createContact([
            'email' => $uniqueEmail,
            'attributes' => [
                'FIRSTNAME' => 'Eskill',
                'LASTNAME' => 'IntegrationTest'
            ],
            'updateEnabled' => true
        ]);
        $this->assertTrue($create['success']);

        $get = $svc->getContact($uniqueEmail, false);
        $this->assertTrue($get['success']);

        $update = $svc->updateContact($uniqueEmail, [
            'attributes' => [
                'LASTNAME' => 'IntegrationTestUpdated'
            ]
        ]);
        $this->assertTrue($update['success']);

        $delete = $svc->deleteContact($uniqueEmail);
        $this->assertTrue($delete['success']);
    }

    private function uniqueEmail(string $baseEmail): string
    {
        $baseEmail = strtolower(trim($baseEmail));
        $parts = explode('@', $baseEmail, 2);
        if (count($parts) !== 2) {
            return 'brevo_test_' . time() . '@example.com';
        }

        [$local, $domain] = $parts;
        $suffix = '+it' . date('YmdHis') . bin2hex(random_bytes(2));
        return $local . $suffix . '@' . $domain;
    }
}

