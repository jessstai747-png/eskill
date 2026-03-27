<?php

declare(strict_types=1);

namespace App\Services\AI\ML;

use Monolog\Logger;
use Exception;

/**
 * Serviço de integração com o Microserviço Python (FastAPI) de NLP.
 * Responsável por classificar intenções e score de urgência de mensagens do Mercado Livre.
 */
class NLPIntegrationService
{
    private string $baseUrl;
    private string $apiKey;
    private Logger $logger;
    private int $timeout;

    public function __construct(Logger $logger, string $baseUrl = 'http://127.0.0.1:8000', string $apiKey = 'dev-secret-key', int $timeout = 3)
    {
        $this->logger = $logger;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * @param string $messageId
     * @param string $text
     * @param string $itemId
     * @param float $price
     * @return array{intent: string, urgency_score: float, confidence: float, is_critical: bool}|null
     */
    public function predictIntent(string $messageId, string $text, string $itemId, float $price): ?array
    {
        try {
            $payload = [
                'message_id' => $messageId,
                'text' => $text,
                'item_id' => $itemId,
                'price' => $price
            ];

            $ch = curl_init("{$this->baseUrl}/api/v1/predict");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                "X-API-Key: {$this->apiKey}"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                $this->logger->error("Falha na integracao com NLP Service: {$error} | HTTP Code: {$httpCode}");
                return null;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error("Resposta invalida do NLP Service (JSON error)");
                return null;
            }

            return [
                'intent' => $data['intent'] ?? 'unknown',
                'urgency_score' => (float)($data['urgency_score'] ?? 0.0),
                'confidence' => (float)($data['confidence'] ?? 0.0),
                'is_critical' => (bool)($data['is_critical'] ?? false)
            ];

        } catch (Exception $e) {
            $this->logger->error("Erro inexperado no NLPIntegrationService: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se o microserviço Python está rodando
     */
    public function healthCheck(): bool
    {
        try {
            $ch = curl_init("{$this->baseUrl}/health");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            return false;
        }
    }
}
