<?php

namespace App\Controllers;

use App\Services\QuestionService;

class QuestionController extends BaseController
{
    private QuestionService $service;
    private int $accountId;

    public function __construct()
    {
        parent::__construct();
        // Verificar autenticação e obter account_id da sessão ou request
        // Assumindo que o middleware de auth já validou e temos o user
        // Aqui simplificamos pegando o primeiro account_id ativo do usuário ou da sessão

        // Em um cenário real, o account_id viria do header ou sessão selecionada
        $this->accountId = $_SESSION['active_ml_account_id'] ?? 0;

        if (!$this->accountId) {
            // Se não houver conta na sessão, retornamos 0.
            // O endpoint específico deve validar se precisa de conta e retornar 401 caso positivo.
            // REMOVED INSECURE BACKDOOR: $headers['X-Account-Id'] trust
            $this->accountId = 0;
        }

        $this->service = new QuestionService($this->accountId ?: null);
    }

    /**
     * GET /api/questions
     * Lista perguntas
     */
    public function index()
    {
        header('Content-Type: application/json');

        $filters = [
            'status' => $this->request->get('status'),
            'item_id' => $this->request->get('item_id'),
            'limit' => $this->request->getInt('limit', 50),
            'offset' => $this->request->getInt('offset', 0),
            'account_id' => $this->request->get('account_id'),
            'allow_local_cache' => $this->request->get('allow_local_cache'),
            'source' => $this->request->get('source')
        ];

        // Se não for "all" e não tiver conta selecionada, erro
        if (($filters['account_id'] !== 'all') && !$this->accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Conta ML não selecionada']);
            return;
        }

        $result = $this->service->getQuestions($filters);

        if (isset($result['error'])) {
            $error = (string)$result['error'];
            if (in_array($error, ['missing_seller_id', 'local_cache_required'], true)) {
                http_response_code(422);
            } elseif (in_array($error, ['db_unavailable', 'network_disabled', 'circuit_breaker_open'], true)) {
                http_response_code(503);
            } elseif ($error === 'missing_token') {
                http_response_code(401);
            } else {
                http_response_code(502);
            }
        }

        echo json_encode($result);
    }

    /**
     * GET /api/questions/{id}
     * Detalhes da pergunta
     */
    public function show(string $id)
    {
        header('Content-Type: application/json');
        $options = [];
        $allowLocalCache = $this->request->get('allow_local_cache');
        if ($allowLocalCache !== null) {
            $options['allow_local_cache'] = $allowLocalCache;
        }

        $source = $this->request->get('source');
        if ($source !== null) {
            $options['source'] = $source;
        }

        $result = $this->service->getQuestion($id, $options);

        if (isset($result['error'])) {
            $error = (string)$result['error'];
            if (in_array($error, ['not_found', 'question_not_found'], true)) {
                http_response_code(404);
            } elseif (in_array($error, ['db_unavailable', 'network_disabled', 'circuit_breaker_open'], true)) {
                http_response_code(503);
            } elseif ($error === 'missing_token') {
                http_response_code(401);
            } else {
                http_response_code(502);
            }
        }

        echo json_encode($result);
    }

    /**
     * POST /api/questions/{id}/answer
     * Responder pergunta
     */
    public function answer(string $id)
    {
        header('Content-Type: application/json');

        if (!$this->accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Conta ML não selecionada']);
            return;
        }

        $data = $this->request->json();
        $text = $data['text'] ?? '';

        if (empty($text)) {
            http_response_code(400);
            echo json_encode(['error' => 'Texto da resposta é obrigatório']);
            return;
        }

        $result = $this->service->answerQuestion($id, $text);
        echo json_encode($result);
    }

    /**
     * DELETE /api/questions/{id}
     * Excluir pergunta
     */
    public function delete(string $id)
    {
        header('Content-Type: application/json');

        if (!$this->accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Conta ML não selecionada']);
            return;
        }

        $result = $this->service->deleteQuestion($id);
        echo json_encode($result);
    }

    /**
     * GET /api/questions/unanswered/count
     * Contagem de não respondidas
     */
    public function countUnanswered()
    {
        header('Content-Type: application/json');

        if (!$this->accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Conta ML não selecionada']);
            return;
        }

        $count = $this->service->getUnansweredCount();
        echo json_encode(['count' => $count]);
    }

    /**
     * POST /api/questions/{id}/analyze
     * Analisa sentimento e intenção com IA
     */
    public function analyze(string $id)
    {
        header('Content-Type: application/json');

        if (!$this->accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Conta ML não selecionada']);
            return;
        }

        $result = $this->service->analyzeQuestion($id);
        echo json_encode($result);
    }

    /**
     * POST /api/questions/{id}/draft
     * Gera rascunho de resposta com IA
     */
    public function draft(string $id)
    {
        header('Content-Type: application/json');

        if (!$this->accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Conta ML não selecionada']);
            return;
        }

        $result = $this->service->generateDraftAnswer($id);
        echo json_encode($result);
    }
}
