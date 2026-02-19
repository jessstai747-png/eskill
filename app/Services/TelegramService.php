<?php

namespace App\Services;

/**
 * Serviço de integração com Telegram Bot API
 *
 * Stub: Telegram desabilitado por padrão até configurar TELEGRAM_BOT_TOKEN e TELEGRAM_CHAT_ID.
 * Para habilitar, defina essas variáveis no .env.
 */
class TelegramService
{
    private ?string $botToken;
    private ?string $chatId;

    public function __construct()
    {
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $this->chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? null;
    }

    /**
     * Verificar se o Telegram está habilitado
     */
    public function isEnabled(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Enviar mensagem genérica
     */
    public function sendMessage(string $message): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Notificar novo concorrente
     */
    public function sendNewCompetitorNotification(array $alertData): bool
    {
        $msg = "🔔 <b>Novo Concorrente Detectado</b>\n";
        $msg .= "Vendedor: " . ($alertData['seller_nickname'] ?? 'N/A') . "\n";
        $msg .= "Categoria: " . ($alertData['category_id'] ?? 'N/A') . "\n";
        $msg .= "Itens: " . ($alertData['total_items'] ?? 0);
        return $this->sendMessage($msg);
    }

    /**
     * Notificar novo produto na categoria
     */
    public function sendNewProductNotification(array $alertData): bool
    {
        $msg = "📦 <b>Novo Produto Detectado</b>\n";
        $msg .= "Título: " . ($alertData['title'] ?? 'N/A') . "\n";
        $msg .= "Preço: R$ " . number_format($alertData['price'] ?? 0, 2, ',', '.') . "\n";
        $msg .= "Vendedor: " . ($alertData['seller_nickname'] ?? 'N/A');
        return $this->sendMessage($msg);
    }

    /**
     * Notificar token expirando
     */
    public function sendTokenExpiringNotification(array $accountData): bool
    {
        $msg = "⚠️ <b>Token ML Expirando</b>\n";
        $msg .= "Conta: " . ($accountData['nickname'] ?? 'N/A') . "\n";
        $msg .= "Expira em: " . ($accountData['expires_in'] ?? 'N/A') . "\n";
        $msg .= "Renove em: /auth/connect";
        return $this->sendMessage($msg);
    }
}
