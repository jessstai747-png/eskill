<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Services\MercadoLivreClient;
use App\Services\AI\SEO\KeywordKiller;

/**
 * 📋 SEO Technical Specification Generator - Geração de Fichas Técnicas
 * 
 * Cria fichas técnicas completas automaticamente:
 * - Identifica atributos obrigatórios da categoria
 * - Extrai especificações do título e descrição
 * - Usa IA para preencher atributos faltantes
 * - Valida conformidade com regras do ML
 * - Gera GTIN/EAN quando necessário
 * 
 * @author SEO Development Team
 * @version 1.0.0
 */
class SEOTechnicalSpecGenerator
{
    private ?int $accountId;
    private ?MercadoLivreClient $mlClient;
    private KeywordKiller $keywordKiller;
    
    // Padrões para extração de especificações
    private const SPEC_PATTERNS = [
        'memory' => '/(\d+)\s*(GB|MB|TB)/i',
        'screen' => '/(\d+(?:\.\d+)?)\s*["\']?(\s*|[pP]ixel)/i',
        'weight' => '/(\d+(?:\.\d+)?)\s*(kg|g|mg)/i',
        'battery' => '/(\d+)\s*(mAh|Wh)/i',
        'voltage' => '/(\d+)\s*(V|Volts)/i',
        'power' => '/(\d+)\s*(W|Watts)/i',
        'frequency' => '/(\d+)\s*(Hz|kHz|MHz|GHz)/i'
    ];
    
    // Atributos críticos por categoria
    private const CRITICAL_ATTRIBUTES = [
        'MLB1648' => ['BRAND', 'MODEL', 'INTERNAL_MEMORY', 'SCREEN_SIZE', 'PROCESSOR', 'RAM', 'BATTERY_CAPACITY', 'COLOR'],
        'MLB1074' => ['BRAND', 'MODEL', 'POWER', 'VOLTAGE', 'FREQUENCY', 'ENERGY_EFFICIENCY', 'COLOR', 'WEIGHT'],
        'MLB1743' => ['BRAND', 'MODEL', 'GENDER', 'SIZE', 'COLOR', 'MATERIAL', 'STYLE', 'SEASON'],
        'MLB1051' => ['BRAND', 'MODEL', 'GENDER', 'SIZE', 'COLOR', 'MATERIAL', 'SHOE_TYPE', 'CLOSURE_TYPE']
    ];
    
    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->mlClient = $accountId ? new MercadoLivreClient($accountId) : null;
        $this->keywordKiller = new KeywordKiller($accountId);
    }
    
    /**
     * 🚀 Gera ficha técnica completa
     */
    public function generateTechnicalSpec(array $product): array
    {
        $result = [
            'success' => true,
            'product' => $product['title'] ?? '',
            'attributes' => [],
            'validation' => [],
            'compliance' => []
        ];
        
        try {
            // 1. Obter atributos obrigatórios da categoria
            $categoryAttrs = $this->getCategoryAttributes($product['category_id'] ?? '');
            
            // 2. Extrair especificações do produto existente
            $extractedSpecs = $this->extractSpecifications($product);
            
            // 3. Identificar atributos faltantes
            $missingAttrs = $this->identifyMissingAttributes($product, $categoryAttrs);
            
            // 4. Preencher atributos faltantes com IA
            $filledAttrs = $this->fillMissingAttributes($product, $missingAttrs);
            
            // 5. Validar conformidade
            $validation = $this->validateTechnicalSpec($product, $categoryAttrs, $extractedSpecs, $filledAttrs);
            
            // 6. Gerar GTIN/EAN se necessário
            $gtinData = $this->generateGTIN($product);
            
            $result['attributes'] = array_merge($extractedSpecs, $filledAttrs, $gtinData);
            $result['validation'] = $validation;
            $result['compliance'] = $this->checkCompliance($result['attributes'], $categoryAttrs);
            $result['metadata'] = [
                'total_attributes' => count($result['attributes']),
                'required_filled' => $this->countRequiredFilled($result['attributes'], $categoryAttrs),
                'ai_generated' => count($filledAttrs),
                'compliance_score' => $this->calculateComplianceScore($result['compliance'])
            ];
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 📊 Obtém atributos da categoria
     */
    private function getCategoryAttributes(string $categoryId): array
    {
        if (!$this->mlClient || !$categoryId) {
            return [];
        }
        
        try {
            $response = $this->mlClient->get("/categories/{$categoryId}");
            
            $attributes = [];
            foreach ($response['attributes'] ?? [] as $attr) {
                $attributes[] = [
                    'id' => $attr['id'],
                    'name' => $attr['name'],
                    'type' => $attr['value_type'] ?? 'string',
                    'required' => $attr['required'] ?? false,
                    'variation' => $attr['allow_variation'] ?? false,
                    'tags' => $attr['tags'] ?? [],
                    'values' => $attr['values'] ?? []
                ];
            }
            
            return $attributes;
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * 🔍 Extrai especificações do produto
     */
    private function extractSpecifications(array $product): array
    {
        $specs = [];
        
        // 1. Atributos já existentes
        foreach ($product['attributes'] ?? [] as $attr) {
            $specs[] = [
                'id' => $attr['id'],
                'value_name' => $attr['value_name'],
                'source' => 'existing'
            ];
        }
        
        // 2. Extração do título
        $titleSpecs = $this->extractFromTitle($product['title'] ?? '');
        foreach ($titleSpecs as $spec) {
            if (!$this->hasAttribute($specs, $spec['id'])) {
                $spec['source'] = 'title_extraction';
                $specs[] = $spec;
            }
        }
        
        // 3. Extração da descrição
        $descriptionSpecs = $this->extractFromDescription($product['description'] ?? '');
        foreach ($descriptionSpecs as $spec) {
            if (!$this->hasAttribute($specs, $spec['id'])) {
                $spec['source'] = 'description_extraction';
                $specs[] = $spec;
            }
        }
        
        // 4. Padrões numéricos
        $numericSpecs = $this->extractNumericSpecs($product);
        foreach ($numericSpecs as $spec) {
            if (!$this->hasAttribute($specs, $spec['id'])) {
                $spec['source'] = 'pattern_extraction';
                $specs[] = $spec;
            }
        }
        
        return $specs;
    }
    
    /**
     * 🔤 Extrai do título
     */
    private function extractFromTitle(string $title): array
    {
        $specs = [];
        
        // Marca (palavras com letra maiúscula no início)
        if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b/', $title, $matches)) {
            $brand = $matches[1];
            if (!in_array($brand, ['Produto', 'Kit', 'Set', 'Novo', 'Original']) && strlen($brand) <= 20) {
                $specs[] = [
                    'id' => 'BRAND',
                    'value_name' => $brand
                ];
            }
        }
        
        // Modelo (padrões comuns)
        if (preg_match('/([A-Z]{2,4}-?\d{3,4}|[A-Z]+-\d+[A-Z]?)\b/', $title, $matches)) {
            $specs[] = [
                'id' => 'MODEL',
                'value_name' => $matches[1]
            ];
        }
        
        // Cor
        $colors = ['Preto', 'Branco', 'Azul', 'Vermelho', 'Verde', 'Amarelo', 'Rosa', 'Cinza', 'Prata', 'Dourado'];
        foreach ($colors as $color) {
            if (mb_stripos($title, $color) !== false) {
                $specs[] = [
                    'id' => 'COLOR',
                    'value_name' => $color
                ];
                break;
            }
        }
        
        return $specs;
    }
    
    /**
     * 📄 Extrai da descrição
     */
    private function extractFromDescription(string $description): array
    {
        $specs = [];
        
        // Garantia
        if (preg_match('/(\d+)\s*(?:meses|mês|anos|ano)\s*(?:de\s*)?garantia/i', $description, $matches)) {
            $specs[] = [
                'id' => 'WARRANTY',
                'value_name' => $matches[1] . ' ' . (str_contains($matches[1], 'ano') ? 'anos' : 'meses')
            ];
        }
        
        // Material
        $materials = ['Alumínio', 'Aço', 'Plástico', 'Vidro', 'Madeira', 'Tecido', 'Couro', 'Silicone', 'Borracha'];
        foreach ($materials as $material) {
            if (mb_stripos($description, $material) !== false) {
                $specs[] = [
                    'id' => 'MATERIAL',
                    'value_name' => $material
                ];
                break;
            }
        }
        
        // Dimensões
        if (preg_match('/(\d+)\s*[xX]\s*(\d+)\s*[xX]\s*(\d+)\s*(cm|mm|m)/i', $description, $matches)) {
            $specs[] = [
                'id' => 'DIMENSIONS',
                'value_name' => "{$matches[1]}x{$matches[2]}x{$matches[3]}{$matches[4]}"
            ];
        }
        
        return $specs;
    }
    
    /**
     * 🔢 Extrai especificações numéricas usando padrões
     */
    private function extractNumericSpecs(array $product): array
    {
        $specs = [];
        $text = ($product['title'] ?? '') . ' ' . ($product['description'] ?? '');
        
        foreach (self::SPEC_PATTERNS as $attrId => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $i => $value) {
                    $unit = $matches[2][$i] ?? '';
                    
                    $specs[] = [
                        'id' => $attrId,
                        'value_name' => $value . $unit
                    ];
                }
            }
        }
        
        return $specs;
    }
    
    /**
     * ❌ Identifica atributos faltantes
     */
    private function identifyMissingAttributes(array $product, array $categoryAttrs): array
    {
        $existing = [];
        
        // Coletar IDs de atributos existentes
        foreach ($product['attributes'] ?? [] as $attr) {
            $existing[] = $attr['id'];
        }
        
        $missing = [];
        foreach ($categoryAttrs as $catAttr) {
            if (($catAttr['required'] || $catAttr['variation']) && !in_array($catAttr['id'], $existing)) {
                $missing[] = $catAttr;
            }
        }
        
        // Adicionar atributos críticos da categoria
        $criticalAttrs = self::CRITICAL_ATTRIBUTES[$product['category_id'] ?? ''] ?? [];
        foreach ($criticalAttrs as $criticalId) {
            if (!in_array($criticalId, $existing)) {
                $missing[] = [
                    'id' => $criticalId,
                    'name' => $criticalId,
                    'type' => 'string',
                    'required' => true,
                    'variation' => false
                ];
            }
        }
        
        return $missing;
    }
    
    /**
     * 🤖 Preenche atributos faltantes com IA
     */
    private function fillMissingAttributes(array $product, array $missingAttrs): array
    {
        $filled = [];
        
        if (empty($missingAttrs)) {
            return $filled;
        }
        
        foreach ($missingAttrs as $attr) {
            $value = $this->generateAttributeValue($product, $attr);
            if ($value) {
                $filled[] = [
                    'id' => $attr['id'],
                    'value_name' => $value,
                    'source' => 'ai_generated'
                ];
            }
        }
        
        return $filled;
    }
    
    /**
     * 🎯 Gera valor específico para um atributo
     */
    private function generateAttributeValue(array $product, array $attr): ?string
    {
        $title = $product['title'] ?? '';
        $description = $product['description'] ?? '';
        $categoryId = $product['category_id'] ?? '';
        
        // Mapeamento de atributos para geração
        switch ($attr['id']) {
            case 'BRAND':
                return $this->extractBrand($title, $categoryId);
                
            case 'MODEL':
                return $this->extractModel($title);
                
            case 'COLOR':
                return $this->extractColor($title);
                
            case 'SIZE':
                return $this->extractSize($title, $categoryId);
                
            case 'MATERIAL':
                return $this->extractMaterial($description);
                
            case 'GENDER':
                return $this->extractGender($title, $categoryId);
                
            case 'WARRANTY':
                return $this->extractWarranty($description);
                
            case 'INTERNAL_MEMORY':
            case 'RAM':
                return $this->extractMemory($title);
                
            case 'SCREEN_SIZE':
                return $this->extractScreenSize($title);
                
            case 'PROCESSOR':
                return $this->extractProcessor($title);
                
            default:
                return $this->generateGenericValue($product, $attr);
        }
    }
    
    /**
     * ✅ Valida especificação técnica
     */
    private function validateTechnicalSpec(array $product, array $categoryAttrs, array $extracted, array $filled): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'score' => 0
        ];
        
        $allAttrs = array_merge($extracted, $filled);
        $score = 100;
        
        // Verificar atributos obrigatórios
        foreach ($categoryAttrs as $catAttr) {
            if ($catAttr['required'] && !$this->hasAttribute($allAttrs, $catAttr['id'])) {
                $validation['errors'][] = "Atributo obrigatório faltante: {$catAttr['name']}";
                $score -= 20;
            }
        }
        
        // Verificar valores válidos
        foreach ($allAttrs as $attr) {
            $attrConfig = $this->findAttributeConfig($categoryAttrs, $attr['id']);
            if ($attrConfig && !$this->isValidValue($attr, $attrConfig)) {
                $validation['warnings'][] = "Valor possivelmente inválido para {$attr['id']}: {$attr['value_name']}";
                $score -= 10;
            }
        }
        
        // Verificar GTIN
        if (!$this->hasGTIN($allAttrs)) {
            $categoryRequiresGTIN = $this->categoryRequiresGTIN($product['category_id'] ?? '');
            if ($categoryRequiresGTIN) {
                $validation['errors'][] = 'Categoria requer GTIN/EAN';
                $score -= 25;
            } else {
                $validation['warnings'][] = 'GTIN/EAN recomendado para melhor posicionamento';
                $score -= 5;
            }
        }
        
        $validation['valid'] = empty($validation['errors']);
        $validation['score'] = max(0, $score);
        
        return $validation;
    }
    
    /**
     * 📱 Verifica GTIN/EAN existente e sugere preenchimento — NUNCA gera códigos falsos
     *
     * GTINs/EANs são códigos internacionais registrados. Gerar códigos falsos
     * viola políticas do Mercado Livre e pode resultar em banimento da conta.
     */
    private function generateGTIN(array $product): array
    {
        // Se já possui GTIN válido, nada a fazer
        if ($this->hasGTIN($product['attributes'] ?? [])) {
            return [];
        }

        $title = $product['title'] ?? '';
        $brand = $this->extractBrand($title, $product['category_id'] ?? '');

        // Nunca gerar GTIN falso — apenas sugerir que o vendedor informe o código real
        return [
            [
                'id' => 'GTIN',
                'value_name' => null,
                'source' => 'lookup_required',
                'recommendation' => 'Informe o código GTIN/EAN real do produto. '
                    . 'Consulte a embalagem do produto ou o fabricante.'
                    . (!empty($brand) ? " Marca detectada: {$brand}." : ''),
                'priority' => 'high',
            ]
        ];
    }
    
    /**
     * 📋 Helper methods
     */
    private function hasAttribute(array $attributes, string $attrId): bool
    {
        foreach ($attributes as $attr) {
            if (($attr['id'] ?? '') === $attrId) {
                return true;
            }
        }
        return false;
    }
    
    private function findAttributeConfig(array $categoryAttrs, string $attrId): ?array
    {
        foreach ($categoryAttrs as $config) {
            if ($config['id'] === $attrId) {
                return $config;
            }
        }
        return null;
    }
    
    private function isValidValue(array $attr, array $config): bool
    {
        // Se tem valores permitidos, verificar se está na lista
        if (!empty($config['values'])) {
            foreach ($config['values'] as $value) {
                if (($value['name'] ?? '') === $attr['value_name']) {
                    return true;
                }
            }
            return false;
        }
        
        // Validação baseada no tipo
        switch ($config['type']) {
            case 'number':
                return is_numeric($attr['value_name']);
                
            case 'number_unit':
                return preg_match('/\d+\s*[a-zA-Z%]+/', $attr['value_name']);
                
            default:
                return !empty($attr['value_name']);
        }
    }
    
    private function countRequiredFilled(array $attributes, array $categoryAttrs): int
    {
        $count = 0;
        foreach ($categoryAttrs as $config) {
            if ($config['required'] && $this->hasAttribute($attributes, $config['id'])) {
                $count++;
            }
        }
        return $count;
    }
    
    private function checkCompliance(array $attributes, array $categoryAttrs): array
    {
        $compliance = [
            'required_complete' => true,
            'variations_complete' => true,
            'gtin_present' => $this->hasGTIN($attributes),
            'issues' => []
        ];
        
        foreach ($categoryAttrs as $config) {
            if ($config['required'] && !$this->hasAttribute($attributes, $config['id'])) {
                $compliance['required_complete'] = false;
                $compliance['issues'][] = "Required attribute missing: {$config['id']}";
            }
            
            if ($config['variation'] && !$this->hasAttribute($attributes, $config['id'])) {
                $compliance['variations_complete'] = false;
            }
        }
        
        return $compliance;
    }
    
    private function calculateComplianceScore(array $compliance): int
    {
        $score = 100;
        
        if (!$compliance['required_complete']) $score -= 40;
        if (!$compliance['variations_complete']) $score -= 20;
        if (!$compliance['gtin_present']) $score -= 10;
        
        return max(0, $score);
    }
    
    // Métodos de extração específicos
    private function extractBrand(string $title, string $category): ?string
    {
        // Lista de marcas conhecidas por categoria
        $brands = [
            'MLB1648' => ['Samsung', 'Apple', 'Xiaomi', 'Motorola', 'LG', 'Sony', 'Nokia'],
            'MLB1074' => ['Brastemp', 'Consul', 'Electrolux', 'Panasonic', 'LG', 'Samsung'],
            'MLB1743' => ['Nike', 'Adidas', 'Puma', 'Hering', 'Renner', 'C&A', 'Zara'],
            'MLB1051' => ['Nike', 'Adidas', 'Olympikus', 'Vulcabrás', 'Arezzo', 'Beira Rio']
        ];
        
        $categoryBrands = $brands[$category] ?? [];
        
        foreach ($categoryBrands as $brand) {
            if (mb_stripos($title, $brand) !== false) {
                return $brand;
            }
        }
        
        return null;
    }
    
    private function extractModel(string $title): ?string
    {
        if (preg_match('/([A-Z]{2,4}-?\d{3,4}|[A-Z]+-\d+[A-Z]?)/i', $title, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function extractColor(string $title): ?string
    {
        $colors = ['Preto', 'Branco', 'Azul', 'Vermelho', 'Verde', 'Amarelo', 'Rosa', 'Cinza', 'Prata'];
        
        foreach ($colors as $color) {
            if (mb_stripos($title, $color) !== false) {
                return $color;
            }
        }
        
        return null;
    }
    
    private function extractSize(string $title, string $category): ?string
    {
        // Roupas
        $clothingSizes = ['PP', 'P', 'M', 'G', 'GG', 'XG', 'XXG'];
        foreach ($clothingSizes as $size) {
            if (mb_stripos($title, $size) !== false) {
                return $size;
            }
        }
        
        // Calçados
        if (preg_match('/\b(\d{2,2})\s*(?:n[°º]?|num)?\b/i', $title, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function extractMaterial(string $description): ?string
    {
        $materials = ['Alumínio', 'Aço', 'Plástico', 'Vidro', 'Madeira', 'Tecido', 'Couro', 'Silicone'];
        
        foreach ($materials as $material) {
            if (mb_stripos($description, $material) !== false) {
                return $material;
            }
        }
        
        return null;
    }
    
    private function extractGender(string $title, string $category): ?string
    {
        $genders = ['Masculino', 'Feminino', 'Unissex', 'Infantil'];
        
        foreach ($genders as $gender) {
            if (mb_stripos($title, $gender) !== false) {
                return $gender;
            }
        }
        
        // Inferência por categoria
        if (in_array($category, ['MLB1743', 'MLB1051'])) {
            if (mb_stripos($title, 'femin') !== false) return 'Feminino';
            if (mb_stripos($title, 'mascul') !== false) return 'Masculino';
            if (mb_stripos($title, 'infantil') !== false) return 'Infantil';
        }
        
        return 'Unissex';
    }
    
    private function extractWarranty(string $description): ?string
    {
        if (preg_match('/(\d+)\s*(?:meses|mês|anos|ano)/i', $description, $matches)) {
            return $matches[1] . ' ' . (str_contains($matches[1], 'ano') ? 'anos' : 'meses');
        }
        
        return '12 meses'; // Default
    }
    
    private function extractMemory(string $title): ?string
    {
        if (preg_match('/(\d+)\s*(GB|TB|MB)/i', $title, $matches)) {
            return $matches[1] . $matches[2];
        }
        
        return null;
    }
    
    private function extractScreenSize(string $title): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*["\']?\s*(?:polegadas|pols)/i', $title, $matches)) {
            return $matches[1] . '"';
        }
        
        return null;
    }
    
    private function extractProcessor(string $title): ?string
    {
        $processors = ['Intel', 'AMD', 'Snapdragon', 'MediaTek', 'Apple', 'Qualcomm'];
        
        foreach ($processors as $processor) {
            if (mb_stripos($title, $processor) !== false) {
                return $processor;
            }
        }
        
        return null;
    }
    
    private function generateGenericValue(array $product, array $attr): ?string
    {
        // Para atributos genéricos, usar IA ou valores padrão
        $title = $product['title'] ?? '';
        
        // Tenta extrair do título
        if (preg_match('/\b(' . preg_quote($attr['name'], '/') . '[^,\s]*)\b/i', $title, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function hasGTIN(array $attributes): bool
    {
        return $this->hasAttribute($attributes, 'GTIN') || $this->hasAttribute($attributes, 'EAN');
    }
    
    private function categoryRequiresGTIN(string $categoryId): bool
    {
        // Categorias que tipicamente requerem GTIN
        $gtinCategories = ['MLB1648', 'MLB1074', 'MLB1051'];
        return in_array($categoryId, $gtinCategories);
    }
    
    private function calculateGTINCheckDigit(string $base12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$base12[$i];
            $sum += ($i % 2 === 0) ? $digit * 1 : $digit * 3;
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        return (string)$checkDigit;
    }
}