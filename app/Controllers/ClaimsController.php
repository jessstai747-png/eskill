<?php

namespace App\Controllers;

use App\Services\ClaimsService;

class ClaimsController extends BaseController
{
    private ClaimsService $claimsService;

    public function __construct()
    {
        // parent::__construct(); 
        $this->claimsService = new ClaimsService();
    }

    /**
     * Render Claims View
     */
    public function index(): void
    {
        require __DIR__ . '/../Views/dashboard/claims/index.php';
    }

    /**
     * API: Get Claims
     */
    public function list(): void
    {
        header('Content-Type: application/json');
        try {
            $claims = $this->claimsService->getClaims();
            echo json_encode(['success' => true, 'claims' => $claims]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Send Message
     */
    public function sendMessage(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $claimId = $data['claim_id'] ?? null;
        $message = $data['message'] ?? null;
        
        if (!$claimId || !$message) {
             http_response_code(400);
             echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
             return;
        }
        
        try {
            $this->claimsService->sendMessage($claimId, $message);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
