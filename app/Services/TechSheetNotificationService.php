<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Notification Service
 * 
 * Sistema de notificações e alertas para ficha técnica
 * Identifica itens que precisam de atenção e gera alertas
 */
class TechSheetNotificationService
{
    private PDO $db;
    private int $accountId;
    private array $thresholds;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        
        // Thresholds configuráveis
        $config = \App\Core\Config::getInstance()->all();
        $this->thresholds = [
            'critical_completeness' => 30,  // Abaixo de 30% é crítico
            'warning_completeness' => 50,   // Abaixo de 50% é warning
            'required_missing' => 1,        // Qualquer atributo obrigatório faltando
            'filter_missing' => 3,          // 3+ atributos de filtro faltando
            'days_without_update' => 30,    // 30 dias sem atualização
        ];
    }

    /**
     * Gera lista de alertas/notificações
     * 
     * @return array
     */
    public function getAlerts(): array
    {
        $alerts = [];
        
        // 1. Itens com completude crítica
        $alerts['critical_completeness'] = $this->getItemsWithCriticalCompleteness();
        
        // 2. Itens com atributos obrigatórios faltando
        $alerts['missing_required'] = $this->getItemsWithMissingRequired();
        
        // 3. Itens sem análise recente
        $alerts['outdated_analysis'] = $this->getItemsWithOutdatedAnalysis();
        
        // 4. Categorias com pior performance
        $alerts['worst_categories'] = $this->getWorstPerformingCategories();
        
        // 5. Sugestões pendentes há muito tempo
        $alerts['stale_suggestions'] = $this->getStaleSuggestions();
        
        return [
            'alerts' => $alerts,
            'summary' => $this->calculateAlertsSummary($alerts),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Itens com completude crítica (< 30%)
     */
    private function getItemsWithCriticalCompleteness(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.id,
                i.ml_item_id as item_id,
                i.title,
                i.category_id,
                s.completeness_percent,
                s.missing_required,
                s.missing_filter,
                s.last_analyzed_at
            FROM items i
            INNER JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND s.completeness_percent < :threshold
            ORDER BY s.completeness_percent ASC
            LIMIT 50
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':threshold' => $this->thresholds['critical_completeness'],
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Itens com atributos obrigatórios faltando
     */
    private function getItemsWithMissingRequired(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.id,
                i.ml_item_id as item_id,
                i.title,
                i.category_id,
                s.completeness_percent,
                s.missing_required,
                s.last_analyzed_at
            FROM items i
            INNER JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND s.missing_required >= :threshold
            ORDER BY s.missing_required DESC, s.completeness_percent ASC
            LIMIT 50
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':threshold' => $this->thresholds['required_missing'],
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Itens sem análise recente
     */
    private function getItemsWithOutdatedAnalysis(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.id,
                i.ml_item_id as item_id,
                i.title,
                i.category_id,
                s.last_analyzed_at,
                DATEDIFF(NOW(), s.last_analyzed_at) as days_since_analysis
            FROM items i
            LEFT JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND (
                s.last_analyzed_at IS NULL 
                OR s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL :days DAY)
              )
            ORDER BY 
              CASE WHEN s.last_analyzed_at IS NULL THEN 0 ELSE 1 END,
              s.last_analyzed_at ASC
            LIMIT 50
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':days' => $this->thresholds['days_without_update'],
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Categorias com pior performance
     */
    private function getWorstPerformingCategories(int $limit = 10): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT 
                i.category_id,
                COUNT(DISTINCT i.id) as item_count,
                AVG(s.completeness_percent) as avg_completeness,
                SUM(s.missing_required) as total_missing_required,
                SUM(s.missing_filter) as total_missing_filter
            FROM items i
            INNER JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND i.category_id IS NOT NULL
            GROUP BY i.category_id
            HAVING avg_completeness < 60 OR total_missing_required > 0
            ORDER BY total_missing_required DESC, avg_completeness ASC
            LIMIT {$limitSql}
        ");
        
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sugestões pendentes há muito tempo (> 14 dias)
     */
    private function getStaleSuggestions(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                sug.item_id,
                i.title,
                i.category_id,
                COUNT(*) as pending_count,
                MIN(sug.created_at) as oldest_suggestion,
                DATEDIFF(NOW(), MIN(sug.created_at)) as days_pending
            FROM tech_sheet_suggestions sug
            INNER JOIN items i ON sug.item_id = i.ml_item_id AND sug.account_id = i.account_id
            WHERE sug.account_id = :account_id
              AND sug.status = 'pending'
              AND sug.created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
              AND i.status = 'active'
            GROUP BY sug.item_id, i.title, i.category_id
            ORDER BY days_pending DESC
            LIMIT 30
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula resumo dos alertas
     */
    private function calculateAlertsSummary(array $alerts): array
    {
        return [
            'total_critical' => count($alerts['critical_completeness']),
            'total_missing_required' => count($alerts['missing_required']),
            'total_outdated' => count($alerts['outdated_analysis']),
            'total_worst_categories' => count($alerts['worst_categories']),
            'total_stale_suggestions' => count($alerts['stale_suggestions']),
            'priority_level' => $this->calculatePriorityLevel($alerts),
        ];
    }

    /**
     * Calcula nível de prioridade geral
     */
    private function calculatePriorityLevel(array $alerts): string
    {
        $criticalCount = count($alerts['critical_completeness']);
        $missingRequiredCount = count($alerts['missing_required']);
        
        if ($criticalCount > 20 || $missingRequiredCount > 30) {
            return 'CRITICAL';
        } elseif ($criticalCount > 10 || $missingRequiredCount > 15) {
            return 'HIGH';
        } elseif ($criticalCount > 5 || $missingRequiredCount > 5) {
            return 'MEDIUM';
        }
        
        return 'LOW';
    }

    /**
     * Gera relatório diário para envio por email
     */
    public function generateDailyReport(): array
    {
        $alerts = $this->getAlerts();
        $analytics = new TechSheetAnalyticsService($this->accountId);
        $overview = $analytics->getOverview();
        
        return [
            'date' => date('Y-m-d'),
            'overview' => $overview,
            'alerts' => $alerts,
            'action_items' => $this->generateActionItems($alerts),
        ];
    }

    /**
     * Gera lista de ações recomendadas
     */
    private function generateActionItems(array $alerts): array
    {
        $actions = [];
        
        if (count($alerts['alerts']['missing_required']) > 0) {
            $actions[] = [
                'priority' => 'HIGH',
                'action' => 'Preencher atributos obrigatórios',
                'count' => count($alerts['alerts']['missing_required']),
                'url' => '/dashboard/seo/ficha-tecnica?tab=pending',
            ];
        }
        
        if (count($alerts['alerts']['critical_completeness']) > 0) {
            $actions[] = [
                'priority' => 'MEDIUM',
                'action' => 'Melhorar completude crítica',
                'count' => count($alerts['alerts']['critical_completeness']),
                'url' => '/dashboard/seo/ficha-tecnica?completeness=0-30',
            ];
        }
        
        if (count($alerts['alerts']['stale_suggestions']) > 0) {
            $actions[] = [
                'priority' => 'LOW',
                'action' => 'Revisar sugestões pendentes',
                'count' => count($alerts['alerts']['stale_suggestions']),
                'url' => '/dashboard/seo/ficha-tecnica?tab=review',
            ];
        }
        
        return $actions;
    }
}
