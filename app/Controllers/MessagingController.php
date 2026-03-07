<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\MessagingService;

/**
 * Messaging Controller
 * 
 * REST API para sistema de mensagens
 * 
 * Endpoints:
 * - GET    /api/messaging/:accountId/conversations
 * - GET    /api/messaging/:accountId/messages/:threadId
 * - POST   /api/messaging/:accountId/send
 * - POST   /api/messaging/:accountId/template
 * - GET    /api/messaging/:accountId/templates
 * - POST   /api/messaging/:accountId/send-template
 * - POST   /api/messaging/:accountId/auto-response
 * - GET    /api/messaging/:accountId/auto-responses
 * - POST   /api/messaging/:accountId/webhook
 * - GET    /api/messaging/:accountId/stats
 */
class MessagingController
{
    private MessagingService $messagingService;
    private Request $request;

    public function __construct(int $accountId)
    {
        $this->request = new Request();
        $this->messagingService = new MessagingService($accountId);
    }

    /**
     * GET /api/messaging/:accountId/conversations?limit=50&offset=0
     * Lista conversas
     */
    public function listConversations(): void
    {
        header('Content-Type: application/json');

        $limit = $this->request->getInt('limit', 50);
        $offset = $this->request->getInt('offset', 0);
        $filters = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        $result = $this->messagingService->listConversations($filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/messaging/:accountId/messages/:threadId?limit=100
     * Obtém mensagens de uma thread
     */
    public function getMessages(string $threadId): void
    {
        header('Content-Type: application/json');

        $result = $this->messagingService->getMessages($threadId);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/messaging/:accountId/send
     * Envia mensagem
     * 
     * Body: {
     *   "to": "USER_ID",
     *   "text": "Olá! Seu produto foi enviado.",
     *   "context": {
     *     "order_id": "ORDER123",
     *     "item_id": "MLB456"
     *   }
     * }
     */
    public function sendMessage(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['to'], $data['text'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'to and text are required'
            ]);
            return;
        }

        $result = $this->messagingService->sendMessage(
            $data['to'],
            $data['text'],
            $data['context'] ?? null
        );
        
        http_response_code($result['success'] ? 201 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/messaging/:accountId/template
     * Cria template de mensagem
     * 
     * Body: {
     *   "name": "Boas-vindas",
     *   "subject": "Bem-vindo!",
     *   "content": "Olá {{name}}, obrigado por comprar {{product}}!",
     *   "category": "welcome",
     *   "variables": ["name", "product"]
     * }
     */
    public function createTemplate(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name'], $data['content'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'name and content are required'
            ]);
            return;
        }

        $result = $this->messagingService->createTemplate(
            $data['name'],
            $data['content'],
            $data['subject'] ?? null,
            $data['category'] ?? 'general',
            $data['variables'] ?? []
        );
        
        http_response_code($result['success'] ? 201 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/messaging/:accountId/templates?category=welcome
     * Lista templates
     */
    public function listTemplates(): void
    {
        header('Content-Type: application/json');

        $category = $this->request->get('category');
        $filters = ['category' => $category];

        $result = $this->messagingService->listTemplates($filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/messaging/:accountId/send-template
     * Envia mensagem usando template
     * 
     * Body: {
     *   "to": "USER_ID",
     *   "template_id": 1,
     *   "variables": {
     *     "name": "João",
     *     "product": "Smartphone XYZ"
     *   }
     * }
     */
    public function sendFromTemplate(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['to'], $data['template_id'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'to and template_id are required'
            ]);
            return;
        }

        $result = $this->messagingService->sendFromTemplate(
            $data['to'],
            (int)$data['template_id'],
            $data['variables'] ?? []
        );
        
        http_response_code($result['success'] ? 201 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/messaging/:accountId/auto-response
     * Configura resposta automática
     * 
     * Body: {
     *   "trigger_keyword": "prazo",
     *   "response_message": "O prazo de entrega é de 3-5 dias úteis.",
     *   "enabled": true
     * }
     */
    public function setAutoResponse(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['trigger_keyword'], $data['response_message'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'trigger_keyword and response_message are required'
            ]);
            return;
        }

        $result = $this->messagingService->setAutoResponse(
            $data['trigger_keyword'],
            $data['response_message'],
            $data['enabled'] ?? true
        );
        
        http_response_code($result['success'] ? 201 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/messaging/:accountId/auto-responses
     * Lista respostas automáticas
     */
    public function listAutoResponses(): void
    {
        header('Content-Type: application/json');

        $result = $this->messagingService->listAutoResponses();
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/messaging/:accountId/webhook
     * Processa webhook de mensagem recebida
     * 
     * Body: {
     *   "message_id": "MSG123",
     *   "text": "qual o prazo de entrega?",
     *   "from": "USER_ID",
     *   "thread_id": "THREAD123"
     * }
     */
    public function processWebhook(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['message_id'], $data['text'], $data['from'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'message_id, text, and from are required'
            ]);
            return;
        }

        $result = $this->messagingService->processIncomingMessage(
            $data['message_id'],
            $data['text'],
            $data['from'],
            $data['thread_id'] ?? null
        );
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/messaging/:accountId/stats?start_date=2024-01-01&end_date=2024-12-31
     * Estatísticas de mensagens
     */
    public function getStats(): void
    {
        header('Content-Type: application/json');

        $startDate = $this->request->get('start_date', date('Y-m-01')) ?? date('Y-m-01');
        $endDate = $this->request->get('end_date', date('Y-m-t')) ?? date('Y-m-t');
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $result = $this->messagingService->getMessagingStats($filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
