<?php

namespace App\Services;

use App\Database;
use Exception;

/**
 * ClonePostActionsService
 *
 * Executa ações pós-clone como Tech Sheet, SEO, Pricing
 */
class ClonePostActionsService
{
    private \PDO $db;

    /**
     * Lista de ações disponíveis para pós-clone
     */
    private array $availableActions = [
        'tech_sheet' => [
            'name' => 'Ficha Técnica',
            'description' => 'Gera e aplica ficha técnica automaticamente',
            'enabled' => true,
        ],
        'seo_optimize' => [
            'name' => 'Otimização SEO',
            'description' => 'Aplica sugestões de SEO Killer',
            'enabled' => true,
        ],
        'pricing_apply' => [
            'name' => 'Precificação',
            'description' => 'Aplica regras de precificação inteligente',
            'enabled' => true,
        ],
        'activate' => [
            'name' => 'Ativar Anúncio',
            'description' => 'Ativa o anúncio após todas as ações',
            'enabled' => true,
        ],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Retorna lista de ações disponíveis
     */
    public function getAvailableActions(): array
    {
        return $this->availableActions;
    }

    /**
     * Agenda ações pós-clone para um item
     */
    public function scheduleActions(
        string $targetItemId,
        array $actions,
        ?string $cloneJobId = null,
        ?int $clonedItemId = null
    ): array {
        $scheduled = [];

        foreach ($actions as $action) {
            $stmt = $this->db->prepare("
                INSERT INTO clone_post_actions_log
                (clone_job_id, cloned_item_id, target_item_id, action_type, status)
                VALUES (:job_id, :cloned_id, :item_id, :action, 'pending')
            ");

            $stmt->execute([
                'job_id' => $cloneJobId,
                'cloned_id' => $clonedItemId,
                'item_id' => $targetItemId,
                'action' => $action,
            ]);

            $scheduled[] = [
                'id' => (int) $this->db->lastInsertId(),
                'action' => $action,
                'status' => 'pending',
            ];
        }

        return $scheduled;
    }

    /**
     * Processa ações pendentes para um item ou job
     */
    public function processPendingActions(?string $jobId = null, ?string $itemId = null, int $limit = 50): array
    {
        $limitSql = max(1, min(500, (int)$limit));
        $sql = "SELECT * FROM clone_post_actions_log WHERE status = 'pending'";
        $params = [];

        if ($jobId) {
            $sql .= " AND clone_job_id = :job_id";
            $params['job_id'] = $jobId;
        }

        if ($itemId) {
            $sql .= " AND target_item_id = :item_id";
            $params['item_id'] = $itemId;
        }

        $sql .= " ORDER BY created_at ASC LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        $actions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $results = [];

        foreach ($actions as $action) {
            $result = $this->executeAction($action);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Executa uma ação específica
     */
    public function executeAction(array $actionLog): array
    {
        $actionId = (int) $actionLog['id'];
        $actionType = $actionLog['action_type'];
        $targetItemId = $actionLog['target_item_id'];

        // Marcar como processando
        $this->updateActionStatus($actionId, 'processing');

        try {
            $result = match ($actionType) {
                'tech_sheet' => $this->executeTechSheetAction($targetItemId),
                'seo_optimize' => $this->executeSeoOptimizeAction($targetItemId),
                'pricing_apply' => $this->executePricingAction($targetItemId),
                'activate' => $this->executeActivateAction($targetItemId),
                default => ['status' => 'skipped', 'message' => 'Ação desconhecida'],
            };

            $status = $result['status'] === 'success' ? 'completed' : 'failed';
            $this->updateActionStatus($actionId, $status, $result, $result['error'] ?? null);

            return [
                'action_id' => $actionId,
                'action_type' => $actionType,
                'target_item_id' => $targetItemId,
                'status' => $status,
                'result' => $result,
            ];
        } catch (Exception $e) {
            $this->updateActionStatus($actionId, 'failed', null, $e->getMessage());

            return [
                'action_id' => $actionId,
                'action_type' => $actionType,
                'target_item_id' => $targetItemId,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Atualiza status de uma ação
     */
    private function updateActionStatus(int $actionId, string $status, ?array $result = null, ?string $error = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_post_actions_log
            SET status = :status, result = :result, error_message = :error, processed_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $actionId,
            'status' => $status,
            'result' => $result ? json_encode($result) : null,
            'error' => $error,
        ]);
    }

    /**
     * Executa ação de Tech Sheet - dispara análise/atualização de ficha técnica
     */
    private function executeTechSheetAction(string $itemId): array
    {
        try {
            // Verificar se TechSheetService existe
            if (!class_exists(\App\Services\TechSheetService::class)) {
                return [
                    'status' => 'skipped',
                    'message' => 'TechSheetService não disponível',
                ];
            }

            // Buscar account_id do item
            $accountId = $this->getAccountIdFromItem($itemId);
            if (!$accountId) {
                return [
                    'status' => 'failed',
                    'error' => 'Não foi possível identificar a conta do item',
                ];
            }

            $techSheetService = new \App\Services\TechSheetService($accountId);

            // Gerar sugestões reais de ficha técnica para o item
            $result = $techSheetService->generateSuggestions($itemId, [
                'use_title' => true,
                'use_benchmark' => true,
                'use_ai' => false,
                'min_confidence' => 60,
            ]);

            return [
                'status' => 'success',
                'message' => 'Sugestões de Tech Sheet geradas',
                'created' => (int)($result['created'] ?? 0),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Executa ação de SEO - dispara otimização SEO
     */
    private function executeSeoOptimizeAction(string $itemId): array
    {
        try {
            // Verificar se SeoAnalyzerService existe
            if (!class_exists(\App\Services\SeoAnalyzerService::class)) {
                return [
                    'status' => 'skipped',
                    'message' => 'SeoAnalyzerService não disponível',
                ];
            }

            // Buscar account_id do item
            $accountId = $this->getAccountIdFromItem($itemId);
            if (!$accountId) {
                return [
                    'status' => 'failed',
                    'error' => 'Não foi possível identificar a conta do item',
                ];
            }

            $seoService = new \App\Services\SeoAnalyzerService($accountId);

            // Analisar item
            $analysis = $seoService->analyzeItem($itemId);

            // Se há sugestões de melhoria, agendar job de SEO
            if (isset($analysis['score']) && $analysis['score'] < 80) {
                // Agendar otimização via JobService se disponível
                try {
                    $jobService = new JobService();
                    $jobService->dispatch('seo_optimize_item', [
                        'item_id' => $itemId,
                        'account_id' => $accountId,
                        'priority' => 'low',
                    ]);

                    return [
                        'status' => 'success',
                        'message' => 'Otimização SEO agendada',
                        'current_score' => $analysis['score'] ?? null,
                        'suggestions_count' => count($analysis['suggestions'] ?? []),
                    ];
                } catch (Exception $e) {
                    // Se falhar o job, retornar análise apenas
                }
            }

            return [
                'status' => 'success',
                'message' => 'Análise SEO concluída',
                'score' => $analysis['score'] ?? null,
                'grade' => $analysis['grade'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Executa ação de Pricing - aplica precificação inteligente
     */
    private function executePricingAction(string $itemId): array
    {
        try {
            // Verificar se PricingStrategyService existe
            if (!class_exists(\App\Services\PricingStrategyService::class)) {
                return [
                    'status' => 'skipped',
                    'message' => 'PricingStrategyService não disponível',
                ];
            }

            // Buscar account_id do item
            $accountId = $this->getAccountIdFromItem($itemId);
            if (!$accountId) {
                return [
                    'status' => 'failed',
                    'error' => 'Não foi possível identificar a conta do item',
                ];
            }

            // Buscar dados do item
            $client = new MercadoLivreClient($accountId);
            $item = $client->get("/items/{$itemId}");

            if (isset($item['error'])) {
                return [
                    'status' => 'failed',
                    'error' => 'Erro ao buscar item: ' . ($item['message'] ?? 'Unknown'),
                ];
            }

            $pricingService = new PricingStrategyService($accountId);

            // Analisar concorrência
            $analysis = $pricingService->analyzeCompetitorPrices(
                $item['category_id'],
                null,
                $item['title']
            );

            // Sugerir preço competitivo
            $suggestion = $pricingService->suggestPrice($analysis, 'competitive');

            if (isset($suggestion['suggested_price']) && $suggestion['suggested_price'] !== $item['price']) {
                // Atualizar preço do item
                $updateResult = $client->put("/items/{$itemId}", [
                    'price' => $suggestion['suggested_price'],
                ]);

                if (isset($updateResult['error'])) {
                    return [
                        'status' => 'failed',
                        'error' => 'Erro ao atualizar preço: ' . ($updateResult['message'] ?? 'Unknown'),
                    ];
                }

                return [
                    'status' => 'success',
                    'message' => 'Preço atualizado para valor competitivo',
                    'old_price' => $item['price'],
                    'new_price' => $suggestion['suggested_price'],
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Preço já está competitivo',
                'current_price' => $item['price'],
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Executa ação de Ativar - ativa o anúncio
     */
    private function executeActivateAction(string $itemId): array
    {
        try {
            $accountId = $this->getAccountIdFromItem($itemId);
            if (!$accountId) {
                return [
                    'status' => 'failed',
                    'error' => 'Não foi possível identificar a conta do item',
                ];
            }

            $client = new MercadoLivreClient($accountId);

            $result = $client->put("/items/{$itemId}", [
                'status' => 'active',
            ]);

            if (isset($result['error'])) {
                return [
                    'status' => 'failed',
                    'error' => 'Erro ao ativar item: ' . ($result['message'] ?? 'Unknown'),
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Anúncio ativado com sucesso',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtém account_id a partir do item_id
     */
    private function getAccountIdFromItem(string $itemId): ?int
    {
        // Primeiro tentar na tabela items local
        try {
            $stmt = $this->db->prepare("SELECT account_id FROM items WHERE ml_item_id = :item_id LIMIT 1");
            $stmt->execute(['item_id' => $itemId]);
            $result = $stmt->fetchColumn();
            if ($result) {
                return (int) $result;
            }
        } catch (Exception $e) {
            // Tabela pode não existir
        }

        // Tentar na tabela cloned_items
        try {
            $stmt = $this->db->prepare("SELECT target_account_id FROM cloned_items WHERE target_item_id = :item_id ORDER BY id DESC LIMIT 1");
            $stmt->execute(['item_id' => $itemId]);
            $result = $stmt->fetchColumn();
            if ($result) {
                return (int) $result;
            }
        } catch (Exception $e) {
            // Tabela pode não existir
        }

        // Tentar na tabela catalog_clone_job_items
        try {
            $stmt = $this->db->prepare("
                SELECT ccj.target_account_id
                FROM catalog_clone_job_items ccji
                JOIN catalog_clone_jobs ccj ON ccji.job_id = ccj.job_id
                WHERE ccji.target_item_id = :item_id
                ORDER BY ccji.id DESC LIMIT 1
            ");
            $stmt->execute(['item_id' => $itemId]);
            $result = $stmt->fetchColumn();
            if ($result) {
                return (int) $result;
            }
        } catch (Exception $e) {
            // Tabela pode não existir
        }

        return null;
    }

    /**
     * Obtém estatísticas de ações pós-clone
     */
    public function getActionStats(?string $jobId = null, ?int $days = 7): array
    {
        $sql = "
            SELECT
                action_type,
                status,
                COUNT(*) as count
            FROM clone_post_actions_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        $params = ['days' => $days];

        if ($jobId) {
            $sql .= " AND clone_job_id = :job_id";
            $params['job_id'] = $jobId;
        }

        $sql .= " GROUP BY action_type, status";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Organizar por tipo de ação
        $stats = [];
        foreach ($rows as $row) {
            $type = $row['action_type'];
            if (!isset($stats[$type])) {
                $stats[$type] = ['total' => 0, 'completed' => 0, 'failed' => 0, 'pending' => 0];
            }
            $stats[$type][$row['status']] = (int) $row['count'];
            $stats[$type]['total'] += (int) $row['count'];
        }

        return $stats;
    }
}
