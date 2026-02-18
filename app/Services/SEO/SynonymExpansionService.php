<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use PDO;

class SynonymExpansionService
{
    private PDO $db;
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    public function expand(string $title, string $categoryId): array
    {
        $hierarchy = $this->getHierarchy($categoryId);
        
        $expanded = [];
        foreach ($hierarchy as $level => $words) {
            $expanded[$level] = [
                'words' => $words,
                'count' => count($words)
            ];
        }
        
        return $expanded;
    }

    public function getHierarchy(string $categoryId): array
    {
        $stmt = $this->db->prepare("
            SELECT level, word, weight, destination
            FROM seo_synonym_hierarchy
            WHERE category_id = :category_id AND is_active = 1
            ORDER BY level, weight DESC
        ");
        
        $stmt->execute(['category_id' => $categoryId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hierarchy = [];
        foreach ($rows as $row) {
            $level = $row['level'];
            if (!isset($hierarchy[$level])) {
                $hierarchy[$level] = [];
            }
            $hierarchy[$level][] = $row['word'];
        }
        
        return $hierarchy;
    }

    public function generateOptimizedModel(string $title, string $categoryId): string
    {
        $hierarchy = $this->getHierarchy($categoryId);
        $nivel2 = $hierarchy['nivel_2'] ?? [];
        
        if (empty($nivel2)) {
            return $title;
        }
        
        return $title . ' ' . $nivel2[0];
    }
}
