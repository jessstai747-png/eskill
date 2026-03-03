<?php

declare(strict_types=1);

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
     * Check if the current request uses the test-token in a testing environment.
     * This bypass lives here (not in ApiAuthMiddleware) because render is
     * a test-harness-only endpoint — it must never be reached in production.
     */
    private function isTestEnvToken(): bool
    {
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '');
        if ($env !== 'testing') {
            return false;
        }
        // Try both getallheaders() and $_SERVER for maximum compatibility
        // (PHP built-in server may expose Authorization via either path)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader === '') {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        return (bool) preg_match('/^Bearer\s+test-token$/i', $authHeader);
    }

    /**
     * Inject trusted auth state for the testing environment token.
     */
    private function setTestEnvAuthState(): void
    {
        $_SERVER['API_TOKEN_DATA'] = ['user_id' => 1, 'scopes' => ['*']];
        $_SERVER['API_USER_ID'] = 1;
    }
    
    /**
     * POST /api/render
     * Create a new render job
     */
    public function create(): void
    {
        if ($this->isTestEnvToken()) {
            $this->setTestEnvAuthState();
            $this->handleCreate();
            return;
        }
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
        if ($this->isTestEnvToken()) {
            $this->setTestEnvAuthState();
            $this->handleStatus($jobId);
            return;
        }
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
        if ($this->isTestEnvToken()) {
            $this->setTestEnvAuthState();
            $this->handleCleanup();
            return;
        }
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
