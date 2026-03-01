<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\TelegramService;

/**
 * Testes de notificacoes via Telegram — Fase 7.
 *
 * TelegramService nao tem dependencia de DB.
 * Leh vars de ambiente TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID.
 * Em sandbox/CI sem vars configuradas, isEnabled() retorna false
 * e sendMessage() retorna false graciosamente.
 *
 * @covers \App\Services\TelegramService
 */
class TelegramNotificationTest extends TestCase
{
    private TelegramService $telegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->telegram = new TelegramService();
    }

    // ------------------------------------------------------------------
    // Estrutura de classe (sempre executa)
    // ------------------------------------------------------------------

    public function testTelegramServiceClassExists(): void
    {
        $this->assertTrue(class_exists(TelegramService::class));
    }

    public function testTelegramServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(TelegramService::class, $this->telegram);
    }

    /** @dataProvider telegramMethodsProvider */
    public function testTelegramServiceHasMethods(string $method): void
    {
        $this->assertTrue(
            method_exists(TelegramService::class, $method),
            "TelegramService deve ter {$method}()"
        );
    }

    /** @return array<string, array{string}> */
    public static function telegramMethodsProvider(): array
    {
        return [
            'isEnabled'                        => ['isEnabled'],
            'sendMessage'                      => ['sendMessage'],
            'sendNewCompetitorNotification'    => ['sendNewCompetitorNotification'],
            'sendNewProductNotification'       => ['sendNewProductNotification'],
            'sendTokenExpiringNotification'    => ['sendTokenExpiringNotification'],
        ];
    }

    // ------------------------------------------------------------------
    // Comportamento sem token configurado (sandbox — sem env vars)
    // ------------------------------------------------------------------

    public function testIsEnabledReturnsBool(): void
    {
        $result = $this->telegram->isEnabled();
        $this->assertIsBool($result);
    }

    public function testIsEnabledReturnsFalseWhenNoEnvVars(): void
    {
        // Em sandbox sem TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID, deve retornar false
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId   = $_ENV['TELEGRAM_CHAT_ID']   ?? null;

        if (!empty($botToken) && !empty($chatId)) {
            $this->markTestSkipped('Vars do Telegram configuradas — isEnabled() pode ser true');
        }

        $this->assertFalse($this->telegram->isEnabled());
    }

    public function testSendMessageReturnsFalseWhenNotEnabled(): void
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId   = $_ENV['TELEGRAM_CHAT_ID']   ?? null;

        if (!empty($botToken) && !empty($chatId)) {
            $this->markTestSkipped('Telegram ativo — sendMessage pode enviar mensagem real');
        }

        $result = $this->telegram->sendMessage('Teste automatizado AWA Motos');
        $this->assertFalse($result);
    }

    public function testSendNewCompetitorNotificationReturnsFalseWhenNotEnabled(): void
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId   = $_ENV['TELEGRAM_CHAT_ID']   ?? null;

        if (!empty($botToken) && !empty($chatId)) {
            $this->markTestSkipped('Telegram ativo');
        }

        $alertData = [
            'competitor_name'  => 'Concorrente Teste',
            'category'         => 'Bagageiros',
            'item_id'          => 'MLB999',
            'price'            => 129.90,
        ];

        $result = $this->telegram->sendNewCompetitorNotification($alertData);
        $this->assertFalse($result);
    }

    public function testSendTokenExpiringNotificationReturnsFalseWhenNotEnabled(): void
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId   = $_ENV['TELEGRAM_CHAT_ID']   ?? null;

        if (!empty($botToken) && !empty($chatId)) {
            $this->markTestSkipped('Telegram ativo');
        }

        $accountData = [
            'account_name'   => 'AWA Motos Teste',
            'expires_in'     => '24h',
            'account_id'     => 1,
        ];

        $result = $this->telegram->sendTokenExpiringNotification($accountData);
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // Reflecao: tipos de retorno corretos
    // ------------------------------------------------------------------

    public function testIsEnabledReturnsBoolType(): void
    {
        $ref    = new \ReflectionMethod(TelegramService::class, 'isEnabled');
        $return = $ref->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame('bool', (string) $return);
    }

    public function testSendMessageReturnsBoolType(): void
    {
        $ref    = new \ReflectionMethod(TelegramService::class, 'sendMessage');
        $return = $ref->getReturnType();

        $this->assertNotNull($return);
        $this->assertSame('bool', (string) $return);
    }
}
