<?php

namespace App\Services\AI\Core\Harness;

use App\Database;
use PDO;

/**
 * Manages the persistent state of the Agent Harness.
 * Tracks session status, heartbeat, and context implementation.
 */
class StateManager
{
    private PDO $db;
    private string $agentName;
    private string $sessionId;

    public function __construct(string $agentName = 'default_agent')
    {
        $this->db = Database::getInstance();
        $this->agentName = $agentName;
        $this->sessionId = uniqid('session_', true);
        
        $this->initializeSession();
    }

    /**
     * Create initial session record.
     */
    private function initializeSession(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_harness_state 
            (agent_name, session_id, status, started_at)
            VALUES (?, ?, 'initializing', NOW())
        ");
        $stmt->execute([$this->agentName, $this->sessionId]);
    }

    /**
     * Update current status.
     */
    public function updateStatus(string $status, ?string $featureId = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE ai_harness_state 
            SET status = ?, 
                current_feature_id = ?,
                last_heartbeat = NOW()
            WHERE session_id = ?
        ");
        $stmt->execute([$status, $featureId, $this->sessionId]);
    }

    /**
     * Record heartbeat and memory usage.
     */
    public function heartbeat(): void
    {
        $memory = memory_get_usage(true);
        $stmt = $this->db->prepare("
            UPDATE ai_harness_state 
            SET last_heartbeat = NOW(),
                memory_usage = ?
            WHERE session_id = ?
        ");
        $stmt->execute([$memory, $this->sessionId]);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get the latest state for an agent.
     */
    public function getLastState(string $agentName = 'AgentHarness'): ?array
    {
        // Get the most recent session state
        $stmt = $this->db->prepare("
            SELECT * FROM ai_harness_state 
            WHERE agent_name = ? 
            ORDER BY last_heartbeat DESC, created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$agentName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
}
