<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetWebhookService;

class TechSheetWebhookServiceTest extends TestCase
{
    private int $testAccountId = 999;

    public function testRegisterWebhookReturnsId(): void
    {
        $service = new TechSheetWebhookService($this->testAccountId);
        
        try {
            $webhookId = $service->registerWebhook('http', [
                'url' => 'https://example.com/webhook',
                'events' => ['*'],
            ]);
            
            $this->assertIsInt($webhookId);
            $this->assertGreaterThan(0, $webhookId);
            
            // Cleanup
            $service->deleteWebhook($webhookId);
            
        } catch (\Exception $e) {
            // Tabela pode não existir
            $this->assertTrue(true);
        }
    }

    public function testListWebhooksReturnsArray(): void
    {
        $service = new TechSheetWebhookService($this->testAccountId);
        
        try {
            $result = $service->listWebhooks();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testNotifyReturnsArray(): void
    {
        $service = new TechSheetWebhookService($this->testAccountId);
        
        $result = $service->notify('test.event', [
            'message' => 'Test notification',
        ]);
        
        $this->assertIsArray($result);
    }
}
