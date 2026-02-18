<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SEO\SEOMonitoringService;
use App\Services\SEO\SEOStrategiesEngine;

class SEOMonitoringJob
{
    private SEOMonitoringService $monitoringService;
    private SEOStrategiesEngine $seoEngine;

    public function __construct()
    {
        $this->monitoringService = new SEOMonitoringService();
        $this->seoEngine = new SEOStrategiesEngine();
    }

    /**
     * Executa verificação semanal de todos os anúncios
     */
    public function runWeeklyCheck(): void
    {
        try {
            // Get all active items that need monitoring
            $itemsToCheck = $this->getItemsForWeeklyCheck();
            
            foreach ($itemsToCheck as $item) {
                $itemId = $item['id'];
                
                // Collect current metrics
                $currentMetrics = $this->monitoringService->collectMetrics($itemId);
                
                // Compare with previous week
                $comparison = $this->monitoringService->compareWithPrevious($itemId, 7);
                
                // Check if there's significant decline
                if ($this->isSignificantDecline($comparison)) {
                    // Generate alert
                    $this->monitoringService->generateAlert($itemId, 'significant_decline');
                    
                    // Optionally trigger auto-optimization
                    $this->triggerAutoOptimizationIfNeeded($itemId, $comparison);
                }
                
                // Log the check
                log_info("Weekly check completed for item {$itemId}", ['item_id' => $itemId]);
            }
            
            log_info("Weekly SEO monitoring check completed", ['items_count' => count($itemsToCheck)]);
        } catch (\Exception $e) {
            log_error("Error in weekly SEO monitoring: " . $e->getMessage(), ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Processa fila de otimizações automáticas
     */
    public function processAutoOptimizationQueue(): void
    {
        try {
            // Get items that are scheduled for auto-optimization
            $itemsToOptimize = $this->getItemsForAutoOptimization();
            
            foreach ($itemsToOptimize as $item) {
                $itemId = $item['id'];
                
                try {
                    // Run auto-optimization
                    $result = $this->monitoringService->runAutoOptimization($itemId);
                    
                    // Log success
                    log_info("Auto-optimization completed", ['item_id' => $itemId]);
                } catch (\Exception $e) {
                    // Log failure but continue with other items
                    log_error("Auto-optimization failed", ['item_id' => $itemId, 'error' => $e->getMessage()]);
                }
            }
            
            log_info("Auto-optimization queue processed", ['items_count' => count($itemsToOptimize)]);
        } catch (\Exception $e) {
            log_error("Error processing auto-optimization queue", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Envia alertas de queda de posição
     */
    public function sendPositionAlerts(): void
    {
        try {
            // Get items with position tracking
            $itemsToMonitor = $this->getItemsForPositionMonitoring();
            
            foreach ($itemsToMonitor as $item) {
                $itemId = $item['id'];
                
                // Collect current metrics
                $metrics = $this->monitoringService->collectMetrics($itemId);
                
                // Check if position has declined significantly
                if ($metrics['position_avg'] > $item['threshold_position'] ?? 10) {
                    $this->monitoringService->generateAlert($itemId, 'position_decline');
                }
            }
            
            log_info("Position alerts check completed", ['items_count' => count($itemsToMonitor)]);
        } catch (\Exception $e) {
            log_error("Error sending position alerts", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get items that need weekly check
     */
    private function getItemsForWeeklyCheck(): array
    {
        try {
            $db = \App\Database::getInstance();

            // Buscar itens ativos que precisam de verificação semanal (todas as contas)
            $stmt = $db->prepare("
                SELECT i.ml_item_id AS id, i.account_id
                FROM items i
                LEFT JOIN seo_monitoring_schedule s ON s.item_id = i.ml_item_id AND s.account_id = i.account_id
                WHERE i.status = 'active'
                  AND (s.last_checked IS NULL OR s.last_checked < DATE_SUB(NOW(), INTERVAL 7 DAY))
                ORDER BY i.updated_at DESC
                LIMIT 100
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_error('SEOMonitoringJob::getItemsForWeeklyCheck error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get items scheduled for auto-optimization
     */
    private function getItemsForAutoOptimization(): array
    {
        try {
            $db = \App\Database::getInstance();

            // Buscar itens com auto-optimization habilitada (todas as contas)
            $stmt = $db->prepare("
                SELECT i.ml_item_id AS id, i.account_id
                FROM items i
                WHERE i.status = 'active'
                  AND i.auto_reprice = 1
                ORDER BY i.updated_at DESC
                LIMIT 50
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_error('SEOMonitoringJob::getItemsForAutoOptimization error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get items for position monitoring
     */
    private function getItemsForPositionMonitoring(): array
    {
        try {
            $db = \App\Database::getInstance();

            // Buscar itens com configuração de threshold de posição (todas as contas)
            $stmt = $db->prepare("
                SELECT i.ml_item_id AS id,
                       i.account_id,
                       COALESCE(s.threshold_position, 10) AS threshold_position
                FROM items i
                LEFT JOIN seo_monitoring_schedule s ON s.item_id = i.ml_item_id AND s.account_id = i.account_id
                WHERE i.status = 'active'
                  AND (s.position_monitoring = 1 OR s.position_monitoring IS NULL)
                ORDER BY i.updated_at DESC
                LIMIT 100
            ");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_error('SEOMonitoringJob::getItemsForPositionMonitoring error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check if comparison shows significant decline
     */
    private function isSignificantDecline(array $comparison): bool
    {
        $changes = $comparison['changes'] ?? [];
        
        // Define what constitutes significant decline
        $significantDeclines = [
            $changes['position_change'] > 5,  // Position dropped by more than 5 places
            $changes['conversion_change'] < -0.02,  // Conversion rate dropped by more than 2%
            $changes['views_change'] < -100  // Views dropped by more than 100
        ];
        
        // Return true if any significant decline occurred
        return in_array(true, $significantDeclines);
    }

    /**
     * Trigger auto-optimization if needed based on comparison
     */
    private function triggerAutoOptimizationIfNeeded(string $itemId, array $comparison): void
    {
        // Determine if auto-optimization is needed based on the decline
        $needsOptimization = false;
        
        if (isset($comparison['changes']['position_change']) && $comparison['changes']['position_change'] > 5) {
            $needsOptimization = true;
        } elseif (isset($comparison['changes']['conversion_change']) && $comparison['changes']['conversion_change'] < -0.02) {
            $needsOptimization = true;
        }
        
        if ($needsOptimization) {
            try {
                $result = $this->monitoringService->runAutoOptimization($itemId);
                log_info("Auto-optimization triggered due to performance decline", ['item_id' => $itemId]);
            } catch (\Exception $e) {
                log_error("Failed to trigger auto-optimization", ['item_id' => $itemId, 'error' => $e->getMessage()]);
            }
        }
    }
}
