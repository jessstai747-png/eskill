<?php

namespace App\Services;

use App\Database;
use PDO;
use Exception;

/**
 * Clone SEO Integration Service
 * 
 * Aplica otimizações SEO automaticamente durante o fluxo de clonagem:
 * - Otimização de títulos
 * - Melhoria de descrições
 * - Sugestão de keywords
 * - Verificação de atributos
 * - Diferentes níveis de otimização
 */
class CloneSEOIntegrationService
{
    private PDO $db;
    private int $accountId;

    // Níveis de otimização
    public const LEVEL_NONE = 'none';           // Sem otimização
    public const LEVEL_BASIC = 'basic';         // Título limpo, keywords principais
    public const LEVEL_STANDARD = 'standard';   // + descrição melhorada
    public const LEVEL_AGGRESSIVE = 'aggressive'; // + reescrita completa

    // Palavras proibidas no ML
    private const FORBIDDEN_WORDS = [
        'melhor', 'barato', 'oferta', 'promoção', 'desconto', 'grátis',
        'frete grátis', 'imperdível', 'aproveite', 'compre já', 'última unidade',
        'somente hoje', 'mega', 'super', 'hiper', 'ultra', 'top',
    ];

    // Palavras de alto impacto
    private const HIGH_IMPACT_WORDS = [
        'original', 'lacrado', 'novo', 'garantia', 'nota fiscal',
        'pronta entrega', 'envio imediato', 'full',
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }

    /**
     * Aplica otimização SEO a um item para clonagem
     */
    public function optimizeForClone(array $item, string $level = self::LEVEL_STANDARD): array
    {
        if ($level === self::LEVEL_NONE) {
            return $item;
        }

        $optimized = $item;
        $changes = [];

        // 1. Otimizar título
        $titleResult = $this->optimizeTitle($item['title'] ?? '', $item, $level);
        if ($titleResult['changed']) {
            $optimized['title'] = $titleResult['title'];
            $changes['title'] = $titleResult;
        }

        // 2. Otimizar descrição (se standard ou aggressive)
        if (in_array($level, [self::LEVEL_STANDARD, self::LEVEL_AGGRESSIVE])) {
            $descResult = $this->optimizeDescription(
                $item['description'] ?? '',
                $item,
                $level
            );
            if ($descResult['changed']) {
                $optimized['description'] = $descResult['description'];
                $changes['description'] = $descResult;
            }
        }

        // 3. Sugerir atributos faltantes
        $attrResult = $this->suggestAttributes($item);
        if (!empty($attrResult['suggestions'])) {
            $changes['attributes'] = $attrResult;
        }

        // 4. Calcular score SEO
        $seoScore = $this->calculateSEOScore($optimized);

        return [
            'item' => $optimized,
            'original' => $item,
            'changes' => $changes,
            'seo_score' => $seoScore,
            'optimization_level' => $level,
        ];
    }

    /**
     * Otimiza título do anúncio
     */
    private function optimizeTitle(string $title, array $item, string $level): array
    {
        $original = $title;
        $optimized = $title;
        $modifications = [];

        // 1. Limpar título
        $optimized = $this->cleanTitle($optimized);
        if ($optimized !== $original) {
            $modifications[] = 'cleaned';
        }

        // 2. Remover palavras proibidas
        $beforeForbidden = $optimized;
        $optimized = $this->removeForbiddenWords($optimized);
        if ($optimized !== $beforeForbidden) {
            $modifications[] = 'removed_forbidden';
        }

        // 3. Adicionar marca se não presente e disponível
        $brand = $this->extractBrand($item);
        if ($brand && stripos($optimized, $brand) === false) {
            if (strlen($optimized) + strlen($brand) + 3 <= 60) {
                $optimized = $brand . ' - ' . $optimized;
                $modifications[] = 'added_brand';
            }
        }

        // 4. Adicionar modelo se não presente (aggressive)
        if ($level === self::LEVEL_AGGRESSIVE) {
            $model = $this->extractModel($item);
            if ($model && stripos($optimized, $model) === false) {
                if (strlen($optimized) + strlen($model) + 1 <= 60) {
                    $optimized .= ' ' . $model;
                    $modifications[] = 'added_model';
                }
            }
        }

        // 5. Garantir tamanho ideal (45-58 caracteres)
        if (strlen($optimized) > 60) {
            $optimized = $this->truncateTitle($optimized, 58);
            $modifications[] = 'truncated';
        }

        // 6. Capitalização correta
        $beforeCase = $optimized;
        $optimized = $this->properCase($optimized);
        if ($optimized !== $beforeCase) {
            $modifications[] = 'fixed_case';
        }

        return [
            'title' => $optimized,
            'original' => $original,
            'changed' => $optimized !== $original,
            'modifications' => $modifications,
            'length' => strlen($optimized),
            'ideal_length' => strlen($optimized) >= 45 && strlen($optimized) <= 58,
        ];
    }

    /**
     * Limpa título removendo caracteres e espaços extras
     */
    private function cleanTitle(string $title): string
    {
        // Remover múltiplos espaços
        $title = preg_replace('/\s+/', ' ', $title);

        // Remover caracteres especiais desnecessários
        $title = preg_replace('/[^\w\s\-\/\.\,\(\)\+áéíóúãõâêîôûàèìòùçÁÉÍÓÚÃÕÂÊÎÔÛÀÈÌÒÙÇ]/u', '', $title);

        // Trim
        $title = trim($title);

        return $title;
    }

    /**
     * Remove palavras proibidas
     */
    private function removeForbiddenWords(string $title): string
    {
        foreach (self::FORBIDDEN_WORDS as $word) {
            $title = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', '', $title);
        }

        // Limpar espaços extras após remoção
        $title = preg_replace('/\s+/', ' ', trim($title));

        return $title;
    }

    /**
     * Trunca título de forma inteligente
     */
    private function truncateTitle(string $title, int $maxLength): string
    {
        if (strlen($title) <= $maxLength) {
            return $title;
        }

        // Tentar cortar em espaço
        $truncated = substr($title, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace > $maxLength * 0.7) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return trim($truncated);
    }

    /**
     * Aplica capitalização correta
     */
    private function properCase(string $title): string
    {
        // Primeira letra maiúscula, resto minúscula para palavras longas
        $words = explode(' ', $title);
        $result = [];

        $skipWords = ['de', 'da', 'do', 'das', 'dos', 'e', 'com', 'para', 'por', 'em', 'a', 'o'];

        foreach ($words as $i => $word) {
            // Manter siglas em maiúsculas
            if (strlen($word) <= 4 && ctype_upper(str_replace(['-', '/'], '', $word))) {
                $result[] = $word;
            }
            // Manter marcas/modelos conhecidos
            elseif (preg_match('/^[A-Z][a-z]+$/', $word)) {
                $result[] = $word;
            }
            // Primeira palavra sempre capitalizada
            elseif ($i === 0) {
                $result[] = ucfirst(mb_strtolower($word));
            }
            // Palavras de ligação em minúsculas
            elseif (in_array(mb_strtolower($word), $skipWords)) {
                $result[] = mb_strtolower($word);
            }
            // Resto capitalizado
            else {
                $result[] = ucfirst(mb_strtolower($word));
            }
        }

        return implode(' ', $result);
    }

    /**
     * Otimiza descrição
     */
    private function optimizeDescription(string $description, array $item, string $level): array
    {
        $original = $description;
        $modifications = [];

        // Se descrição muito curta, expandir
        if (strlen($description) < 500) {
            $description = $this->expandDescription($description, $item);
            $modifications[] = 'expanded';
        }

        // Adicionar estrutura com bullets
        if (strpos($description, '•') === false && strpos($description, '-') === false) {
            $description = $this->addBulletStructure($description, $item);
            $modifications[] = 'added_bullets';
        }

        // Remover palavras proibidas
        $beforeForbidden = $description;
        foreach (self::FORBIDDEN_WORDS as $word) {
            $description = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', '', $description);
        }
        if ($description !== $beforeForbidden) {
            $modifications[] = 'removed_forbidden';
        }

        // Aggressive: reescrever
        if ($level === self::LEVEL_AGGRESSIVE) {
            $description = $this->rewriteDescription($description, $item);
            $modifications[] = 'rewritten';
        }

        return [
            'description' => $description,
            'original' => $original,
            'changed' => $description !== $original,
            'modifications' => $modifications,
            'length' => strlen($description),
        ];
    }

    /**
     * Expande descrição curta
     */
    private function expandDescription(string $description, array $item): string
    {
        $brand = $this->extractBrand($item);
        $category = $item['category_id'] ?? '';

        $expanded = $description;

        // Adicionar cabeçalho
        if ($brand) {
            $expanded = "📦 Produto Original {$brand}\n\n" . $expanded;
        }

        // Adicionar seção de características
        $expanded .= "\n\n📋 CARACTERÍSTICAS:\n";

        $attributes = $item['attributes'] ?? [];
        foreach (array_slice($attributes, 0, 10) as $attr) {
            if (!empty($attr['value_name'])) {
                $expanded .= "• {$attr['name']}: {$attr['value_name']}\n";
            }
        }

        // Adicionar rodapé
        $expanded .= "\n📦 ENVIO:\n";
        $expanded .= "• Produto pronta entrega\n";
        $expanded .= "• Enviamos em até 24h úteis\n";
        $expanded .= "• Embalagem segura\n";

        return $expanded;
    }

    /**
     * Adiciona estrutura de bullets
     */
    private function addBulletStructure(string $description, array $item): string
    {
        // Dividir em sentenças
        $sentences = preg_split('/[.!?]+/', $description);
        $sentences = array_filter(array_map('trim', $sentences));

        if (count($sentences) <= 2) {
            return $description;
        }

        $structured = array_shift($sentences) . "\n\n";
        $structured .= "✅ DESTAQUES:\n";

        foreach (array_slice($sentences, 0, 6) as $sentence) {
            if (strlen($sentence) > 10) {
                $structured .= "• " . ucfirst($sentence) . "\n";
            }
        }

        return $structured;
    }

    /**
     * Reescreve descrição (modo agressivo)
     */
    private function rewriteDescription(string $description, array $item): string
    {
        $title = $item['title'] ?? '';
        $brand = $this->extractBrand($item);
        $model = $this->extractModel($item);
        $price = $item['price'] ?? 0;

        $template = "🎯 {$title}\n\n";

        if ($brand) {
            $template .= "✅ Produto Original {$brand}";
            if ($model) {
                $template .= " - Modelo {$model}";
            }
            $template .= "\n\n";
        }

        $template .= "📋 SOBRE O PRODUTO:\n";
        $template .= $description . "\n\n";

        $template .= "🛒 POR QUE COMPRAR CONOSCO?\n";
        $template .= "• Produto 100% original e lacrado\n";
        $template .= "• Nota fiscal inclusa\n";
        $template .= "• Garantia de fábrica\n";
        $template .= "• Atendimento especializado\n\n";

        $template .= "📦 ENVIO:\n";
        $template .= "• Embalagem reforçada\n";
        $template .= "• Envio em até 24h úteis\n";
        $template .= "• Código de rastreamento\n\n";

        $template .= "⚡ Não perca, garanta já o seu!";

        return $template;
    }

    /**
     * Sugere atributos faltantes
     */
    private function suggestAttributes(array $item): array
    {
        $existing = [];
        foreach ($item['attributes'] ?? [] as $attr) {
            $existing[$attr['id']] = $attr['value_name'] ?? null;
        }

        $suggestions = [];

        // Atributos críticos
        $critical = ['BRAND', 'MODEL', 'GTIN', 'MPN'];

        foreach ($critical as $attrId) {
            if (!isset($existing[$attrId]) || empty($existing[$attrId])) {
                $suggestions[] = [
                    'id' => $attrId,
                    'importance' => 'high',
                    'reason' => 'Atributo crítico para SEO e catálogo',
                ];
            }
        }

        return [
            'existing_count' => count($existing),
            'suggestions' => $suggestions,
            'completeness' => $this->calculateAttributeCompleteness($item),
        ];
    }

    /**
     * Calcula completude de atributos
     */
    private function calculateAttributeCompleteness(array $item): float
    {
        $attributes = $item['attributes'] ?? [];
        $filled = 0;

        foreach ($attributes as $attr) {
            if (!empty($attr['value_name']) || !empty($attr['value_id'])) {
                $filled++;
            }
        }

        if (count($attributes) === 0) {
            return 0;
        }

        return round(($filled / count($attributes)) * 100, 1);
    }

    /**
     * Calcula score SEO geral (0-100)
     */
    public function calculateSEOScore(array $item): array
    {
        $scores = [];

        // 1. Título (30 pontos)
        $title = $item['title'] ?? '';
        $titleLength = strlen($title);
        $titleScore = 0;

        if ($titleLength >= 45 && $titleLength <= 58) {
            $titleScore = 30;
        } elseif ($titleLength >= 30 && $titleLength <= 60) {
            $titleScore = 20;
        } elseif ($titleLength > 0) {
            $titleScore = 10;
        }

        // Penalizar palavras proibidas
        foreach (self::FORBIDDEN_WORDS as $word) {
            if (stripos($title, $word) !== false) {
                $titleScore -= 5;
            }
        }
        $titleScore = max(0, $titleScore);
        $scores['title'] = $titleScore;

        // 2. Descrição (25 pontos)
        $description = $item['description'] ?? '';
        $descLength = strlen($description);
        $descScore = 0;

        if ($descLength >= 1000) {
            $descScore = 25;
        } elseif ($descLength >= 500) {
            $descScore = 20;
        } elseif ($descLength >= 200) {
            $descScore = 10;
        } elseif ($descLength > 0) {
            $descScore = 5;
        }
        $scores['description'] = $descScore;

        // 3. Atributos (25 pontos)
        $attributes = $item['attributes'] ?? [];
        $attrScore = 0;

        $hasBrand = false;
        $hasModel = false;
        $hasGtin = false;

        foreach ($attributes as $attr) {
            $id = $attr['id'] ?? '';
            $hasValue = !empty($attr['value_name']) || !empty($attr['value_id']);

            if ($id === 'BRAND' && $hasValue) $hasBrand = true;
            if ($id === 'MODEL' && $hasValue) $hasModel = true;
            if ($id === 'GTIN' && $hasValue) $hasGtin = true;
        }

        if ($hasBrand) $attrScore += 10;
        if ($hasModel) $attrScore += 8;
        if ($hasGtin) $attrScore += 7;
        $scores['attributes'] = $attrScore;

        // 4. Imagens (20 pontos)
        $pictures = $item['pictures'] ?? [];
        $imgScore = min(20, count($pictures) * 3);
        $scores['images'] = $imgScore;

        // Total
        $total = array_sum($scores);

        return [
            'total' => $total,
            'max' => 100,
            'breakdown' => $scores,
            'grade' => $this->getGrade($total),
        ];
    }

    /**
     * Obtém nota (A-F)
     */
    private function getGrade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        if ($score >= 50) return 'E';
        return 'F';
    }

    /**
     * Extrai marca do item
     */
    private function extractBrand(array $item): ?string
    {
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'BRAND' && !empty($attr['value_name'])) {
                return $attr['value_name'];
            }
        }
        return null;
    }

    /**
     * Extrai modelo do item
     */
    private function extractModel(array $item): ?string
    {
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'MODEL' && !empty($attr['value_name'])) {
                return $attr['value_name'];
            }
        }
        return null;
    }

    /**
     * Obtém configuração de otimização do usuário
     */
    public function getUserOptimizationLevel(): string
    {
        $stmt = $this->db->prepare("
            SELECT seo_optimization_level 
            FROM clone_user_settings 
            WHERE account_id = :account_id
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['seo_optimization_level'] ?? self::LEVEL_STANDARD;
    }

    /**
     * Define configuração de otimização do usuário
     */
    public function setUserOptimizationLevel(string $level): bool
    {
        if (!in_array($level, [self::LEVEL_NONE, self::LEVEL_BASIC, self::LEVEL_STANDARD, self::LEVEL_AGGRESSIVE])) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO clone_user_settings (account_id, seo_optimization_level, updated_at)
            VALUES (:account_id, :level, NOW())
            ON DUPLICATE KEY UPDATE
            seo_optimization_level = :level2,
            updated_at = NOW()
        ");

        return $stmt->execute([
            ':account_id' => $this->accountId,
            ':level' => $level,
            ':level2' => $level,
        ]);
    }

    /**
     * Obtém estatísticas de otimizações
     */
    public function getOptimizationStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_optimizations,
                AVG(seo_score_before) as avg_score_before,
                AVG(seo_score_after) as avg_score_after
            FROM clone_seo_optimizations
            WHERE account_id = :account_id
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_optimizations' => intval($stats['total_optimizations'] ?? 0),
            'avg_score_before' => round(floatval($stats['avg_score_before'] ?? 0), 1),
            'avg_score_after' => round(floatval($stats['avg_score_after'] ?? 0), 1),
            'avg_improvement' => round(
                floatval($stats['avg_score_after'] ?? 0) - floatval($stats['avg_score_before'] ?? 0),
                1
            ),
        ];
    }

    /**
     * Registra otimização realizada
     */
    public function logOptimization(string $itemId, int $scoreBefore, int $scoreAfter, string $level): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_seo_optimizations 
            (account_id, item_id, seo_score_before, seo_score_after, optimization_level, created_at)
            VALUES 
            (:account_id, :item_id, :before, :after, :level, NOW())
        ");
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':item_id' => $itemId,
            ':before' => $scoreBefore,
            ':after' => $scoreAfter,
            ':level' => $level,
        ]);
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
            CREATE TABLE IF NOT EXISTS clone_user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL UNIQUE,
                seo_optimization_level ENUM('none', 'basic', 'standard', 'aggressive') DEFAULT 'standard',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_seo_optimizations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                seo_score_before INT DEFAULT 0,
                seo_score_after INT DEFAULT 0,
                optimization_level VARCHAR(20) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account (account_id),
                INDEX idx_item (item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $checked = true;
    }
}
