<?php

namespace App\Agents;

use App\Database;

abstract class BaseAgent
{
    protected string $code;
    protected \PDO $db;
    protected array $config = [];

    public function __construct(string $code)
    {
        $this->code = $code;
        $this->db = Database::getInstance();
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $stmt = $this->db->prepare("SELECT config FROM ai_agents WHERE code = ?");
        $stmt->execute([$this->code]);
        $json = $stmt->fetchColumn();
        if ($json) {
            $this->config = json_decode($json, true) ?? [];
        }
    }

    abstract public function run(): void;

    protected function log(string $level, string $message, array $context = []): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_agent_logs (agent_code, level, message, context)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->code,
            $level,
            $message,
            json_encode($context)
        ]);
    }
    
    protected function updateLastRun(): void
    {
        $stmt = $this->db->prepare("UPDATE ai_agents SET last_run_at = NOW() WHERE code = ?");
        $stmt->execute([$this->code]);
    }
}
