<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AutonomousAgentService;

class AgentJob
{
    public function run(): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting AgentJob...\n";
        
        $service = new AutonomousAgentService();
        $agents = $service->getAgents();
        
        foreach ($agents as $agent) {
            if ($agent['status'] === 'active') {
                echo "Running Agent: {$agent['name']} ({$agent['code']})...\n";
                $result = $service->runAgent($agent['code']);
                if ($result['success']) {
                    echo "✅ Success.\n";
                } else {
                    echo "❌ Failed: " . ($result['error'] ?? 'Unknown') . "\n";
                }
            } else {
                echo "Skipping Agent: {$agent['name']} (Status: {$agent['status']})\n";
            }
        }
        
        echo "AgentJob Finished.\n";
    }
}
