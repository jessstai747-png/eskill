<?php
declare(strict_types=1);

namespace App\Services\SEO;

class LongTailGeneratorService
{
    private const CATEGORY_CONTEXTS = [
        'MLB3530' => ['delivery', 'carga', 'viagem'],
        'MLB1071' => ['segurança', 'conforto'],
        'MLB1234' => ['urbano', 'lazer'],
    ];

    public function __construct(?int $accountId = null)
    {
        // Initialization code if needed
    }

    /**
     * Gera keywords long tail automaticamente
     */
    public function generate(string $title, string $categoryId): array
    {
        $title = trim($title);
        if ($title === '') {
            return [];
        }

        $titleWords = $this->normalizeTitleWords($title);
        $longTailKeywords = [];

        $capacity = $this->extractCapacity($title);
        $type = $this->extractProductType($title, $capacity);

        if ($capacity) {
            $longTailKeywords = array_merge($longTailKeywords, $this->generateCapacityCombinations($type, $capacity));
        }

        $compatibilities = $this->extractCompatibilities($title);
        if (!empty($compatibilities)) {
            $longTailKeywords = array_merge($longTailKeywords, $this->generateCompatibilityCombinations($type, $compatibilities));
        }

        $contexts = $this->extractContexts($title, $categoryId);
        if (!empty($contexts)) {
            $longTailKeywords = array_merge($longTailKeywords, $this->generateContextCombinations($type, $contexts));
        }

        $longTailKeywords = array_merge($longTailKeywords, $this->generateGeneralCombinations($titleWords));

        return $this->normalizeKeywordList($longTailKeywords);
    }

    /**
     * Gera combinações com capacidade
     */
    private function generateCapacityCombinations(string $type, string $capacity): array
    {
        $combinations = [];

        $descriptors = ['com capacidade de', 'de', 'com', 'com armazenamento de', 'com espaço para'];

        foreach ($descriptors as $descriptor) {
            $combinations[] = $type . ' ' . $descriptor . ' ' . $capacity;
            $combinations[] = $capacity . ' ' . $descriptor . ' ' . $type;
        }

        $capacityNumber = preg_replace('/[^0-9]/', '', $capacity);
        if ($capacityNumber) {
            $combinations[] = $type . ' ' . $capacityNumber . ' litros';
            $combinations[] = $type . ' ' . $capacityNumber . 'L';
            $combinations[] = $type . ' ' . $capacityNumber . ' litros de capacidade';
        }

        return $combinations;
    }

    /**
     * Gera combinações com compatibilidade
     */
    private function generateCompatibilityCombinations(string $type, array $compatibilities): array
    {
        $combinations = [];

        foreach ($compatibilities as $compatibility) {
            $combinations[] = $type . ' compatível com ' . $compatibility;
            $combinations[] = $type . ' para ' . $compatibility;
            $combinations[] = $compatibility . ' com ' . $type;
            $combinations[] = $type . ' funciona com ' . $compatibility;
        }

        return $combinations;
    }

    /**
     * Gera combinações com contexto
     */
    private function generateContextCombinations(string $type, array $contexts): array
    {
        $combinations = [];

        foreach ($contexts as $context) {
            $combinations[] = $type . ' para ' . $context;
            $combinations[] = $type . ' ideal para ' . $context;
            $combinations[] = $type . ' usado em ' . $context;
            $combinations[] = $context . ' com ' . $type;
        }

        return $combinations;
    }

    /**
     * Gera combinações genéricas
     */
    private function generateGeneralCombinations(array $titleWords): array
    {
        $combinations = [];
        $prepositions = ['com', 'para', 'de', 'usando', 'com material de'];

        $wordCount = count($titleWords);
        if ($wordCount === 0) {
            return [];
        }

        for ($i = 0; $i < $wordCount; $i++) {
            for ($j = $i + 1; $j < $wordCount; $j++) {
                foreach ($prepositions as $prep) {
                    $combinations[] = $titleWords[$i] . ' ' . $prep . ' ' . $titleWords[$j];
                    $combinations[] = $titleWords[$j] . ' ' . $prep . ' ' . $titleWords[$i];
                }
            }
        }

        for ($i = 0; $i < $wordCount - 1; $i++) {
            for ($j = $i + 1; $j < $wordCount; $j++) {
                $combinations[] = $titleWords[0] . ' ' . $titleWords[$i] . ' ' . $titleWords[$j];
                $combinations[] = $titleWords[$i] . ' ' . $titleWords[$j] . ' ' . $titleWords[0];
            }
        }

        return $combinations;
    }

    /**
     * Extract capacity from title
     */
    private function extractCapacity(string $title): ?string
    {
        // Look for capacity indicators like "41L", "41 litros", "41 litro"
        $patterns = [
            '/(\d+)\s*(litros?|L|l)/i',
            '/(\d+)\s*x\s*(\d+)\s*(litros?|L|l)/i',  // For patterns like "2x41L"
            '/(\d+)\s*(cm³|ml|mL|galão|galao)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                return trim($matches[0]);
            }
        }
        
        return null;
    }

    /**
     * Extract compatibilities from title
     */
    private function extractCompatibilities(string $title): array
    {
        $compatibilities = [];
        
        // Common motorcycle brands/models that might be in titles
        $commonBrands = [
            'honda', 'yamaha', 'suzuki', 'dafra', 'kawasaki', 'harley', 'ktm',
            'cg 160', 'titan', 'fan', 'bros', 'cb 300', 'factor', 'fazer', 'xtz'
        ];
        
        $titleLower = mb_strtolower($title);
        
        foreach ($commonBrands as $brand) {
            if (strpos($titleLower, $brand) !== false) {
                $compatibilities[] = mb_strtoupper(mb_substr($brand, 0, 1)) . mb_substr($brand, 1);
            }
        }

        return array_values(array_unique($compatibilities));
    }

    /**
     * Extract contexts from title/category
     */
    private function extractContexts(string $title, string $categoryId): array
    {
        $contexts = [];
        $titleLower = mb_strtolower($title);
        
        // Common context indicators
        $contextIndicators = [
            'delivery' => ['delivery', 'entrega', 'motoboy'],
            'trabalho' => ['trabalho', 'profissional'],
            'viagem' => ['viagem', 'passeio', 'turismo'],
            'cidade' => ['cidade', 'urbano', 'dia a dia'],
            'esporte' => ['esporte', 'trilha', 'off road']
        ];
        
        foreach ($contextIndicators as $context => $indicators) {
            foreach ($indicators as $indicator) {
                if (strpos($titleLower, $indicator) !== false) {
                    $contexts[] = $context;
                    break;
                }
            }
        }

        if (isset(self::CATEGORY_CONTEXTS[$categoryId])) {
            $contexts = array_merge($contexts, self::CATEGORY_CONTEXTS[$categoryId]);
        }

        return array_values(array_unique($contexts));
    }

    private function extractProductType(string $title, ?string $capacity): string
    {
        $clean = $capacity ? str_ireplace($capacity, '', $title) : $title;
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words)) {
            return $title;
        }

        return implode(' ', array_slice($words, 0, min(3, count($words))));
    }

    private function normalizeTitleWords(string $title): array
    {
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title));
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        $filtered = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3) {
                $filtered[] = $word;
            }
        }

        return $filtered;
    }

    private function normalizeKeywordList(array $keywords): array
    {
        $normalized = [];

        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                continue;
            }

            $value = $this->normalizeCombination($keyword);
            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        $unique = [];
        foreach ($normalized as $keyword) {
            $key = mb_strtolower($keyword);
            if (!isset($unique[$key])) {
                $unique[$key] = $keyword;
            }
        }

        return array_values($unique);
    }

    private function normalizeCombination(string $combination): ?string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $combination));
        if ($clean === '') {
            return null;
        }

        $words = preg_split('/\s+/', mb_strtolower($clean), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) < 2) {
            return null;
        }

        if (count(array_unique($words)) === 1) {
            return null;
        }

        return $clean;
    }
}