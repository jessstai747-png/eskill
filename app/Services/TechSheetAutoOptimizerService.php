<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\JobService;
use PDO;

/**
 * Tech Sheet Auto Optimizer
 * 
 * Sistema de auto-aplicação de sugestões com alta confiança
 * Aplica automaticamente sugestões seguras baseadas em rules
 */
class TechSheetAutoOptimizerService
{
    private PDO $db;
    private int $accountId;
    private TechSheetService $techSheetService;
    private array $config;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->techSheetService = new TechSheetService($accountId);
        
        $appConfig = \App\Core\Config::getInstance()->all();
        $this->config = [
            'enabled' => $appConfig['tech_sheet']['auto_apply'] ?? false,
            'auto_apply' => $appConfig['tech_sheet']['auto_apply_to_ml'] ?? true,  // Se true, aplica no ML após aprovar
            'min_confidence' => $appConfig['tech_sheet']['min_confidence_auto'] ?? 90,
            'safe_sources' => TechSheetService::SAFE_SOURCES,  // Usa constantes canônicas
            'max_batch_size' => 50,
            'dry_run' => false,  // Se true, apenas simula
        ];
    }

    /**
     * Executa otimização automática para itens elegíveis
     * 
     * @param array $options
     * @return array
     */
    public function autoOptimize(array $options = []): array
    {
        if (!$this->config['enabled'] && !($options['force'] ?? false)) {
            return [
                'success' => false,
                'dry_run' => false,
                'error' => 'Auto-optimize desabilitado. Use force=true para forçar.',
            ];
        }

        $dryRun = $options['dry_run'] ?? $this->config['dry_run'];
        $limit = min($options['limit'] ?? 100, $this->config['max_batch_size']);
        
        // Buscar itens elegíveis
        $eligibleItems = $this->findEligibleItems($limit);
        
        if (empty($eligibleItems)) {
            return [
                'success' => true,
                'message' => 'Nenhum item elegível para auto-optimize',
                'processed' => 0,
            ];
        }

        $results = [
            'total_eligible' => count($eligibleItems),
            'processed' => 0,
            'approved' => 0,
            'applied' => 0,
            'skipped' => 0,
            'errors' => 0,
            'items' => [],
        ];

        foreach ($eligibleItems as $item) {
            $itemResult = $this->processItem($item, $dryRun);
            $results['items'][] = $itemResult;
            $results['processed']++;
            
            if ($itemResult['status'] === 'approved') {
                $results['approved']++;
            }
            if ($itemResult['status'] === 'applied') {
                $results['applied']++;
            }
            if ($itemResult['status'] === 'skipped') {
                $results['skipped']++;
            }
            if ($itemResult['status'] === 'error') {
                $results['errors']++;
            }
        }

        return [
            'success' => true,
            'dry_run' => $dryRun,
            'results' => $results,
            'executed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Encontra itens elegíveis para auto-optimize
     * 
     * Critérios:
     * - Tem sugestões pendentes com confiança >= min_confidence
     * - Fonte é segura (title, benchmark) - usa constantes canônicas
     * - Item ativo
     * - Categoria conhecida
     */
    private function findEligibleItems(int $limit): array
    {
        // Construir IN clause com fontes seguras
        $safeSources = $this->config['safe_sources'];
        $placeholders = implode(',', array_map(fn($i) => ":source{$i}", array_keys($safeSources)));
        
        $limitSql = max(1, min((int)$limit, 500));

        $sql = "
            SELECT 
                sug.item_id,
                i.title,
                i.category_id,
                COUNT(*) as eligible_suggestions,
                AVG(sug.confidence) as avg_confidence,
                GROUP_CONCAT(sug.attribute_id) as attribute_ids
            FROM tech_sheet_suggestions sug
            INNER JOIN items i 
                ON sug.item_id = i.ml_item_id AND sug.account_id = i.account_id
            WHERE sug.account_id = :account_id
              AND sug.status = 'pending'
              AND sug.confidence >= :min_confidence
              AND sug.source IN ({$placeholders})
              AND i.status = 'active'
              AND i.category_id IS NOT NULL
            GROUP BY sug.item_id, i.title, i.category_id
            HAVING eligible_suggestions > 0
            ORDER BY avg_confidence DESC, eligible_suggestions DESC
            LIMIT {$limitSql}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue(':min_confidence', $this->config['min_confidence'], PDO::PARAM_INT);
        foreach ($safeSources as $i => $source) {
            $stmt->bindValue(":source{$i}", $source, PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Processa um item individual
     * 
     * @param array $item Item a processar
     * @param bool $dryRun Se true, apenas simula sem modificar
     * @return array Resultado do processamento
     */
    private function processItem(array $item, bool $dryRun): array
    {
        $itemId = $item['item_id'];
        
        try {
            // Buscar sugestões elegíveis
            $suggestions = $this->getEligibleSuggestions($itemId);
            
            if (empty($suggestions)) {
                return [
                    'item_id' => $itemId,
                    'status' => 'skipped',
                    'reason' => 'Nenhuma sugestão elegível',
                ];
            }

            if ($dryRun) {
                return [
                    'item_id' => $itemId,
                    'status' => 'dry_run',
                    'suggestions_count' => count($suggestions),
                    'would_approve' => array_column($suggestions, 'attribute_id'),
                    'would_apply' => $this->config['auto_apply'],
                ];
            }

            // Auto-aprovar sugestões
            $approvedCount = 0;
            foreach ($suggestions as $sug) {
                $decision = [
                    'attribute_id' => $sug['attribute_id'],
                    'status' => 'approved',
                ];
                
                // Passa null para indicar ação automática (sistema)
                $this->techSheetService->saveDecisions($itemId, [$decision], null);
                $approvedCount++;
            }

            // Se auto_apply estiver habilitado, aplicar no Mercado Livre
            $appliedCount = 0;
            $applyError = null;
            
            if ($this->config['auto_apply'] && $approvedCount > 0) {
                try {
                    // Aplicar sugestões aprovadas (userId=null para ação de sistema)
                    $applyResult = $this->techSheetService->applyApproved($itemId, null);
                    
                    if ($applyResult['success'] ?? false) {
                        $appliedCount = $applyResult['applied'] ?? 0;
                    } else {
                        $applyError = $applyResult['error'] ?? 'Erro desconhecido ao aplicar';
                    }
                } catch (\Exception $e) {
                    $applyError = $e->getMessage();
                }
            }

            // Determinar status final
            $status = 'approved';
            if ($appliedCount > 0) {
                $status = 'applied';
            } elseif ($applyError) {
                $status = 'apply_error';
            }

            return [
                'item_id' => $itemId,
                'status' => $status,
                'approved_count' => $approvedCount,
                'applied_count' => $appliedCount,
                'attributes' => array_column($suggestions, 'attribute_id'),
                'auto_apply_enabled' => $this->config['auto_apply'],
                'apply_error' => $applyError,
            ];
            
        } catch (\Exception $e) {
            return [
                'item_id' => $itemId,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Busca sugestões elegíveis para um item
     */
    private function getEligibleSuggestions(string $itemId): array
    {
        // Construir IN clause com fontes seguras
        $safeSources = $this->config['safe_sources'];
        $placeholders = implode(',', array_map(fn($i) => ":source{$i}", array_keys($safeSources)));
        
        $sql = "
            SELECT 
                attribute_id,
                attribute_name,
                suggested_value,
                source,
                confidence
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
              AND item_id = :item_id
              AND status = 'pending'
              AND confidence >= :min_confidence
              AND source IN ({$placeholders})
            ORDER BY confidence DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $params = [
            ':account_id' => $this->accountId,
            ':item_id' => $itemId,
            ':min_confidence' => $this->config['min_confidence'],
        ];
        foreach ($safeSources as $i => $source) {
            $params[":source{$i}"] = $source;
        }
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executa auto-optimize via job em background
     */
    public function scheduleAutoOptimize(array $options = []): string
    {
        $jobService = new JobService();
        
        return $jobService->dispatch('tech_sheet_auto_optimize', [
            'account_id' => $this->accountId,
            'options' => $options,
        ]);
    }

    /**
     * Estatísticas de auto-optimize
     */
    public function getStats(): array
    {
        // Contar itens elegíveis
        $eligibleItems = $this->findEligibleItems(1000);
        
        // Construir IN clause com fontes seguras
        $safeSources = $this->config['safe_sources'];
        $placeholders = implode(',', array_map(fn($i) => ":source{$i}", array_keys($safeSources)));
        
        $sql = "
            SELECT 
                source,
                COUNT(*) as count,
                AVG(confidence) as avg_confidence
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
              AND status = 'pending'
              AND confidence >= :min_confidence
              AND source IN ({$placeholders})
            GROUP BY source
        ";
        
        $stmt = $this->db->prepare($sql);
        $params = [
            ':account_id' => $this->accountId,
            ':min_confidence' => $this->config['min_confidence'],
        ];
        foreach ($safeSources as $i => $source) {
            $params[":source{$i}"] = $source;
        }
        $stmt->execute($params);
        
        $bySource = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'enabled' => $this->config['enabled'],
            'min_confidence' => $this->config['min_confidence'],
            'safe_sources' => $safeSources,
            'eligible_items' => count($eligibleItems),
            'total_eligible_suggestions' => array_sum(array_column($bySource, 'count')),
            'by_source' => $bySource,
        ];
    }
}
