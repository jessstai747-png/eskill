<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\ChatbotAIService;

/**
 * Chatbot AI Controller
 * 
 * REST API para chatbot inteligente
 * 
 * Endpoints:
 * - POST /api/chatbot/:accountId/process
 * - GET  /api/chatbot/:accountId/stats
 */
class ChatbotAIController
{
    private ChatbotAIService $chatbotService;
    private Request $request;

    public function __construct(int $accountId)
    {
        $this->chatbotService = new ChatbotAIService($accountId);
        $this->request = new Request();
    }

    /**
     * POST /api/chatbot/:accountId/process
     * Processa mensagem e gera resposta inteligente
     * 
     * Body: {
     *   "message": "Onde está meu pedido?",
     *   "from_user": "USER_ID",
     *   "context": {
     *     "order_id": "123456",
     *     "item_id": "MLB789"
     *   }
     * }
     */
    public function processMessage(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['message'], $data['from_user'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'message and from_user are required'
            ]);
            return;
        }

        $result = $this->chatbotService->processMessage(
            $data['message'],
            $data['from_user'],
            $data['context'] ?? []
        );
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/chatbot/:accountId/stats?days=30
     * Estatísticas do chatbot
     */
    public function getStats(): void
    {
        header('Content-Type: application/json');

        $days = $this->request->getInt('days', 30);
        
        $result = $this->chatbotService->getStats($days);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
