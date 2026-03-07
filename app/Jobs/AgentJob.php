<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AutonomousAgentService;

class AgentJob
{
    public function run(): void
    {
        logger()->info('Starting AgentJob', ['job' => 'AgentJob']);
        
        $service = new AutonomousAgentService();
        $agents = $service->getAgents();
        
        foreach ($agents as $agent) {
            if ($agent['status'] === 'active') {
                logger()->info("Running agent", [
                    'agent' => $agent['name'],
                    'code' => $agent['code']
                ]);
                
                $result = $service->runAgent($agent['code']);
                
                if ($result['success']) {
                    logger()->info("Agent completed successfully", [
                        'agent' => $agent['name']
                    ]);
                } else {
                    logger()->error("Agent failed", [
                        'agent' => $agent['name'],
                        'error' => $result['error'] ?? 'Unknown'
                    ]);
                }
            } else {
                logger()->debug("Skipping inactive agent", [
                    'agent' => $agent['name'],
                    'status' => $agent['status']
                ]);
            }
        }
        
        logger()->info('AgentJob finished', [
            'total_agents' => count($agents)
        ]);
    }
}
