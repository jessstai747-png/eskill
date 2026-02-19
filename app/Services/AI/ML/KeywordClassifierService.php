<?php

declare(strict_types=1);

namespace App\Services\AI\ML;

use App\Database;
use App\Services\AI\Core\AIProviderManager;
use PDO;

/**
 * 🏷️ Keyword Classifier Service
 * 
 * Classifica keywords em categorias semânticas:
 * - CORE: Palavras-chave principais do produto (marca, modelo, tipo)
 * - SUPPORT: Palavras de suporte/características (cor, tamanho, material)
 * - LONG_TAIL: Frases de cauda longa (variações específicas, nichos)
 * - MODIFIER: Modificadores de busca (original, novo, barato, promoção)
 * - BRANDED: Termos de marca específicos
 */
class KeywordClassifierService
{
    private PDO $db;
    private int $accountId;
    private ?AIProviderManager $aiProvider;

    // Tipos de classificação
    public const TYPE_CORE = 'CORE';
    public const TYPE_SUPPORT = 'SUPPORT';
    public const TYPE_LONG_TAIL = 'LONG_TAIL';
    public const TYPE_MODIFIER = 'MODIFIER';
    public const TYPE_BRANDED = 'BRANDED';

    // Pesos por tipo
    private const TYPE_WEIGHTS = [
        self::TYPE_CORE => 1.0,
        self::TYPE_BRANDED => 0.9,
        self::TYPE_SUPPORT => 0.7,
        self::TYPE_MODIFIER => 0.5,
        self::TYPE_LONG_TAIL => 0.6,
    ];

    // Modificadores conhecidos
    private const KNOWN_MODIFIERS = [
        'original', 'novo', 'usado', 'barato', 'promocao', 'promoção',
        'oferta', 'desconto', 'frete gratis', 'frete grátis', 'entrega rapida',
        'pronta entrega', 'atacado', 'varejo', 'importado', 'nacional',
        'premium', 'profissional', 'industrial', 'residencial', 'comercial',
    ];

    // Atributos de suporte conhecidos
    private const SUPPORT_PATTERNS = [
        '/^(preto|branco|azul|vermelho|verde|amarelo|rosa|cinza|marrom|bege|dourado|prata)$/i',
        '/^(pequeno|medio|médio|grande|extra grande|pp|p|m|g|gg|xg|xgg)$/i',
        '/^(110v|220v|bivolt|12v|24v)$/i',
        '/^\d+\s*(ml|l|g|kg|cm|mm|m|un|pç|pcs?)$/i',
        '/^(masculino|feminino|unissex|infantil|adulto|juvenil)$/i',
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->aiProvider = new AIProviderManager();
    }

    /**
     * 🏷️ Classificar uma lista de keywords
     */
    public function classifyKeywords(array $keywords, ?string $categoryContext = null): array
    {
        $classified = [];

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }

            // Tentar cache primeiro
            $cached = $this->getCachedClassification($keyword, $categoryContext);
            if ($cached) {
                $classified[] = $cached;
                continue;
            }

            // Classificar usando regras
            $classification = $this->classifyByRules($keyword, $categoryContext);

            // Se incerto, usar AI
            if ($classification['confidence'] < 0.7) {
                $aiClassification = $this->classifyWithAI($keyword, $categoryContext);
                if ($aiClassification && $aiClassification['confidence'] > $classification['confidence']) {
                    $classification = $aiClassification;
                }
            }

            // Cachear resultado
            $this->cacheClassification($keyword, $categoryContext, $classification);

            $classified[] = $classification;
        }

        return $classified;
    }

    /**
     * 📏 Classificar usando regras heurísticas
     */
    private function classifyByRules(string $keyword, ?string $categoryContext): array
    {
        $keyword = mb_strtolower($keyword);
        $wordCount = count(explode(' ', $keyword));

        // Verificar se é modificador
        foreach (self::KNOWN_MODIFIERS as $modifier) {
            if ($keyword === $modifier || str_contains($keyword, $modifier)) {
                return [
                    'keyword' => $keyword,
                    'type' => self::TYPE_MODIFIER,
                    'weight' => self::TYPE_WEIGHTS[self::TYPE_MODIFIER],
                    'confidence' => 0.9,
                    'reason' => 'Modificador conhecido',
                ];
            }
        }

        // Verificar padrões de suporte
        foreach (self::SUPPORT_PATTERNS as $pattern) {
            if (preg_match($pattern, $keyword)) {
                return [
                    'keyword' => $keyword,
                    'type' => self::TYPE_SUPPORT,
                    'weight' => self::TYPE_WEIGHTS[self::TYPE_SUPPORT],
                    'confidence' => 0.85,
                    'reason' => 'Padrão de atributo',
                ];
            }
        }

        // Long tail (3+ palavras)
        if ($wordCount >= 3) {
            return [
                'keyword' => $keyword,
                'type' => self::TYPE_LONG_TAIL,
                'weight' => self::TYPE_WEIGHTS[self::TYPE_LONG_TAIL],
                'confidence' => 0.7,
                'reason' => 'Frase com 3+ palavras',
            ];
        }

        // Verificar se parece marca (maiúscula, sigla, padrão de nome)
        if ($this->looksLikeBrand($keyword)) {
            return [
                'keyword' => $keyword,
                'type' => self::TYPE_BRANDED,
                'weight' => self::TYPE_WEIGHTS[self::TYPE_BRANDED],
                'confidence' => 0.6, // Menor confiança, pode precisar de AI
                'reason' => 'Possível marca',
            ];
        }

        // Default: assumir CORE
        return [
            'keyword' => $keyword,
            'type' => self::TYPE_CORE,
            'weight' => self::TYPE_WEIGHTS[self::TYPE_CORE],
            'confidence' => 0.5, // Baixa confiança para trigger AI
            'reason' => 'Classificação padrão',
        ];
    }

    /**
     * 🏢 Verificar se parece nome de marca
     */
    private function looksLikeBrand(string $keyword): bool
    {
        // Siglas (todas maiúsculas, 2-5 letras)
        if (preg_match('/^[A-Z]{2,5}$/', $keyword)) {
            return true;
        }

        // Nomes próprios (primeira letra maiúscula)
        if (preg_match('/^[A-Z][a-z]+$/', $keyword)) {
            return true;
        }

        // Padrões de marca conhecidos
        $brandPatterns = [
            '/samsung|apple|sony|lg|philips|panasonic|dell|hp|lenovo/i',
            '/nike|adidas|puma|reebok|mizuno|asics|fila|new balance/i',
            '/tramontina|mondial|britânia|britania|arno|cadence|philco/i',
            '/natura|avon|boticário|boticario|eudora|mary kay/i',
        ];

        foreach ($brandPatterns as $pattern) {
            if (preg_match($pattern, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 🤖 Classificar usando AI
     */
    private function classifyWithAI(string $keyword, ?string $categoryContext): ?array
    {
        try {
            $prompt = $this->buildClassificationPrompt($keyword, $categoryContext);
            
            $response = $this->aiProvider->generate([
                'prompt' => $prompt,
                'max_tokens' => 200,
                'temperature' => 0.3,
            ]);

            if (!$response || !isset($response['text'])) {
                return null;
            }

            return $this->parseAIResponse($keyword, $response['text']);
        } catch (\Exception $e) {
            log_warning('Erro na classificação AI de keyword', [
                'service' => 'KeywordClassifierService',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 📝 Construir prompt para classificação AI
     */
    private function buildClassificationPrompt(string $keyword, ?string $categoryContext): string
    {
        $contextInfo = $categoryContext ? " na categoria: {$categoryContext}" : "";

        return <<<PROMPT
Classifique a seguinte palavra-chave para e-commerce Mercado Livre{$contextInfo}:

Palavra-chave: "{$keyword}"

Tipos possíveis:
- CORE: Palavra principal do produto (tipo, categoria principal)
- BRANDED: Nome de marca
- SUPPORT: Característica/atributo (cor, tamanho, voltagem)
- MODIFIER: Modificador de busca (original, novo, promoção)
- LONG_TAIL: Frase específica de nicho

Responda APENAS no formato:
TIPO: [tipo]
CONFIANÇA: [0.0-1.0]
RAZÃO: [explicação curta]
PROMPT;
    }

    /**
     * 🔍 Parsear resposta da AI
     */
    private function parseAIResponse(string $keyword, string $response): ?array
    {
        $type = self::TYPE_CORE;
        $confidence = 0.7;
        $reason = 'Classificado por AI';

        if (preg_match('/TIPO:\s*(CORE|BRANDED|SUPPORT|MODIFIER|LONG_TAIL)/i', $response, $matches)) {
            $type = strtoupper($matches[1]);
        }

        if (preg_match('/CONFIAN[ÇC]A:\s*([\d.]+)/i', $response, $matches)) {
            $confidence = min(1.0, max(0.0, (float) $matches[1]));
        }

        if (preg_match('/RAZ[ÃA]O:\s*(.+)/i', $response, $matches)) {
            $reason = trim($matches[1]);
        }

        return [
            'keyword' => $keyword,
            'type' => $type,
            'weight' => self::TYPE_WEIGHTS[$type] ?? 0.5,
            'confidence' => $confidence,
            'reason' => $reason,
            'ai_classified' => true,
        ];
    }

    /**
     * 💾 Cachear classificação
     */
    private function cacheClassification(string $keyword, ?string $categoryContext, array $classification): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO keyword_classifications (
                    keyword_hash, keyword, category_context, type, weight, 
                    confidence, reason, created_at
                ) VALUES (
                    :keyword_hash, :keyword, :category_context, :type, :weight,
                    :confidence, :reason, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    weight = VALUES(weight),
                    confidence = VALUES(confidence),
                    reason = VALUES(reason)
            ");

            $stmt->execute([
                'keyword_hash' => md5($keyword . ($categoryContext ?? '')),
                'keyword' => $keyword,
                'category_context' => $categoryContext,
                'type' => $classification['type'],
                'weight' => $classification['weight'],
                'confidence' => $classification['confidence'],
                'reason' => $classification['reason'],
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao cachear classificação de keyword', [
                'service' => 'KeywordClassifierService',
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 📖 Obter classificação do cache
     */
    private function getCachedClassification(string $keyword, ?string $categoryContext): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT keyword, type, weight, confidence, reason
                FROM keyword_classifications
                WHERE keyword_hash = :keyword_hash
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $stmt->execute([
                'keyword_hash' => md5($keyword . ($categoryContext ?? '')),
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'keyword' => $row['keyword'],
                    'type' => $row['type'],
                    'weight' => (float) $row['weight'],
                    'confidence' => (float) $row['confidence'],
                    'reason' => $row['reason'],
                    'cached' => true,
                ];
            }
        } catch (\Exception $e) {
            // Tabela pode não existir ainda
        }

        return null;
    }

    /**
     * 📊 Classificar e agrupar por tipo
     */
    public function classifyAndGroup(array $keywords, ?string $categoryContext = null): array
    {
        $classified = $this->classifyKeywords($keywords, $categoryContext);

        $grouped = [
            self::TYPE_CORE => [],
            self::TYPE_BRANDED => [],
            self::TYPE_SUPPORT => [],
            self::TYPE_MODIFIER => [],
            self::TYPE_LONG_TAIL => [],
        ];

        foreach ($classified as $item) {
            $type = $item['type'] ?? self::TYPE_CORE;
            $grouped[$type][] = $item;
        }

        // Ordenar cada grupo por confiança
        foreach ($grouped as $type => &$items) {
            usort($items, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        }

        return $grouped;
    }

    /**
     * 🎯 Obter keywords otimizadas para título
     */
    public function getOptimizedForTitle(array $keywords, ?string $categoryContext = null, int $maxLength = 60): array
    {
        $grouped = $this->classifyAndGroup($keywords, $categoryContext);

        $selected = [];
        $currentLength = 0;

        // Prioridade: CORE > BRANDED > SUPPORT > MODIFIER
        $priority = [self::TYPE_CORE, self::TYPE_BRANDED, self::TYPE_SUPPORT, self::TYPE_MODIFIER];

        foreach ($priority as $type) {
            foreach ($grouped[$type] as $item) {
                $keyword = $item['keyword'];
                $keywordLength = mb_strlen($keyword);

                if ($currentLength + $keywordLength + 1 <= $maxLength) {
                    $selected[] = $item;
                    $currentLength += $keywordLength + 1; // +1 para espaço
                }
            }
        }

        return [
            'keywords' => $selected,
            'total_length' => $currentLength,
            'remaining_space' => $maxLength - $currentLength,
        ];
    }

    /**
     * 📈 Estatísticas de classificação
     */
    public function getClassificationStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    type,
                    COUNT(*) as count,
                    AVG(confidence) as avg_confidence
                FROM keyword_classifications
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY type
                ORDER BY count DESC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 🔄 Reclassificar keywords com baixa confiança
     */
    public function reclassifyLowConfidence(float $threshold = 0.7): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT keyword, category_context
                FROM keyword_classifications
                WHERE confidence < :threshold
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $stmt->execute(['threshold' => $threshold]);
            $keywords = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $reclassified = 0;
            foreach ($keywords as $row) {
                $this->classifyKeywords([$row['keyword']], $row['category_context']);
                $reclassified++;
            }

            return $reclassified;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
