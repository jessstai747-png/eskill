<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load Env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Services\Agent\AgentService;

// Mock ClaudeClient if no API key (to avoid crashing if key is missing during this test)
// But we want to test REAL connection if possible.
// If not, we rely on the Service's internal try/catch.

echo "--- Verifying Agent Service Logic ---\n";

try {
    $service = new AgentService();
    
    // Simulate Start Project
    echo "Starting Test Project...\n";
    $params = [
        'name' => 'Verification Project ' . date('Y-m-d H:i:s'),
        'description' => 'A simple test project to verify the agent system.',
        'category' => 'testing',
        'requirements' => ['Verify database', 'Verify git init']
    ];
    
    $result = $service->startProject($params);
    echo "Project Started! ID: " . $result['project_id'] . "\n";
    echo "Status: " . $result['status'] . "\n";
    echo "Features Generated: " . $result['features_count'] . "\n";
    
    // Check if Git Repo was created
    $repoPath = __DIR__ . '/../storage/agent_projects/' . $result['project_id'];
    if (is_dir($repoPath . '/.git')) {
        echo "Git Repo Verified at: $repoPath\n";
    } else {
        echo "ERROR: Git Repo not found at $repoPath\n";
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
