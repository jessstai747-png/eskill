<?php

namespace App\Controllers;

use App\Services\Agent\AgentService;

/**
 * Controller para API de Long-Running Agents
 * 
 * Endpoints disponíveis:
 * - POST /api/agent/projects/start - Inicia novo projeto
 * - POST /api/agent/projects/{id}/session - Executa sessão de coding
 * - GET /api/agent/projects/{id}/status - Status do projeto
 * - POST /api/agent/projects/{id}/test - Testa uma feature
 * - GET /api/agent/projects - Lista projetos
 * - GET /api/agent/projects/{id}/progress - Histórico de progresso
 * - GET /api/agent/projects/{id}/features - Lista de features
 */
class AgentController extends BaseController
{
    private AgentService $agentService;
    
    public function __construct()
    {
        parent::__construct();
        $this->agentService = new AgentService();
    }
    
    /**
     * Inicia um novo projeto com agent
     * POST /api/agent/projects/start
     * 
     * Body:
     * {
     *   "name": "My Project",
     *   "description": "Build a todo app with user auth",
     *   "category": "dashboard",
     *   "requirements": [
     *     "User can register and login",
     *     "User can create, edit, delete todos",
     *     "Todos have categories and due dates"
     *   ]
     * }
     */
    public function startProject(): void
    {
        header('Content-Type: application/json');
        
        $data = $this->request->json();
        
        if (empty($data['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Description is required']);
            return;
        }
        
        try {
            $result = $this->agentService->startProject($data);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Executa uma sessão de coding
     * POST /api/agent/projects/{id}/session
     */
    public function runCodingSession(int $projectId): void
    {
        header('Content-Type: application/json');
        
        try {
            $result = $this->agentService->runCodingSession($projectId);
            
            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Obtém status do projeto
     * GET /api/agent/projects/{id}/status
     */
    public function getStatus(int $projectId): void
    {
        header('Content-Type: application/json');
        
        try {
            $status = $this->agentService->getProjectStatus($projectId);
            
            echo json_encode([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Testa uma feature específica
     * POST /api/agent/projects/{id}/test
     * 
     * Body:
     * {
     *   "feature_id": "F1"
     * }
     */
    public function testFeature(int $projectId): void
    {
        header('Content-Type: application/json');
        
        $data = $this->request->json();
        
        if (empty($data['feature_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'feature_id is required']);
            return;
        }
        
        try {
            $result = $this->agentService->testFeature($projectId, $data['feature_id']);
            
            echo json_encode([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Lista todos os projetos
     * GET /api/agent/projects
     */
    public function listProjects(): void
    {
        header('Content-Type: application/json');
        
        try {
            $projects = $this->agentService->listProjects();
            echo json_encode([
                'success' => true,
                'data' => [
                    'projects' => $projects,
                    'total' => count($projects),
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    /**
     * Lista agentes autônomos
     * GET /api/agent/autonomous
     */
    public function listAutonomous(): void
    {
        header('Content-Type: application/json');
        $service = new \App\Services\AutonomousAgentService();
        echo json_encode(['success' => true, 'data' => $service->getAgents()]);
    }

    /**
     * Lista logs de agentes
     * GET /api/agent/autonomous/logs
     */
    public function listLogs(): void
    {
        header('Content-Type: application/json');
        $service = new \App\Services\AutonomousAgentService();
        $code = $this->request->get('code');
        echo json_encode(['success' => true, 'data' => $service->getLogs($code, 100)]);
    }
}
