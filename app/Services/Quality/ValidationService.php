<?php

declare(strict_types=1);

namespace App\Services\Quality;

use App\Services\MercadoLivreClient;
use App\Services\CategoryService;

/**
 * Validation Service - Valida anúncios antes da publicação
 *
 * Usa a API oficial /items/validate do Mercado Livre para:
 * - Validar dados antes de publicar
 * - Detectar erros e warnings
 * - Verificar conformidade com regras da categoria
 * - Identificar campos obrigatórios faltantes
 * - Validar atributos e valores
 * - Checar limites e restrições
 *
 * Evita erros na publicação e economiza chamadas de API
 */
class ValidationService
{
    private MercadoLivreClient $client;
    private CategoryService $categoryService;

    // Tipos de validação
    public const VALIDATION_TYPES = [
        'pre_publish' => 'Pré-publicação',
        'pre_update' => 'Pré-atualização',
        'comprehensive' => 'Completa',
    ];

    // Níveis de severidade
    public const SEVERITY_LEVELS = [
        'error' => 'Erro (bloqueia publicação)',
        'warning' => 'Aviso (permite publicação)',
        'info' => 'Informação',
    ];

    // Categorias de validação
    public const VALIDATION_CATEGORIES = [
        'structure' => 'Estrutura',
        'required_fields' => 'Campos obrigatórios',
        'attributes' => 'Atributos',
        'images' => 'Imagens',
        'price' => 'Preço',
        'shipping' => 'Envio',
        'description' => 'Descrição',
        'category' => 'Categoria',
        'listing_type' => 'Tipo de anúncio',
    ];

    public function __construct(?int $accountId = null)
    {
        $this->client = new MercadoLivreClient($accountId);
        $this->categoryService = new CategoryService($accountId);
    }

    /**
     * Valida dados de um anúncio antes de publicar
     * Usa API oficial /items/validate
     */
    public function validateListing(array $itemData): array
    {
        $validations = [
            'success' => true,
            'can_publish' => true,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // 1. Validação local (rápida)
        $localValidation = $this->performLocalValidation($itemData);
        $validations['local_validation'] = $localValidation;

        if (!$localValidation['passed']) {
            $validations['success'] = false;
            $validations['can_publish'] = false;
        }

        // 2. Validação com API do ML (oficial)
        if ($localValidation['passed']) {
            $apiValidation = $this->performApiValidation($itemData);
            $validations['api_validation'] = $apiValidation;

            if (!$apiValidation['passed']) {
                $validations['can_publish'] = false;
            }
        } else {
            $validations['api_validation'] = [
                'skipped' => true,
                'reason' => 'Validação local falhou',
            ];
        }

        // 3. Validação de categoria
        $categoryValidation = $this->validateCategory($itemData);
        $validations['category_validation'] = $categoryValidation;

        // 4. Validação de atributos
        $attributesValidation = $this->validateAttributes($itemData);
        $validations['attributes_validation'] = $attributesValidation;

        // 5. Consolidar resultados
        $validations['errors'] = $this->collectErrors($localValidation, $apiValidation ?? null, $categoryValidation, $attributesValidation);
        $validations['warnings'] = $this->collectWarnings($localValidation, $apiValidation ?? null, $categoryValidation, $attributesValidation);
        $validations['summary'] = $this->generateSummary($validations);

        return $validations;
    }

    /**
     * Validação local (sem chamada de API)
     */
    private function performLocalValidation(array $itemData): array
    {
        $errors = [];
        $warnings = [];
        $passed = true;

        // 1. Campos obrigatórios básicos
        $requiredFields = ['title', 'category_id', 'price', 'currency_id', 'available_quantity', 'buying_mode', 'condition', 'listing_type_id'];

        foreach ($requiredFields as $field) {
            if (!isset($itemData[$field]) || empty($itemData[$field])) {
                $errors[] = [
                    'field' => $field,
                    'category' => 'required_fields',
                    'severity' => 'error',
                    'message' => "Campo obrigatório '{$field}' está ausente ou vazio",
                ];
                $passed = false;
            }
        }

        // 2. Validação de título
        if (isset($itemData['title'])) {
            $title = $itemData['title'];
            $titleLength = mb_strlen($title);

            if ($titleLength < 10) {
                $errors[] = [
                    'field' => 'title',
                    'category' => 'structure',
                    'severity' => 'error',
                    'message' => 'Título muito curto (mínimo 10 caracteres)',
                ];
                $passed = false;
            }

            if ($titleLength > 60) {
                $errors[] = [
                    'field' => 'title',
                    'category' => 'structure',
                    'severity' => 'error',
                    'message' => 'Título muito longo (máximo 60 caracteres)',
                ];
                $passed = false;
            }

            // Palavras proibidas
            $forbiddenWords = ['whatsapp', 'telefone', 'celular', 'email', 'site', 'www', 'http'];
            foreach ($forbiddenWords as $word) {
                if (stripos($title, $word) !== false) {
                    $errors[] = [
                        'field' => 'title',
                        'category' => 'structure',
                        'severity' => 'error',
                        'message' => "Título contém palavra proibida: '{$word}'",
                    ];
                    $passed = false;
                }
            }
        }

        // 3. Validação de preço
        if (isset($itemData['price'])) {
            $price = $itemData['price'];

            if (!is_numeric($price) || $price <= 0) {
                $errors[] = [
                    'field' => 'price',
                    'category' => 'price',
                    'severity' => 'error',
                    'message' => 'Preço inválido ou zero',
                ];
                $passed = false;
            }

            if ($price > 999999999) {
                $errors[] = [
                    'field' => 'price',
                    'category' => 'price',
                    'severity' => 'error',
                    'message' => 'Preço excede o limite permitido',
                ];
                $passed = false;
            }
        }

        // 4. Validação de quantidade
        if (isset($itemData['available_quantity'])) {
            $quantity = $itemData['available_quantity'];

            if (!is_numeric($quantity) || $quantity < 1) {
                $errors[] = [
                    'field' => 'available_quantity',
                    'category' => 'structure',
                    'severity' => 'error',
                    'message' => 'Quantidade disponível inválida (mínimo 1)',
                ];
                $passed = false;
            }
        }

        // 5. Validação de imagens
        $pictures = $itemData['pictures'] ?? [];
        $pictureCount = count($pictures);

        if ($pictureCount === 0) {
            $warnings[] = [
                'field' => 'pictures',
                'category' => 'images',
                'severity' => 'warning',
                'message' => 'Nenhuma imagem fornecida (recomendado: 6+ imagens)',
            ];
        } else if ($pictureCount < 3) {
            $warnings[] = [
                'field' => 'pictures',
                'category' => 'images',
                'severity' => 'warning',
                'message' => "Apenas {$pictureCount} imagens (recomendado: 6+ imagens)",
            ];
        }

        // Validar formato das imagens
        foreach ($pictures as $index => $picture) {
            if (!isset($picture['source']) && !isset($picture['id'])) {
                $errors[] = [
                    'field' => "pictures[{$index}]",
                    'category' => 'images',
                    'severity' => 'error',
                    'message' => 'Imagem sem source ou id',
                ];
                $passed = false;
            }
        }

        // 6. Validação de currency_id
        if (isset($itemData['currency_id']) && $itemData['currency_id'] !== 'BRL') {
            $errors[] = [
                'field' => 'currency_id',
                'category' => 'structure',
                'severity' => 'error',
                'message' => "Moeda inválida (use 'BRL' para Brasil)",
            ];
            $passed = false;
        }

        // 7. Validação de buying_mode
        $validBuyingModes = ['buy_it_now', 'classified'];
        if (isset($itemData['buying_mode']) && !in_array($itemData['buying_mode'], $validBuyingModes)) {
            $errors[] = [
                'field' => 'buying_mode',
                'category' => 'structure',
                'severity' => 'error',
                'message' => "buying_mode inválido (use: " . implode(', ', $validBuyingModes) . ")",
            ];
            $passed = false;
        }

        // 8. Validação de condition
        $validConditions = ['new', 'used', 'not_specified'];
        if (isset($itemData['condition']) && !in_array($itemData['condition'], $validConditions)) {
            $errors[] = [
                'field' => 'condition',
                'category' => 'structure',
                'severity' => 'error',
                'message' => "condition inválido (use: " . implode(', ', $validConditions) . ")",
            ];
            $passed = false;
        }

        return [
            'passed' => $passed,
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_fields' => count($requiredFields),
        ];
    }

    /**
     * Validação usando API oficial do ML
     */
    private function performApiValidation(array $itemData): array
    {
        try {
            // Endpoint: POST /items/validate
            $response = $this->client->post('/items/validate', $itemData);

            if (isset($response['error'])) {
                return [
                    'passed' => false,
                    'api_available' => true,
                    'errors' => [[
                        'category' => 'api_validation',
                        'severity' => 'error',
                        'message' => $response['message'] ?? 'Erro na validação da API',
                        'details' => $response,
                    ]],
                    'warnings' => [],
                ];
            }

            // Se a API retornou sucesso, o anúncio é válido
            return [
                'passed' => true,
                'api_available' => true,
                'errors' => [],
                'warnings' => [],
                'response' => $response,
            ];
        } catch (\Exception $e) {
            // API pode não estar disponível ou ter timeout
            return [
                'passed' => true, // Não bloqueamos por erro de API
                'api_available' => false,
                'errors' => [],
                'warnings' => [[
                    'category' => 'api_validation',
                    'severity' => 'warning',
                    'message' => 'Não foi possível validar com a API do ML: ' . $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Valida categoria e suas regras
     */
    private function validateCategory(array $itemData): array
    {
        $errors = [];
        $warnings = [];
        $passed = true;

        $categoryId = $itemData['category_id'] ?? null;

        if (empty($categoryId)) {
            return [
                'passed' => false,
                'errors' => [[
                    'field' => 'category_id',
                    'category' => 'category',
                    'severity' => 'error',
                    'message' => 'category_id é obrigatório',
                ]],
                'warnings' => [],
            ];
        }

        try {
            $category = $this->client->get("/categories/{$categoryId}");

            if (isset($category['error'])) {
                $errors[] = [
                    'field' => 'category_id',
                    'category' => 'category',
                    'severity' => 'error',
                    'message' => 'Categoria inválida ou não encontrada',
                ];
                $passed = false;
            }

            // Verificar se aceita listings
            $settings = $category['settings'] ?? [];
            if (isset($settings['listing_allowed']) && !$settings['listing_allowed']) {
                $errors[] = [
                    'field' => 'category_id',
                    'category' => 'category',
                    'severity' => 'error',
                    'message' => 'Esta categoria não aceita anúncios',
                ];
                $passed = false;
            }

            // Verificar buying_modes permitidos
            if (isset($settings['buying_modes']) && !empty($settings['buying_modes'])) {
                $buyingMode = $itemData['buying_mode'] ?? '';
                if (!in_array($buyingMode, $settings['buying_modes'])) {
                    $errors[] = [
                        'field' => 'buying_mode',
                        'category' => 'category',
                        'severity' => 'error',
                        'message' => "buying_mode '{$buyingMode}' não permitido nesta categoria",
                    ];
                    $passed = false;
                }
            }
        } catch (\Exception $e) {
            $warnings[] = [
                'category' => 'category',
                'severity' => 'warning',
                'message' => 'Não foi possível validar categoria completamente',
            ];
        }

        return [
            'passed' => $passed,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Valida atributos
     */
    private function validateAttributes(array $itemData): array
    {
        $errors = [];
        $warnings = [];
        $passed = true;

        $categoryId = $itemData['category_id'] ?? '';
        $itemAttributes = $itemData['attributes'] ?? [];

        if (empty($categoryId)) {
            return ['passed' => true, 'errors' => [], 'warnings' => []];
        }

        try {
            $categoryAttributes = $this->categoryService->getCategoryAttributes($categoryId);

            // Verificar atributos obrigatórios
            $requiredAttributes = array_filter(
                $categoryAttributes,
                fn(array $attr): bool =>
                isset($attr['tags']['required']) && $attr['tags']['required']
            );

            $itemAttributeIds = array_column($itemAttributes, 'id');

            foreach ($requiredAttributes as $reqAttr) {
                if (!in_array($reqAttr['id'], $itemAttributeIds)) {
                    $errors[] = [
                        'field' => "attributes[{$reqAttr['id']}]",
                        'category' => 'attributes',
                        'severity' => 'error',
                        'message' => "Atributo obrigatório '{$reqAttr['name']}' está ausente",
                    ];
                    $passed = false;
                }
            }

            // Validar valores dos atributos
            foreach ($itemAttributes as $itemAttr) {
                $attrId = $itemAttr['id'] ?? null;

                // Buscar definição do atributo
                $attrDef = null;
                foreach ($categoryAttributes as $catAttr) {
                    if ($catAttr['id'] === $attrId) {
                        $attrDef = $catAttr;
                        break;
                    }
                }

                if ($attrDef) {
                    // Validar tipo de valor
                    if (isset($attrDef['value_type']) && $attrDef['value_type'] === 'list') {
                        // Para atributos de lista, validar se o valor está nas opções
                        if (isset($itemAttr['value_id'])) {
                            $validValues = array_column($attrDef['values'] ?? [], 'id');
                            if (!in_array($itemAttr['value_id'], $validValues)) {
                                $warnings[] = [
                                    'field' => "attributes[{$attrId}]",
                                    'category' => 'attributes',
                                    'severity' => 'warning',
                                    'message' => "Valor '{$itemAttr['value_id']}' pode não ser válido para atributo '{$attrDef['name']}'",
                                ];
                            }
                        }
                    }
                }
            }

            // Verificar atributos recomendados
            $recommendedAttributes = array_filter(
                $categoryAttributes,
                fn(array $attr): bool =>
                isset($attr['tags']['recommended']) && $attr['tags']['recommended']
            );

            foreach ($recommendedAttributes as $recAttr) {
                if (!in_array($recAttr['id'], $itemAttributeIds)) {
                    $warnings[] = [
                        'field' => "attributes[{$recAttr['id']}]",
                        'category' => 'attributes',
                        'severity' => 'warning',
                        'message' => "Atributo recomendado '{$recAttr['name']}' não foi preenchido",
                    ];
                }
            }
        } catch (\Exception $e) {
            $warnings[] = [
                'category' => 'attributes',
                'severity' => 'warning',
                'message' => 'Não foi possível validar atributos completamente',
            ];
        }

        return [
            'passed' => $passed,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Coleta todos os erros
     */
    private function collectErrors(...$validations): array
    {
        $allErrors = [];

        foreach ($validations as $validation) {
            if ($validation && isset($validation['errors'])) {
                $allErrors = array_merge($allErrors, $validation['errors']);
            }
        }

        return $allErrors;
    }

    /**
     * Coleta todos os warnings
     */
    private function collectWarnings(...$validations): array
    {
        $allWarnings = [];

        foreach ($validations as $validation) {
            if ($validation && isset($validation['warnings'])) {
                $allWarnings = array_merge($allWarnings, $validation['warnings']);
            }
        }

        return $allWarnings;
    }

    /**
     * Gera resumo da validação
     */
    private function generateSummary(array $validations): array
    {
        return [
            'can_publish' => $validations['can_publish'],
            'total_errors' => count($validations['errors']),
            'total_warnings' => count($validations['warnings']),
            'validations_performed' => [
                'local' => $validations['local_validation']['passed'],
                'api' => $validations['api_validation']['passed'] ?? null,
                'category' => $validations['category_validation']['passed'],
                'attributes' => $validations['attributes_validation']['passed'],
            ],
        ];
    }

    /**
     * Valida múltiplos anúncios em lote
     */
    public function validateBatch(array $itemsData): array
    {
        $results = [];

        foreach ($itemsData as $index => $itemData) {
            $results[$index] = $this->validateListing($itemData);
        }

        return [
            'success' => true,
            'total_items' => count($itemsData),
            'valid_items' => count(array_filter($results, fn(array $r): bool => $r['can_publish'])),
            'invalid_items' => count(array_filter($results, fn(array $r): bool => !$r['can_publish'])),
            'results' => $results,
        ];
    }

    /**
     * Corrige automaticamente erros simples (quando possível)
     */
    public function autoFix(array $itemData): array
    {
        $fixed = $itemData;
        $changes = [];

        // 1. Fixar currency_id
        if (!isset($fixed['currency_id']) || empty($fixed['currency_id'])) {
            $fixed['currency_id'] = 'BRL';
            $changes[] = "currency_id definido como 'BRL'";
        }

        // 2. Fixar buying_mode
        if (!isset($fixed['buying_mode']) || empty($fixed['buying_mode'])) {
            $fixed['buying_mode'] = 'buy_it_now';
            $changes[] = "buying_mode definido como 'buy_it_now'";
        }

        // 3. Fixar listing_type_id
        if (!isset($fixed['listing_type_id']) || empty($fixed['listing_type_id'])) {
            $fixed['listing_type_id'] = 'gold_special';
            $changes[] = "listing_type_id definido como 'gold_special'";
        }

        // 4. Fixar condition
        if (!isset($fixed['condition']) || empty($fixed['condition'])) {
            $fixed['condition'] = 'new';
            $changes[] = "condition definido como 'new'";
        }

        // 5. Limpar título (remover espaços extras)
        if (isset($fixed['title'])) {
            $originalTitle = $fixed['title'];
            $fixed['title'] = preg_replace('/\s+/', ' ', trim($fixed['title']));
            if ($originalTitle !== $fixed['title']) {
                $changes[] = "Título limpo (espaços extras removidos)";
            }
        }

        return [
            'original' => $itemData,
            'fixed' => $fixed,
            'changes' => $changes,
            'changed' => count($changes) > 0,
        ];
    }
}
