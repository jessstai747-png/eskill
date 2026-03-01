<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\WhatsAppService;

/**
 * Testes de notificacoes via WhatsApp — Fase 7.
 *
 * WhatsAppService requer int $userId e DB no construtor — sem skipDbAutoConnect.
 * Testes de estrutura sempre executam; funcionais pulam se DB indisponivel.
 *
 * @covers \App\Services\WhatsAppService
 */
class WhatsAppNotificationTest extends TestCase
{
    private bool $dbAvailable = false;
    private ?WhatsAppService $service = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->service     = new WhatsAppService(1);
            $this->dbAvailable = true;
        } catch (\Throwable) {
            $this->dbAvailable = false;
        }
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testWhatsAppServiceClassExists(): void
    {
        $this->assertTrue(class_exists(WhatsAppService::class));
    }

    /** @dataProvider whatsappMethodsProvider */
    public function testWhatsAppServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(WhatsAppService::class, $method),
            "WhatsAppService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function whatsappMethodsProvider(): array
    {
        return [
            'saveSettings'  => ['saveSettings'],
            'sendMessage'   => ['sendMessage'],
            'isConfigured'  => ['isConfigured'],
            'send'          => ['send'],
            'getSettings'   => ['getSettings'],
            'getLogs'       => ['getLogs'],
        ];
    }

    public function testSendMessageSignatureAcceptsToAndMessage(): void
    {
        $ref    = new \ReflectionMethod(WhatsAppService::class, 'sendMessage');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params));
        $this->assertSame('to', $params[0]->getName());
        $this->assertSame('message', $params[1]->getName());
    }

    public function testSendMethodReturnTypeIsArray(): void
    {
        $ref    = new \ReflectionMethod(WhatsAppService::class, 'send');
        $return = $ref->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame('array', (string) $return);
    }

    // ------------------------------------------------------------------
    // Testes funcionais (requerem DB)
    // ------------------------------------------------------------------

    public function testIsConfiguredReturnsBoolWhenDbAvailable(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel — WhatsAppService requer Database::getInstance()');
        }

        $result = $this->service->isConfigured();

        $this->assertIsBool($result);
    }

    public function testGetSettingsReturnsNullOrArrayWhenDbAvailable(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->service->getSettings();

        $this->assertTrue(is_null($result) || is_array($result));
    }

    public function testGetLogsReturnsArrayWhenDbAvailable(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB nao disponivel');
        }

        $result = $this->service->getLogs(5);

        $this->assertIsArray($result);
    }
}
