<?php
declare(strict_types=1);

namespace App\Services\SEO;

/**
 * Serviço de Ficha Técnica com IA Real
 * Gera fichas técnicas completas e otimizadas para SEO
 *
 * @author Sistema SEO Profissional
 * @version 1.0.0
 */
class TechSheetService
{
    private AIClient $ai;

    private const SYSTEM_PROMPT = "Você é um especialista em criar fichas técnicas de produtos para e-commerce.
Você sabe:
- Quais especificações são mais buscadas por compradores
- Como estruturar informações técnicas de forma clara
- Quais atributos são obrigatórios por categoria no Mercado Livre
- Como destacar diferenciais técnicos
- Terminologia técnica correta para cada categoria

Sempre seja preciso, use unidades de medida corretas e forneça informações completas.";

    public function __construct()
    {
        $this->ai = new AIClient();
    }

    /**
     * Gera ficha técnica completa a partir de informações básicas
     */
    public function generate(array $product): array
    {
        $title = $product['title'] ?? '';
        $category = $product['category'] ?? '';
        $brand = $product['brand'] ?? '';
        $description = $product['description'] ?? '';
        $existingSpecs = $product['specifications'] ?? [];

        $existingSpecsStr = !empty($existingSpecs) ?
            json_encode($existingSpecs, JSON_UNESCAPED_UNICODE) :
            'nenhuma especificação fornecida';

        $prompt = "Crie uma ficha técnica completa para este produto:

PRODUTO: {$title}
CATEGORIA: {$category}
MARCA: {$brand}
DESCRIÇÃO: " . mb_substr($description, 0, 500) . "
ESPECIFICAÇÕES EXISTENTES: {$existingSpecsStr}

Analise o produto e crie uma ficha técnica completa. Retorne JSON:
{
    \"product_name\": \"nome completo do produto\",
    \"brand\": \"marca\",
    \"model\": \"modelo identificado ou sugerido\",
    \"category_attributes\": {
        \"ATRIBUTO_OBRIGATÓRIO_1\": \"valor\",
        \"ATRIBUTO_OBRIGATÓRIO_2\": \"valor\"
    },
    \"technical_specifications\": {
        \"Dimensões\": {
            \"Altura\": \"valor com unidade\",
            \"Largura\": \"valor com unidade\",
            \"Profundidade\": \"valor com unidade\",
            \"Peso\": \"valor com unidade\"
        },
        \"Características Técnicas\": {
            \"especificação\": \"valor\"
        },
        \"Material/Composição\": {
            \"Material principal\": \"valor\",
            \"Acabamento\": \"valor\"
        },
        \"Compatibilidade\": {
            \"Compatível com\": \"valores\"
        }
    },
    \"key_features\": [\"5-7 características principais em bullet points\"],
    \"included_items\": [\"itens inclusos na embalagem\"],
    \"warranty\": \"informação de garantia\",
    \"certifications\": [\"certificações se aplicável\"],
    \"seo_attributes\": {
        \"atributos especialmente importantes para SEO nesta categoria\"
    },
    \"missing_info\": [\"informações que seriam importantes mas não foram fornecidas\"],
    \"quality_score\": (0-100),
    \"completeness_percentage\": (0-100)
}

IMPORTANTE: Seja realista - se não houver informação suficiente para inferir um valor, indique como \"Não especificado\" ou inclua em missing_info.";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'max_tokens' => 2500,
            'cache_ttl' => 86400 // 24 horas
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;
        $data['generated_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * Extrai especificações do título
     */
    public function extractFromTitle(string $title, string $category = ''): array
    {
        $prompt = "Extraia todas as especificações técnicas deste título de produto:

TÍTULO: {$title}
CATEGORIA: {$category}

Analise o título e identifique TODAS as especificações mencionadas.

Retorne JSON:
{
    \"extracted_specs\": {
        \"brand\": \"marca se mencionada\",
        \"model\": \"modelo se mencionado\",
        \"color\": \"cor se mencionada\",
        \"size\": \"tamanho se mencionado\",
        \"capacity\": \"capacidade se mencionada\",
        \"material\": \"material se mencionado\",
        \"voltage\": \"voltagem se mencionada\",
        \"power\": \"potência se mencionada\",
        \"other_specs\": {
            \"nome_spec\": \"valor\"
        }
    },
    \"inferred_category\": \"categoria inferida do produto\",
    \"product_type\": \"tipo de produto\",
    \"key_attributes\": [\"atributos mais importantes identificados\"],
    \"missing_important_specs\": [\"especificações importantes que deveriam estar no título mas não estão\"],
    \"title_quality\": {
        \"score\": (0-100),
        \"has_brand\": boolean,
        \"has_model\": boolean,
        \"has_key_specs\": boolean,
        \"suggestions\": [\"sugestões de melhoria\"]
    }
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'cache_ttl' => 3600
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;

        return $data;
    }

    /**
     * Completa ficha técnica incompleta
     */
    public function complete(array $existingSheet, string $category): array
    {
        $existingJson = json_encode($existingSheet, JSON_UNESCAPED_UNICODE);

        $prompt = "Complete esta ficha técnica incompleta:

FICHA ATUAL: {$existingJson}
CATEGORIA: {$category}

Identifique o que está faltando para uma ficha técnica completa desta categoria.

Retorne JSON:
{
    \"completed_sheet\": {
        \"... todos os campos da ficha original com adições ...\"
    },
    \"added_fields\": [\"lista de campos que foram adicionados\"],
    \"suggested_values\": {
        \"campo\": {
            \"suggested_value\": \"valor sugerido\",
            \"confidence\": \"alta/média/baixa\",
            \"reasoning\": \"por que este valor\"
        }
    },
    \"still_missing\": [\"campos que ainda precisam de informação do vendedor\"],
    \"category_requirements\": {
        \"mandatory\": [\"atributos obrigatórios para esta categoria\"],
        \"recommended\": [\"atributos recomendados\"],
        \"optional\": [\"atributos opcionais mas úteis\"]
    },
    \"completeness_before\": (0-100),
    \"completeness_after\": (0-100)
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'max_tokens' => 2000,
            'cache_ttl' => 3600
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;

        return $data;
    }

    /**
     * Valida ficha técnica
     */
    public function validate(array $techSheet, string $category): array
    {
        $sheetJson = json_encode($techSheet, JSON_UNESCAPED_UNICODE);

        $prompt = "Valide esta ficha técnica para a categoria especificada:

FICHA TÉCNICA: {$sheetJson}
CATEGORIA: {$category}

Verifique:
1. Se todos os campos obrigatórios estão preenchidos
2. Se os valores estão em formatos corretos
3. Se as unidades de medida estão corretas
4. Se há inconsistências
5. Se está otimizada para SEO

Retorne JSON:
{
    \"is_valid\": boolean,
    \"validation_score\": (0-100),
    \"errors\": [
        {
            \"field\": \"nome do campo\",
            \"error\": \"descrição do erro\",
            \"severity\": \"critical/warning/info\"
        }
    ],
    \"warnings\": [
        {
            \"field\": \"nome do campo\",
            \"warning\": \"descrição do aviso\",
            \"suggestion\": \"como corrigir\"
        }
    ],
    \"missing_mandatory\": [\"campos obrigatórios faltando\"],
    \"format_issues\": [\"problemas de formatação\"],
    \"seo_issues\": [\"problemas que afetam SEO\"],
    \"recommendations\": [\"recomendações de melhoria\"],
    \"marketplace_compliance\": {
        \"mercado_livre\": boolean,
        \"issues\": [\"problemas de compliance\"]
    }
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'cache_ttl' => 1800 // 30 min
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;
        $data['validated_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * Sugere atributos para categoria
     */
    public function suggestAttributes(string $category, string $productType = ''): array
    {
        $prompt = "Liste todos os atributos recomendados para esta categoria:

CATEGORIA: {$category}
TIPO DE PRODUTO: {$productType}

Retorne JSON:
{
    \"category\": \"{$category}\",
    \"mandatory_attributes\": [
        {
            \"name\": \"nome do atributo\",
            \"type\": \"text/number/select/boolean\",
            \"example\": \"exemplo de valor\",
            \"importance\": \"por que é obrigatório\"
        }
    ],
    \"recommended_attributes\": [
        {
            \"name\": \"nome do atributo\",
            \"type\": \"text/number/select/boolean\",
            \"example\": \"exemplo de valor\",
            \"seo_impact\": \"alto/médio/baixo\"
        }
    ],
    \"optional_attributes\": [
        {
            \"name\": \"nome do atributo\",
            \"type\": \"text/number/select/boolean\",
            \"example\": \"exemplo de valor\"
        }
    ],
    \"common_values\": {
        \"atributo\": [\"valores mais comuns\"]
    },
    \"seo_priority_attributes\": [\"atributos que mais impactam busca\"],
    \"buyer_priority_attributes\": [\"atributos que compradores mais buscam\"]
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'cache_ttl' => 86400 // 24 horas
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;

        return $data;
    }

    /**
     * Verifica se serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->ai->isAvailable();
    }
}
