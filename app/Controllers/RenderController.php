<?php

namespace App\Controllers;

use App\Services\RenderHarness;

/**
 * Render Controller
 * Handles video rendering requests for E2E testing
 */
class RenderController extends BaseController
{
    private ?RenderHarness $harness = null;
    
    public function __construct()
    {
        parent::__construct();
        
        // Initialize harness if enabled
        if (RenderHarness::isEnabled()) {
            $this->harness = new RenderHarness();
        }
    }
    
    /**
     * POST /api/render
     * Create a new render job
     */
    public function create(): void
    {
        // Apply API authentication middleware
        $auth = new \App\Middleware\ApiAuthMiddleware();
        $auth->handle(function() {
            $this->handleCreate();
        });
    }
    
    /**
     * Handle create logic after authentication
     */
    private function handleCreate(): void
    {
        header('Content-Type: application/json');
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid JSON payload',
            ]);
            return;
        }
        
        // Use harness if enabled, otherwise return error
        if ($this->harness) {
            $result = $this->harness->render($input);
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
        } else {
            http_response_code(501);
            echo json_encode([
                'success' => false,
                'error' => 'Render service not available. Enable RENDER_HARNESS for testing.',
            ]);
        }
    }
    
    /**
     * GET /api/render/:jobId
     * Get render job status
     */
    public function status(string $jobId): void
    {
        // Apply API authentication middleware
        $auth = new \App\Middleware\ApiAuthMiddleware();
        $auth->handle(function() use ($jobId) {
            $this->handleStatus($jobId);
        });
    }
    
    /**
     * Handle status logic after authentication
     */
    private function handleStatus(string $jobId): void
    {
        header('Content-Type: application/json');
        
        if ($this->harness) {
            $result = $this->harness->getJobStatus($jobId);
            echo json_encode($result);
        } else {
            http_response_code(501);
            echo json_encode([
                'success' => false,
                'error' => 'Render service not available',
            ]);
        }
    }
    
    /**
     * DELETE /api/render/cleanup
     * Clean up old mock renders (test utility)
     */
    public function cleanup(): void
    {
        // Apply API authentication middleware
        $auth = new \App\Middleware\ApiAuthMiddleware();
        $auth->handle(function() {
            $this->handleCleanup();
        });
    }
    
    /**
     * Handle cleanup logic after authentication
     */
    private function handleCleanup(): void
    {
        header('Content-Type: application/json');
        
        if ($this->harness) {
            $count = $this->harness->cleanup();
            echo json_encode([
                'success' => true,
                'cleaned' => $count,
            ]);
        } else {
            http_response_code(501);
            echo json_encode([
                'success' => false,
                'error' => 'Render service not available',
            ]);
        }
    }
}
