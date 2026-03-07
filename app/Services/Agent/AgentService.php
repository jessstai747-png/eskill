<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Database;
use PDO;

/**
 * Serviço de gestão de projetos de agentes AI
 *
 * Gerencia ciclo de vida de projetos dos agentes autônomos:
 * criação, sessões de coding, status e testes.
 * Tabelas: agent_projects, agent_features, agent_progress_log
 */
class AgentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Iniciar novo projeto
     */
    public function startProject(array $data): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO agent_projects (name, description, status, config, created_at)
             VALUES (:name, :description, 'active', :config, NOW())"
        );
        $stmt->execute([
            'name' => $data['name'] ?? 'Projeto sem nome',
            'description' => $data['description'] ?? '',
            'config' => json_encode($data['config'] ?? []),
        ]);

        $projectId = $this->db->lastInsertId();

        return [
            'project_id' => $projectId,
            'status' => 'active',
            'message' => 'Projeto criado com sucesso',
        ];
    }

    /**
     * Executar sessão de coding
     */
    public function runCodingSession(int $projectId): array
    {
        $project = $this->getProject($projectId);
        if (!$project) {
            throw new \Exception('Projeto não encontrado');
        }

        // Registrar sessão
        $this->db->prepare(
            "INSERT INTO agent_progress_log (project_id, action, details, created_at)
             VALUES (:project_id, 'coding_session', :details, NOW())"
        )->execute([
            'project_id' => $projectId,
            'details' => json_encode(['started_at' => date('Y-m-d H:i:s')]),
        ]);

        return [
            'project_id' => $projectId,
            'session_status' => 'started',
            'project_name' => $project['name'],
        ];
    }

    /**
     * Obter status do projeto
     */
    public function getProjectStatus(int $projectId): array
    {
        $project = $this->getProject($projectId);
        if (!$project) {
            throw new \Exception('Projeto não encontrado');
        }

        // Progresso
        $stmt = $this->db->prepare(
            "SELECT * FROM agent_progress_log
             WHERE project_id = :id ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute(['id' => $projectId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Features
        $features = [];
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM agent_features WHERE project_id = :id"
            );
            $stmt->execute(['id' => $projectId]);
            $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Tabela pode não existir
        }

        return [
            'project' => $project,
            'recent_logs' => $logs,
            'features' => $features,
        ];
    }

    /**
     * Testar feature de um projeto
     */
    public function testFeature(int $projectId, int $featureId): array
    {
        $this->db->prepare(
            "INSERT INTO agent_progress_log (project_id, action, details, created_at)
             VALUES (:project_id, 'test_feature', :details, NOW())"
        )->execute([
            'project_id' => $projectId,
            'details' => json_encode(['feature_id' => $featureId]),
        ]);

        return [
            'project_id' => $projectId,
            'feature_id' => $featureId,
            'test_status' => 'queued',
        ];
    }

    /**
     * Listar todos os projetos
     */
    public function listProjects(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM agent_projects ORDER BY created_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getProject(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM agent_projects WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
