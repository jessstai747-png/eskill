<?php

namespace App\Services;

use App\Database;
use PDO;
use Exception;

/**
 * Serviço de integração com Mercado Pago
 *
 * Gerencia credenciais e conexão com a API do Mercado Pago
 * para processamento de pagamentos de pacotes EAN.
 */
class MercadoPagoService
{
    private PDO $db;
    private ?string $accessToken;
    private ?EncryptionService $encryption = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        try {
            $this->encryption = new EncryptionService();
        } catch (\Throwable $e) {
            $this->encryption = null;
        }
        $this->accessToken = $this->getCredential('mp_access_token');
    }

    /**
     * Salvar credenciais do Mercado Pago
     */
    public static function saveCredentials(string $accessToken, string $publicKey = '', string $webhookSecret = ''): void
    {
        $db = Database::getInstance();

        $encryption = null;
        try {
            $encryption = new EncryptionService();
        } catch (\Throwable $e) {
            $encryption = null;
        }

        $credentials = [
            'mp_access_token' => $accessToken,
            'mp_public_key' => $publicKey,
            'mp_webhook_secret' => $webhookSecret,
        ];

        foreach ($credentials as $key => $value) {
            if (empty($value)) {
                continue;
            }

            $storedValue = $value;
            if ($encryption !== null && in_array($key, ['mp_access_token', 'mp_webhook_secret'], true)) {
                $storedValue = $encryption->encrypt($value);
            }

            $stmt = $db->prepare(
                "INSERT INTO ean_settings (setting_key, setting_value, setting_type, description, updated_at)
                 VALUES (:key, :value, 'string', :desc, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = NOW()"
            );
            $stmt->execute([
                'key' => $key,
                'value' => $storedValue,
                'desc' => "Mercado Pago - {$key}",
                'value2' => $storedValue,
            ]);
        }
    }

    /**
     * Testar conexão com Mercado Pago
     */
    public function testConnection(): array
    {
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'error' => 'Access token não configurado',
            ];
        }

        try {
            $ch = curl_init('https://api.mercadopago.com/v1/payment_methods');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => "cURL error: {$error}"];
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return [
                    'success' => true,
                    'message' => 'Conexão OK',
                    'payment_methods_count' => is_array($data) ? count($data) : 0,
                ];
            }

            return [
                'success' => false,
                'error' => "HTTP {$httpCode}",
                'response' => json_decode($response, true),
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consultar pagamento no Mercado Pago por ID.
     */
    public function getPaymentById(string $paymentId): array
    {
        if (empty($this->accessToken)) {
            return [
                'success' => false,
                'error' => 'Access token não configurado',
            ];
        }

        try {
            $url = 'https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    'success' => false,
                    'error' => 'cURL error: ' . $error,
                ];
            }

            $decoded = is_string($response) ? json_decode($response, true) : null;
            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                return [
                    'success' => true,
                    'data' => $decoded,
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP ' . $httpCode,
                'response' => $decoded,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica se existe token configurado.
     */
    public function hasCredentials(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Obter credencial salva
     */
    private function getCredential(string $key): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT setting_value FROM ean_settings WHERE setting_key = :key"
        );
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        if (!$value) {
            return null;
        }

        $value = (string)$value;

        if ($this->encryption !== null && $this->encryption->isEncrypted($value)) {
            try {
                return $this->encryption->decrypt($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $value;
    }
}
