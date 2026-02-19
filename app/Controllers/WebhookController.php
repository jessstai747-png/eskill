<?php

namespace App\Controllers;

use App\Database;

class WebhookController
{
    /**
     * Recebe notificações do Mercado Livre
     */
    public function receive(): void
    {
        // Se for GET, o ML pode estar verificando a URL? Geralmente é POST.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            http_response_code(200);
            echo "Webhook Endpoint Active";
            return;
        }

        $body = file_get_contents('php://input');
        $headers = getallheaders();
        
        // Log raw request for debug (rotate this log frequently!)
        // file_put_contents(__DIR__ . '/../../storage/logs/webhook_raw.log', $body . PHP_EOL, FILE_APPEND);

        $data = json_decode($body, true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        // Validação básica (em produção deve-se validar x-signature se configurado)
        // Por enquanto, vamos confiar no ID do recurso e processar assincronamente.
        
        $topic = $data['topic'] ?? $data['type'] ?? 'unknown';
        $resource = $data['resource'] ?? '';
        $userId = $data['user_id'] ?? null;
        $applicationId = $data['application_id'] ?? null;
        
        // Salvar no banco para processamento assíncrono (Queue Pattern Simples)
        try {
            $db = Database::getInstance();
            
            // Garantir que tabela existe (failsafe, idealmente via migration)
            $this->ensureTableExists($db);
            
            $stmt = $db->prepare("
                INSERT INTO webhook_events (topic, resource, user_id, application_id, payload, created_at, status)
                VALUES (?, ?, ?, ?, ?, NOW(), 'pending')
            ");
            
            $stmt->execute([
                $topic,
                $resource,
                $userId,
                $applicationId,
                $body
            ]);
            
            http_response_code(200);
            echo json_encode(['status' => 'received']);
            
        } catch (\Exception $e) {
            log_error('Erro ao processar webhook ML', [
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
    }
    
    private function ensureTableExists(\PDO $db): void
    {
        // Criação "Lazy" da tabela para garantir funcionamento imediato
        $sql = "CREATE TABLE IF NOT EXISTS webhook_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic VARCHAR(50),
            resource VARCHAR(255),
            user_id BIGINT,
            application_id BIGINT,
            payload TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME,
            processed_at DATETIME NULL,
            attempts INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $db->exec($sql);
    }
}
