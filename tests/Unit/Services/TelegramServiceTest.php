<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TelegramService;

/**
 * @covers \App\Services\TelegramService
 */
class TelegramServiceTest extends TestCase
{
    private TelegramService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Guarantee env vars are absent so isEnabled() returns false
        unset($_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
        $this->service = new TelegramService();
    }

    protected function tearDown(): void
    {
        unset($_ENV['TELEGRAM_BOT_TOKEN'], $_ENV['TELEGRAM_CHAT_ID']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Class structure
    // -----------------------------------------------------------------------

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TelegramService::class));
    }

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'isEnabled',
            'sendMessage',
            'sendNewCompetitorNotification',
            'sendNewProductNotification',
            'sendTokenExpiringNotification',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(TelegramService::class, $method),
                "TelegramService deve ter o método {$method}()"
            );
        }
    }

    // -----------------------------------------------------------------------
    // isEnabled()
    // -----------------------------------------------------------------------

    public function testIsEnabledReturnsFalseWhenNotConfigured(): void
    {
        $this->assertFalse($this->service->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenBothEnvVarsPresent(): void
    {
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'fake-bot-token-12345';
        $_ENV['TELEGRAM_CHAT_ID'] = '-100987654321';

        $service = new TelegramService();

        $this->assertTrue($service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenOnlyTokenPresent(): void
    {
        $_ENV['TELEGRAM_BOT_TOKEN'] = 'fake-bot-token-12345';
        unset($_ENV['TELEGRAM_CHAT_ID']);

        $service = new TelegramService();

        $this->assertFalse($service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenOnlyChatIdPresent(): void
    {
        unset($_ENV['TELEGRAM_BOT_TOKEN']);
        $_ENV['TELEGRAM_CHAT_ID'] = '-100987654321';

        $service = new TelegramService();

        $this->assertFalse($service->isEnabled());
    }

    // -----------------------------------------------------------------------
    // sendMessage()
    // -----------------------------------------------------------------------

    public function testSendMessageReturnsBool(): void
    {
        $result = $this->service->sendMessage('Test message');
        $this->assertIsBool($result);
    }

    public function testSendMessageReturnsFalseWhenDisabled(): void
    {
        $result = $this->service->sendMessage('Test message');
        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // sendNewCompetitorNotification()
    // -----------------------------------------------------------------------

    public function testSendNewCompetitorNotificationReturnsBool(): void
    {
        $result = $this->service->sendNewCompetitorNotification([
            'seller_nickname' => 'Concorrente XPTO',
            'category_id' => 'MLB1234',
            'total_items' => 42,
        ]);

        $this->assertIsBool($result);
    }

    public function testSendNewCompetitorNotificationReturnsFalseWhenDisabled(): void
    {
        $result = $this->service->sendNewCompetitorNotification([
            'seller_nickname' => 'Concorrente XPTO',
            'category_id' => 'MLB1234',
            'total_items' => 42,
        ]);

        $this->assertFalse($result);
    }

    public function testSendNewCompetitorNotificationAcceptsEmptyArray(): void
    {
        $result = $this->service->sendNewCompetitorNotification([]);
        $this->assertIsBool($result);
    }

    // -----------------------------------------------------------------------
    // sendNewProductNotification()
    // -----------------------------------------------------------------------

    public function testSendNewProductNotificationReturnsBool(): void
    {
        $result = $this->service->sendNewProductNotification([
            'title' => 'Bagageiro CG 160',
            'price' => 189.90,
            'seller_nickname' => 'TopMotosPecas',
        ]);

        $this->assertIsBool($result);
    }

    public function testSendNewProductNotificationReturnsFalseWhenDisabled(): void
    {
        $result = $this->service->sendNewProductNotification([
            'title' => 'Bagageiro CG 160',
            'price' => 189.90,
        ]);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // sendTokenExpiringNotification()
    // -----------------------------------------------------------------------

    public function testSendTokenExpiringNotificationReturnsBool(): void
    {
        $result = $this->service->sendTokenExpiringNotification([
            'nickname' => 'conta-vendas',
            'expires_in' => '3 horas',
        ]);

        $this->assertIsBool($result);
    }

    public function testSendTokenExpiringNotificationReturnsFalseWhenDisabled(): void
    {
        $result = $this->service->sendTokenExpiringNotification([
            'nickname' => 'conta-vendas',
        ]);

        $this->assertFalse($result);
    }

    public function testSendTokenExpiringNotificationAcceptsEmptyArray(): void
    {
        $result = $this->service->sendTokenExpiringNotification([]);
        $this->assertIsBool($result);
    }
}
