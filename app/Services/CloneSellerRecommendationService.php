<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use Exception;

/**
 * Clone Seller Recommendation Service
 * 
 * Sistema de recomendações inteligentes de sellers para clonagem:
 * - Análise de histórico de clonagens bem-sucedidas
 * - Score de qualidade baseado em métricas
 * - Detecção de sellers com alto potencial
 * - Recomendações baseadas em categoria/marca
 */
class CloneSellerRecommendationService
{
    private PDO $db;
    private int $accountId;

    // Pesos para cálculo de score
    private const WEIGHT_SUCCESS_RATE = 0.30;
    private const WEIGHT_ITEM_COUNT = 0.20;
    private const WEIGHT_CATALOG_RATIO = 0.15;
    private const WEIGHT_BRAND_VARIETY = 0.10;
    private const WEIGHT_RECENT_ACTIVITY = 0.15;
    private const WEIGHT_CONVERSION = 0.10;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }

    /**
     * Obtém recomendações de sellers para clonar
     */
    public function getRecommendations(array $filters = []): array
    {
        $limit = $filters['limit'] ?? 20;
        $categoryId = $filters['category_id'] ?? null;
        $minScore = $filters['min_score'] ?? 50;

        // Buscar sellers do histórico com scores calculados
        $sellers = $this->getAnalyzedSellers($categoryId);

        // Filtrar por score mínimo
        $sellers = array_filter($sellers, fn($s) => $s['score'] >= $minScore);

        // Ordenar por score
        usort($sellers, fn($a, $b) => $b['score'] <=> $a['score']);

        // Aplicar limite
        $sellers = array_slice($sellers, 0, $limit);

        // Enriquecer com razões da recomendação
        foreach ($sellers as &$seller) {
            $seller['recommendation_reasons'] = $this->getRecommendationReasons($seller);
        }

        return [
            'recommendations' => $sellers,
            'total_analyzed' => count($sellers),
            'filters_applied' => $filters,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Analisa sellers do histórico de clonagens
     */
    private function getAnalyzedSellers(?string $categoryId): array
    {
        $sql = "
            SELECT 
                cji.source_seller_id,
                COUNT(DISTINCT cji.source_item_id) as total_items_cloned,
                SUM(CASE WHEN cji.status = 'completed' THEN 1 ELSE 0 END) as successful_clones,
                SUM(CASE WHEN cji.status = 'failed' THEN 1 ELSE 0 END) as failed_clones,
                COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(cji.source_snapshot, '$.category_id'))) as category_count,
                COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(cji.source_snapshot, '$.brand'))) as brand_count,
                MAX(cji.processed_at) as last_clone_date,
                AVG(CASE WHEN cji.status = 'completed' THEN 1 ELSE 0 END) * 100 as success_rate
            FROM catalog_clone_job_items cji
            JOIN catalog_clone_jobs cj ON cj.job_id = cji.job_id
            WHERE cj.target_account_id = :account_id
            AND cji.source_seller_id IS NOT NULL
        ";
        $params = [':account_id' => $this->accountId];

        if ($categoryId) {
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(cji.source_snapshot, '$.category_id')) LIKE :category";
            $params[':category'] = $categoryId . '%';
        }

        $sql .= " GROUP BY cji.source_seller_id HAVING total_items_cloned >= 3";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular scores e enriquecer
        foreach ($sellers as &$seller) {
            $seller['score'] = $this->calculateSellerScore($seller);
            $seller['metrics'] = $this->calculateMetrics($seller);
        }

        return $sellers;
    }

    /**
     * Calcula score do seller (0-100)
     */
    private function calculateSellerScore(array $seller): float
    {
        $score = 0;

        // 1. Taxa de sucesso (30%)
        $successRate = floatval($seller['success_rate'] ?? 0);
        $score += ($successRate / 100) * self::WEIGHT_SUCCESS_RATE * 100;

        // 2. Quantidade de itens (20%) - normalizado log
        $itemCount = intval($seller['total_items_cloned'] ?? 0);
        $itemScore = min(1, log10($itemCount + 1) / log10(100));
        $score += $itemScore * self::WEIGHT_ITEM_COUNT * 100;

        // 3. Variedade de categorias (15%)
        $catCount = intval($seller['category_count'] ?? 0);
        $catScore = min(1, $catCount / 10);
        $score += $catScore * self::WEIGHT_CATALOG_RATIO * 100;

        // 4. Variedade de marcas (10%)
        $brandCount = intval($seller['brand_count'] ?? 0);
        $brandScore = min(1, $brandCount / 20);
        $score += $brandScore * self::WEIGHT_BRAND_VARIETY * 100;

        // 5. Atividade recente (15%)
        $lastDate = $seller['last_clone_date'] ?? null;
        if ($lastDate) {
            $daysSince = (time() - strtotime($lastDate)) / 86400;
            $recentScore = max(0, 1 - ($daysSince / 90)); // Decay em 90 dias
            $score += $recentScore * self::WEIGHT_RECENT_ACTIVITY * 100;
        }

        // 6. Conversão estimada (10%) - baseado em sucesso
        $conversionEstimate = $successRate * 0.8; // Proxy
        $score += ($conversionEstimate / 100) * self::WEIGHT_CONVERSION * 100;

        return round($score, 2);
    }

    /**
     * Calcula métricas detalhadas
     */
    private function calculateMetrics(array $seller): array
    {
        $total = intval($seller['total_items_cloned'] ?? 0);
        $success = intval($seller['successful_clones'] ?? 0);
        $failed = intval($seller['failed_clones'] ?? 0);

        return [
            'total_cloned' => $total,
            'success_count' => $success,
            'failure_count' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0,
            'categories' => intval($seller['category_count'] ?? 0),
            'brands' => intval($seller['brand_count'] ?? 0),
            'days_since_last_clone' => $seller['last_clone_date'] 
                ? intval((time() - strtotime($seller['last_clone_date'])) / 86400)
                : null,
        ];
    }

    /**
     * Gera razões da recomendação
     */
    private function getRecommendationReasons(array $seller): array
    {
        $reasons = [];
        $metrics = $seller['metrics'] ?? [];

        if (($metrics['success_rate'] ?? 0) >= 90) {
            $reasons[] = [
                'type' => 'high_success',
                'icon' => '✅',
                'text' => 'Alta taxa de sucesso (' . $metrics['success_rate'] . '%)',
            ];
        }

        if (($metrics['total_cloned'] ?? 0) >= 50) {
            $reasons[] = [
                'type' => 'proven_volume',
                'icon' => '📦',
                'text' => 'Volume comprovado (' . $metrics['total_cloned'] . ' itens)',
            ];
        }

        if (($metrics['categories'] ?? 0) >= 5) {
            $reasons[] = [
                'type' => 'diverse_catalog',
                'icon' => '🏷️',
                'text' => 'Catálogo diversificado (' . $metrics['categories'] . ' categorias)',
            ];
        }

        if (($metrics['brands'] ?? 0) >= 10) {
            $reasons[] = [
                'type' => 'multi_brand',
                'icon' => '🏢',
                'text' => 'Multi-marca (' . $metrics['brands'] . ' marcas)',
            ];
        }

        if (($metrics['days_since_last_clone'] ?? 999) <= 7) {
            $reasons[] = [
                'type' => 'recent_activity',
                'icon' => '🔥',
                'text' => 'Atividade recente',
            ];
        }

        return $reasons;
    }

    /**
     * Registra seller como fonte de clonagem
     */
    public function trackSellerClone(string $sellerId, array $itemData): void
    {
        // Atualizar estatísticas do seller
        $stmt = $this->db->prepare("
            INSERT INTO clone_seller_stats 
            (account_id, seller_id, total_clones, last_clone_at, categories, brands, updated_at)
            VALUES 
            (:account_id, :seller_id, 1, NOW(), :categories, :brands, NOW())
            ON DUPLICATE KEY UPDATE
            total_clones = total_clones + 1,
            last_clone_at = NOW(),
            updated_at = NOW()
        ");

        $stmt->execute([
            ':account_id' => $this->accountId,
            ':seller_id' => $sellerId,
            ':categories' => json_encode([$itemData['category_id'] ?? null]),
            ':brands' => json_encode([$itemData['brand'] ?? null]),
        ]);
    }

    /**
     * Obtém sellers similares a um dado
     */
    public function getSimilarSellers(string $sellerId, int $limit = 10): array
    {
        $limitSql = max(1, min(100, (int)$limit));
        // Buscar categorias/marcas do seller original
        $stmt = $this->db->prepare("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(source_snapshot, '$.category_id')) as category_id,
                JSON_UNQUOTE(JSON_EXTRACT(source_snapshot, '$.brand')) as brand
            FROM catalog_clone_job_items
            WHERE source_seller_id = :seller_id
            LIMIT 100
        ");
        $stmt->execute([':seller_id' => $sellerId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [];
        }

        $categories = array_unique(array_column($items, 'category_id'));
        $brands = array_unique(array_column($items, 'brand'));

        // Buscar outros sellers com categorias/marcas similares
        $placeholdersCat = implode(',', array_fill(0, count($categories), '?'));
        $placeholdersBrand = implode(',', array_fill(0, count($brands), '?'));

        $sql = "
            SELECT source_seller_id, COUNT(*) as match_count
            FROM catalog_clone_job_items
            WHERE source_seller_id != ?
            AND (
                JSON_UNQUOTE(JSON_EXTRACT(source_snapshot, '$.category_id')) IN ({$placeholdersCat})
                OR JSON_UNQUOTE(JSON_EXTRACT(source_snapshot, '$.brand')) IN ({$placeholdersBrand})
            )
            GROUP BY source_seller_id
            ORDER BY match_count DESC
            LIMIT {$limitSql}
        ";

        $params = array_merge([$sellerId], $categories, $brands);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém tendências de sellers
     */
    public function getSellerTrends(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(cji.processed_at) as date,
                COUNT(DISTINCT cji.source_seller_id) as unique_sellers,
                COUNT(*) as total_clones,
                SUM(CASE WHEN cji.status = 'completed' THEN 1 ELSE 0 END) as successful
            FROM catalog_clone_job_items cji
            JOIN catalog_clone_jobs cj ON cj.job_id = cji.job_id
            WHERE cj.target_account_id = :account_id
            AND cji.processed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(cji.processed_at)
            ORDER BY date ASC
        ");
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':days' => $days,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém top sellers por categoria
     */
    public function getTopSellersByCategory(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(cji.source_snapshot, '$.category_id')) as category_id,
                cji.source_seller_id,
                COUNT(*) as clone_count,
                AVG(CASE WHEN cji.status = 'completed' THEN 1 ELSE 0 END) * 100 as success_rate
            FROM catalog_clone_job_items cji
            JOIN catalog_clone_jobs cj ON cj.job_id = cji.job_id
            WHERE cj.target_account_id = :account_id
            AND cji.source_seller_id IS NOT NULL
            GROUP BY category_id, cji.source_seller_id
            ORDER BY category_id, clone_count DESC
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por categoria e pegar top N de cada
        $byCategory = [];
        foreach ($rows as $row) {
            $catId = $row['category_id'];
            if (!isset($byCategory[$catId])) {
                $byCategory[$catId] = [];
            }
            if (count($byCategory[$catId]) < $limit) {
                $byCategory[$catId][] = $row;
            }
        }

        return $byCategory;
    }

    /**
     * Obtém estatísticas gerais
     */
    public function getStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT source_seller_id) as unique_sellers,
                COUNT(*) as total_clone_records,
                AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 as avg_success_rate
            FROM catalog_clone_job_items cji
            JOIN catalog_clone_jobs cj ON cj.job_id = cji.job_id
            WHERE cj.target_account_id = :account_id
            AND source_seller_id IS NOT NULL
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'unique_sellers_tracked' => intval($stats['unique_sellers'] ?? 0),
            'total_clone_records' => intval($stats['total_clone_records'] ?? 0),
            'average_success_rate' => round(floatval($stats['avg_success_rate'] ?? 0), 1),
        ];
    }

    /**
     * Garante que as tabelas existem
     */
    private function ensureTablesExist(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_seller_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                seller_id VARCHAR(50) NOT NULL,
                total_clones INT DEFAULT 0,
                successful_clones INT DEFAULT 0,
                failed_clones INT DEFAULT 0,
                last_clone_at DATETIME NULL,
                categories JSON,
                brands JSON,
                score DECIMAL(5,2) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_account_seller (account_id, seller_id),
                INDEX idx_score (score DESC),
                INDEX idx_last_clone (last_clone_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $checked = true;
    }
}
