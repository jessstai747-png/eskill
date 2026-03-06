<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Service para integração SEO no sistema de clonagem
 *
 * Aplica otimizações SEO automáticas aos anúncios clonados:
 * - Análise e otimização de título
 * - Otimização de descrição
 * - Validação de atributos obrigatórios
 * - Recomendações de imagens
 * - Análise de keywords
 *
 * Integra com o SeoAnalyzerService existente
 *
 * @package App\Services
 */
class CloneSeoIntegrationService
{
    private PDO $db;
    private SeoAnalyzerService $seoAnalyzer;
    private int $accountId;

    // Níveis de otimização
    public const OPTIMIZATION_NONE = 'none';
    public const OPTIMIZATION_BASIC = 'basic';
    public const OPTIMIZATION_ADVANCED = 'advanced';
    public const OPTIMIZATION_AGGRESSIVE = 'aggressive';

    // Score mínimo para publicação
    private const MIN_SCORE_THRESHOLD = 60;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->seoAnalyzer = new SeoAnalyzerService($accountId);
    }

    /**
     * Analisar item antes da clonagem
     *
     * @param string $itemId ID do item original
     * @param string $optimizationLevel Nível de otimização
     * @return array{
     *     score: int,
     *     grade: string,
     *     should_clone: bool,
     *     optimizations_suggested: array,
     *     warnings: array,
     *     analysis: array
     * }
     */
    public function analyzeBeforeClone(string $itemId, string $optimizationLevel = self::OPTIMIZATION_BASIC): array
    {
        // Obter análise SEO completa
        $analysis = $this->seoAnalyzer->analyzeItem($itemId);

        if (isset($analysis['error'])) {
            return [
                'score' => 0,
                'grade' => 'F',
                'should_clone' => false,
                'optimizations_suggested' => [],
                'warnings' => ['Erro ao analisar item: ' . $analysis['error']],
                'analysis' => $analysis,
            ];
        }

        $score = $analysis['score'] ?? 0;
        $grade = $analysis['grade'] ?? 'F';

        // Decidir se deve clonar baseado no score
        $shouldClone = $score >= self::MIN_SCORE_THRESHOLD;

        // Sugestões de otimização baseadas no nível
        $optimizations = $this->generateOptimizations($analysis, $optimizationLevel);

        // Warnings se score muito baixo
        $warnings = [];
        if ($score < self::MIN_SCORE_THRESHOLD) {
            $warnings[] = "Score SEO baixo ({$score}/100). Recomenda-se otimizar antes de clonar.";
        }

        if (!empty($analysis['critical_issues'])) {
            $warnings = array_merge($warnings, $analysis['critical_issues']);
        }

        return [
            'score' => $score,
            'grade' => $grade,
            'should_clone' => $shouldClone,
            'optimizations_suggested' => $optimizations,
            'warnings' => $warnings,
            'analysis' => $analysis,
        ];
    }

    /**
     * Aplicar otimizações SEO a dados de item
     *
     * @param array $itemData Dados do item a clonar
     * @param string $optimizationLevel Nível de otimização
     * @param array $options Opções adicionais
     * @return array Item otimizado
     */
    public function applyOptimizations(array $itemData, string $optimizationLevel, array $options = []): array
    {
        $optimized = $itemData;

        switch ($optimizationLevel) {
            case self::OPTIMIZATION_BASIC:
                $optimized = $this->applyBasicOptimizations($optimized, $options);
                break;

            case self::OPTIMIZATION_ADVANCED:
                $optimized = $this->applyBasicOptimizations($optimized, $options);
                $optimized = $this->applyAdvancedOptimizations($optimized, $options);
                break;

            case self::OPTIMIZATION_AGGRESSIVE:
                $optimized = $this->applyBasicOptimizations($optimized, $options);
                $optimized = $this->applyAdvancedOptimizations($optimized, $options);
                $optimized = $this->applyAggressiveOptimizations($optimized, $options);
                break;

            case self::OPTIMIZATION_NONE:
            default:
                // Sem otimizações
                break;
        }

        // Registrar otimizações aplicadas
        $optimized['_seo_optimizations_applied'] = [
            'level' => $optimizationLevel,
            'timestamp' => date('Y-m-d H:i:s'),
            'changes' => $this->detectChanges($itemData, $optimized),
        ];

        return $optimized;
    }

    /**
     * Otimizações básicas
     *
     * @param array $item
     * @param array $options
     * @return array
     */
    private function applyBasicOptimizations(array $item, array $options): array
    {
        // 1. Otimizar título
        if (!empty($item['title'])) {
            $item['title'] = $this->optimizeTitle($item['title'], 'basic');
        }

        // 2. Limpar descrição de termos proibidos
        if (!empty($item['description'])) {
            $item['description'] = $this->cleanDescription($item['description']);
        }

        // 3. Garantir atributos obrigatórios
        if (!empty($item['attributes']) && !empty($item['category_id'])) {
            $item['attributes'] = $this->ensureRequiredAttributes(
                $item['attributes'],
                $item['category_id']
            );
        }

        return $item;
    }

    /**
     * Otimizações avançadas
     *
     * @param array $item
     * @param array $options
     * @return array
     */
    private function applyAdvancedOptimizations(array $item, array $options): array
    {
        // 1. Otimizar título com keywords
        if (!empty($item['title']) && !empty($item['category_id'])) {
            $item['title'] = $this->optimizeTitle($item['title'], 'advanced', [
                'category_id' => $item['category_id'],
            ]);
        }

        // 2. Enriquecer descrição
        if (!empty($item['description'])) {
            $item['description'] = $this->enrichDescription($item['description'], $item);
        }

        // 3. Adicionar atributos recomendados
        if (!empty($item['category_id'])) {
            $item['attributes'] = $this->addRecommendedAttributes(
                $item['attributes'] ?? [],
                $item['category_id'],
                $item
            );
        }

        // 4. Otimizar ordem das imagens
        if (!empty($item['pictures'])) {
            $item['pictures'] = $this->optimizeImageOrder($item['pictures']);
        }

        return $item;
    }

    /**
     * Otimizações agressivas
     *
     * @param array $item
     * @param array $options
     * @return array
     */
    private function applyAggressiveOptimizations(array $item, array $options): array
    {
        // 1. Reescrever título completamente
        if (!empty($item['title']) && !empty($item['category_id'])) {
            $item['title'] = $this->rewriteTitle($item['title'], $item);
        }

        // 2. Reestruturar descrição com template SEO
        if (!empty($item['description'])) {
            $item['description'] = $this->rewriteDescription($item['description'], $item);
        }

        // 3. Inferir atributos faltantes
        $item['attributes'] = $this->inferMissingAttributes(
            $item['attributes'] ?? [],
            $item
        );

        return $item;
    }

    /**
     * Otimizar título
     *
     * @param string $title Título original
     * @param string $level 'basic' ou 'advanced'
     * @param array $context Contexto adicional
     * @return string Título otimizado
     */
    private function optimizeTitle(string $title, string $level = 'basic', array $context = []): string
    {
        $title = trim($title);

        // Remover múltiplos espaços
        $title = preg_replace('/\s+/', ' ', $title);

        // Remover pontuações excessivas
        $title = preg_replace('/[!]{2,}/', '!', $title);
        $title = preg_replace('/[?]{2,}/', '?', $title);
        $title = preg_replace('/\.{2,}/', '...', $title);

        // Remover termos proibidos
        $forbiddenWords = [
            'promoção',
            'oferta',
            'desconto',
            'barato',
            'liquidação',
            'imperdível',
            'oportunidade',
            'aproveite',
            'compre já',
            'melhor preço',
            'menor preço',
            'frete grátis',
        ];

        foreach ($forbiddenWords as $word) {
            $title = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', '', $title);
        }

        // Limpar espaços extras resultantes
        $title = trim(preg_replace('/\s+/', ' ', $title));

        // Se nível avançado, adicionar keywords estratégicas
        if ($level === 'advanced' && !empty($context['category_id'])) {
            $title = $this->addStrategicKeywords($title, $context['category_id']);
        }

        // Ajustar comprimento (ideal: 45-58 caracteres)
        $length = mb_strlen($title);
        if ($length > 60) {
            // Truncar preservando palavras completas
            $title = mb_substr($title, 0, 57);
            $lastSpace = mb_strrpos($title, ' ');
            if ($lastSpace !== false) {
                $title = mb_substr($title, 0, $lastSpace);
            }
            $title .= '...';
        }

        return $title;
    }

    /**
     * Adicionar keywords estratégicas ao título
     *
     * @param string $title
     * @param string $categoryId
     * @return string
     */
    private function addStrategicKeywords(string $title, string $categoryId): string
    {
        // Keywords de alto impacto
        $highImpactWords = ['Original', 'Novo', 'Lacrado', 'Garantia', 'Nota Fiscal'];

        // Verificar se já tem alguma
        $hasKeyword = false;
        foreach ($highImpactWords as $word) {
            if (stripos($title, $word) !== false) {
                $hasKeyword = true;
                break;
            }
        }

        // Se não tem e tem espaço, adicionar
        if (!$hasKeyword && mb_strlen($title) < 50) {
            $title .= ' - Original';
        }

        return $title;
    }

    /**
     * Limpar descrição de termos proibidos
     *
     * @param string $description
     * @return string
     */
    private function cleanDescription(string $description): string
    {
        // Remover emojis excessivos
        $description = preg_replace('/[\x{1F600}-\x{1F64F}]{3,}/u', '', $description);

        // Remover CAPS LOCK excessivo
        $description = preg_replace_callback('/\b[A-Z]{4,}\b/', function ($matches) {
            return ucfirst(strtolower($matches[0]));
        }, $description);

        // Remover repetições de pontuação
        $description = preg_replace('/[!]{3,}/', '!!', $description);
        $description = preg_replace('/[?]{3,}/', '??', $description);

        return trim($description);
    }

    /**
     * Enriquecer descrição com informações SEO
     *
     * @param string $description
     * @param array $item
     * @return string
     */
    private function enrichDescription(string $description, array $item): string
    {
        // Se descrição muito curta, adicionar seção de especificações
        if (mb_strlen($description) < 500 && !empty($item['attributes'])) {
            $description .= "\n\n📋 ESPECIFICAÇÕES TÉCNICAS:\n";

            foreach ($item['attributes'] as $attr) {
                if (!empty($attr['value_name'])) {
                    $description .= "\n• {$attr['name']}: {$attr['value_name']}";
                }
            }
        }

        // Adicionar call-to-action sutil
        if (!stripos($description, 'dúvida')) {
            $description .= "\n\n❓ Ficou com alguma dúvida? Entre em contato!";
        }

        return $description;
    }

    /**
     * Reescrever título completamente
     *
     * @param string $originalTitle
     * @param array $item
     * @return string
     */
    private function rewriteTitle(string $originalTitle, array $item): string
    {
        // Extrair informações chave
        $brand = '';
        $model = '';

        if (!empty($item['attributes'])) {
            foreach ($item['attributes'] as $attr) {
                if ($attr['id'] === 'BRAND') {
                    $brand = $attr['value_name'] ?? '';
                } elseif ($attr['id'] === 'MODEL') {
                    $model = $attr['value_name'] ?? '';
                }
            }
        }

        // Construir título otimizado
        $parts = array_filter([$brand, $model]);

        if (empty($parts)) {
            // Usar título original otimizado
            return $this->optimizeTitle($originalTitle, 'advanced', ['category_id' => $item['category_id'] ?? '']);
        }

        $newTitle = implode(' ', $parts);

        // Adicionar keyword de impacto
        $newTitle .= ' - Original Novo com Garantia';

        // Ajustar comprimento
        if (mb_strlen($newTitle) > 60) {
            $newTitle = mb_substr($newTitle, 0, 57) . '...';
        }

        return $newTitle;
    }

    /**
     * Reescrever descrição com template SEO
     *
     * @param string $originalDescription
     * @param array $item
     * @return string
     */
    private function rewriteDescription(string $originalDescription, array $item): string
    {
        $sections = [];

        // 1. Introdução chamativa
        $sections[] = "✅ PRODUTO 100% ORIGINAL E NOVO";
        $sections[] = "";

        // 2. Descrição original (limpa)
        $sections[] = $this->cleanDescription($originalDescription);
        $sections[] = "";

        // 3. Especificações técnicas
        if (!empty($item['attributes'])) {
            $sections[] = "📋 ESPECIFICAÇÕES TÉCNICAS:";
            $sections[] = "";

            foreach ($item['attributes'] as $attr) {
                if (!empty($attr['value_name'])) {
                    $sections[] = "• {$attr['name']}: {$attr['value_name']}";
                }
            }
            $sections[] = "";
        }

        // 4. Garantia e segurança
        $sections[] = "🛡️ GARANTIA E SEGURANÇA:";
        $sections[] = "• Produto 100% original";
        $sections[] = "• Nota fiscal inclusa";
        $sections[] = "• Garantia do fabricante";
        $sections[] = "";

        // 5. Call to action
        $sections[] = "❓ FICOU COM ALGUMA DÚVIDA?";
        $sections[] = "Entre em contato através das perguntas!";

        return implode("\n", $sections);
    }

    /**
     * Garantir atributos obrigatórios
     *
     * @param array $attributes
     * @param string $categoryId
     * @return array
     */
    private function ensureRequiredAttributes(array $attributes, string $categoryId): array
    {
        // Obter atributos obrigatórios da categoria
        $categoryAttrs = $this->getCategoryRequiredAttributes($categoryId);

        // Verificar quais faltam
        $existingIds = array_column($attributes, 'id');

        foreach ($categoryAttrs as $required) {
            if (!in_array($required['id'], $existingIds)) {
                // Adicionar com valor padrão se possível
                $attributes[] = [
                    'id' => $required['id'],
                    'value_name' => $required['default_value'] ?? null,
                ];
            }
        }

        return $attributes;
    }

    /**
     * Adicionar atributos recomendados
     *
     * @param array $attributes
     * @param string $categoryId
     * @param array $item
     * @return array
     */
    private function addRecommendedAttributes(array $attributes, string $categoryId, array $item): array
    {
        // Atributos SEO importantes
        $seoAttributes = ['BRAND', 'MODEL', 'GTIN', 'MPN'];

        $existingIds = array_column($attributes, 'id');

        foreach ($seoAttributes as $attrId) {
            if (!in_array($attrId, $existingIds)) {
                // Tentar inferir do título
                $value = $this->inferAttributeFromTitle($attrId, $item['title'] ?? '');
                if ($value) {
                    $attributes[] = [
                        'id' => $attrId,
                        'value_name' => $value,
                    ];
                }
            }
        }

        return $attributes;
    }

    /**
     * Inferir atributos faltantes
     *
     * @param array $attributes
     * @param array $item
     * @return array
     */
    private function inferMissingAttributes(array $attributes, array $item): array
    {
        $title = $item['title'] ?? '';

        // Inferir BRAND
        if (!$this->hasAttribute($attributes, 'BRAND')) {
            $brand = $this->inferBrand($title);
            if ($brand) {
                $attributes[] = ['id' => 'BRAND', 'value_name' => $brand];
            }
        }

        return $attributes;
    }

    /**
     * Otimizar ordem das imagens
     *
     * @param array $pictures
     * @return array
     */
    private function optimizeImageOrder(array $pictures): array
    {
        if (count($pictures) <= 1) {
            return $pictures;
        }

        // Classificar imagens por qualidade (maior resolução primeiro)
        usort($pictures, function (array $a, array $b): int {
            $aSize = ($a['max_size'] ?? $a['size'] ?? '0x0');
            $bSize = ($b['max_size'] ?? $b['size'] ?? '0x0');

            // Extrair resolução (ex: "1200x1200")
            $aPixels = 0;
            $bPixels = 0;
            if (preg_match('/(\d+)x(\d+)/', (string)$aSize, $m)) {
                $aPixels = (int)$m[1] * (int)$m[2];
            }
            if (preg_match('/(\d+)x(\d+)/', (string)$bSize, $m)) {
                $bPixels = (int)$m[1] * (int)$m[2];
            }

            return $bPixels <=> $aPixels;
        });

        return $pictures;
    }

    /**
     * Detectar mudanças aplicadas
     *
     * @param array $original
     * @param array $optimized
     * @return array
     */
    private function detectChanges(array $original, array $optimized): array
    {
        $changes = [];

        if (($original['title'] ?? '') !== ($optimized['title'] ?? '')) {
            $changes['title'] = [
                'from' => $original['title'] ?? '',
                'to' => $optimized['title'] ?? '',
            ];
        }

        if (($original['description'] ?? '') !== ($optimized['description'] ?? '')) {
            $changes['description'] = [
                'from_length' => mb_strlen($original['description'] ?? ''),
                'to_length' => mb_strlen($optimized['description'] ?? ''),
            ];
        }

        $originalAttrCount = count($original['attributes'] ?? []);
        $optimizedAttrCount = count($optimized['attributes'] ?? []);
        if ($originalAttrCount !== $optimizedAttrCount) {
            $changes['attributes_count'] = [
                'from' => $originalAttrCount,
                'to' => $optimizedAttrCount,
            ];
        }

        return $changes;
    }

    /**
     * Gerar sugestões de otimização
     *
     * @param array $analysis
     * @param string $level
     * @return array
     */
    private function generateOptimizations(array $analysis, string $level): array
    {
        $optimizations = [];

        // Baseado na análise, sugerir ações
        if (($analysis['sections']['title']['score'] ?? 0) < 70) {
            $optimizations[] = [
                'type' => 'title',
                'priority' => 'high',
                'action' => 'optimize_title',
                'description' => 'Otimizar título para melhor SEO',
            ];
        }

        if (($analysis['sections']['description']['score'] ?? 0) < 70) {
            $optimizations[] = [
                'type' => 'description',
                'priority' => 'medium',
                'action' => 'enrich_description',
                'description' => 'Enriquecer descrição com mais detalhes',
            ];
        }

        if (($analysis['sections']['attributes']['score'] ?? 0) < 80) {
            $optimizations[] = [
                'type' => 'attributes',
                'priority' => 'high',
                'action' => 'add_attributes',
                'description' => 'Adicionar atributos obrigatórios faltantes',
            ];
        }

        return $optimizations;
    }

    /**
     * Obter atributos obrigatórios da categoria
     *
     * @param string $categoryId
     * @return array
     */
    private function getCategoryRequiredAttributes(string $categoryId): array
    {
        // Cache de 24h
        $cacheKey = "category_required_attrs_{$categoryId}";
        $cached = $this->getFromCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // Buscar via API do Mercado Livre
        try {
            $client = new MercadoLivreClient($this->accountId);
            $response = $client->get("/categories/{$categoryId}/attributes");
            $required = [];
            if (is_array($response)) {
                foreach ($response as $attr) {
                    if (!empty($attr['tags']['required']) || !empty($attr['required'])) {
                        $required[] = $attr;
                    }
                }
            }
        } catch (\Exception $e) {
            $required = [];
        }

        $this->saveToCache($cacheKey, $required, 86400);

        return $required;
    }

    /**
     * Inferir atributo do título
     *
     * @param string $attrId
     * @param string $title
     * @return string|null
     */
    private function inferAttributeFromTitle(string $attrId, string $title): ?string
    {
        if ($attrId === 'BRAND') {
            return $this->inferBrand($title);
        }

        return null;
    }

    /**
     * Inferir marca do título
     *
     * @param string $title
     * @return string|null
     */
    private function inferBrand(string $title): ?string
    {
        // Lista de marcas conhecidas
        $knownBrands = ['Samsung', 'Apple', 'LG', 'Sony', 'Xiaomi', 'Motorola', 'Nokia'];

        foreach ($knownBrands as $brand) {
            if (stripos($title, $brand) !== false) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Verificar se atributo existe
     *
     * @param array $attributes
     * @param string $attrId
     * @return bool
     */
    private function hasAttribute(array $attributes, string $attrId): bool
    {
        foreach ($attributes as $attr) {
            if ($attr['id'] === $attrId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obter do cache
     *
     * @param string $key
     * @return mixed|null
     */
    private function getFromCache(string $key)
    {
        try {
            $cache = new CacheService();
            return $cache->get($key);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Salvar no cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     */
    private function saveToCache(string $key, $value, int $ttl): void
    {
        try {
            $cache = new CacheService();
            $cache->set($key, $value, $ttl);
        } catch (\Exception $e) {
            // Cache indisponível, continuar sem cache
        }
    }

    /**
     * Registrar otimização SEO no banco
     *
     * @param int $jobId
     * @param string $itemId
     * @param array $before
     * @param array $after
     * @return void
     */
    public function logOptimization(int $jobId, string $itemId, array $before, array $after): void
    {
        try {
            $sql = "
                INSERT INTO clone_seo_optimizations
                (job_id, item_id, score_before, score_after, changes_applied, created_at)
                VALUES (:job_id, :item_id, :score_before, :score_after, :changes, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'job_id' => $jobId,
                'item_id' => $itemId,
                'score_before' => $before['score'] ?? 0,
                'score_after' => $after['score'] ?? 0,
                'changes' => json_encode($after['_seo_optimizations_applied'] ?? []),
            ]);
        } catch (\Exception $e) {
            log_error('Erro ao registrar otimização SEO de clone', [
                'job_id' => $jobId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
