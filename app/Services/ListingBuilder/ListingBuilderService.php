<?php

namespace App\Services\ListingBuilder;

use App\Services\MercadoLivreClient;
use App\Services\CategoryService;
use App\Services\SeoAnalyzerService;
use App\Services\Quality\QualityScoreService;
use App\Services\Quality\ValidationService;
use App\Services\Shipping\ShippingOptimizerService;
use App\Services\Shipping\ShippingSimulatorService;
use App\Services\Shipping\DimensionCalculatorService;
use App\Services\ListingBuilder\TemplateManagerService;
use App\Database;
use PDO;

/**
 * Listing Builder Service - Construtor Inteligente de Anúncios
 * 
 * Assistente completo para criação de anúncios otimizados que integra:
 * - SEO Analyzer (títulos otimizados)
 * - Quality Check (validação pré-publicação)
 * - Shipping Optimizer (melhor estratégia de envio)
 * - Templates personalizáveis
 * - Validação em tempo real
 * - Sugestões de melhoria
 */
class ListingBuilderService
{
    private PDO $db;
    private MercadoLivreClient $client;
    private CategoryService $categoryService;
    private SeoAnalyzerService $seoAnalyzer;
    private QualityScoreService $qualityScore;
    private ValidationService $validator;
    private ShippingOptimizerService $shippingOptimizer;
    private DimensionCalculatorService $dimensionCalculator;
    private TemplateManagerService $templateManager;
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->client = new MercadoLivreClient($accountId);
        $this->categoryService = new CategoryService($accountId);
        $this->seoAnalyzer = new SeoAnalyzerService($accountId);
        $this->qualityScore = new QualityScoreService($accountId);
        $this->validator = new ValidationService($accountId);
        $this->shippingOptimizer = new ShippingOptimizerService($accountId);
        $this->dimensionCalculator = new DimensionCalculatorService();
        $this->templateManager = new TemplateManagerService();
    }

    /**
     * Inicia processo de criação de anúncio
     * Retorna dados necessários e sugestões iniciais
     */
    public function startListing(array $options = []): array
    {
        $categoryId = $options['category_id'] ?? null;
        $productName = $options['product_name'] ?? '';

        $result = [
            'success' => true,
            'step' => 'initial',
            'wizard_steps' => $this->getWizardSteps(),
            'required_fields' => $this->getRequiredFields($categoryId),
        ];

        // Se categoria fornecida, buscar informações
        if ($categoryId) {
            $categoryInfo = $this->categoryService->getCategory($categoryId);
            $result['category'] = [
                'id' => $categoryId,
                'name' => $categoryInfo['name'] ?? '',
                'attributes' => $this->categoryService->getCategoryAttributes($categoryId),
                'listing_types' => $categoryInfo['settings']['listing_types'] ?? [],
            ];

            // Buscar templates disponíveis
            $result['templates'] = $this->templateManager->getTemplatesByCategory($categoryId);

            // Analisar concorrência
            $result['market_insights'] = $this->analyzeMarketInsights($categoryId, $productName);
        }

        return $result;
    }

    /**
     * Valida dados do anúncio em tempo real
     */
    public function validateStep(array $data, string $step): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];

        switch ($step) {
            case 'basic_info':
                $validation = $this->validateBasicInfo($data);
                break;

            case 'title':
                $validation = $this->validateTitle($data);
                break;

            case 'description':
                $validation = $this->validateDescription($data);
                break;

            case 'attributes':
                $validation = $this->validateAttributes($data);
                break;

            case 'images':
                $validation = $this->validateImages($data);
                break;

            case 'pricing':
                $validation = $this->validatePricing($data);
                break;

            case 'shipping':
                $validation = $this->validateShipping($data);
                break;

            default:
                return ['success' => false, 'error' => 'Step inválido'];
        }

        return [
            'success' => empty($validation['errors']),
            'step' => $step,
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'suggestions' => $validation['suggestions'],
            'score' => $validation['score'] ?? 0,
        ];
    }

    /**
     * Constrói anúncio completo com todos os dados
     */
    public function buildListing(array $data): array
    {
        try {
            // 1. Validar todos os dados
            $fullValidation = $this->validator->validateListing($data);
            
            if (!$fullValidation['valid']) {
                return [
                    'success' => false,
                    'step' => 'validation_failed',
                    'errors' => $fullValidation['errors'],
                    'can_fix' => $fullValidation['can_autofix'],
                ];
            }

            // 2. Analisar título via SEO (otimização básica)
            if (!empty($data['title'])) {
                $titleAnalysis = $this->seoAnalyzer->analyzeItemData([
                    'title' => $data['title'],
                    'category_id' => $data['category_id'] ?? '',
                ]);
                $titleScore = $titleAnalysis['scores']['title']['score'] ?? 100;
                if ($titleScore < 70 && !empty($titleAnalysis['scores']['title']['suggestions'])) {
                    // Registrar sugestões mas manter o título original
                    $data['_title_suggestions'] = $titleAnalysis['scores']['title']['suggestions'];
                }
            }

            // 3. Otimizar shipping (se dimensões fornecidas)
            if (!empty($data['dimensions']) && !empty($data['weight'])) {
                $shippingOpt = $this->optimizeShippingForListing($data);
                $data['shipping'] = array_merge($data['shipping'] ?? [], $shippingOpt);
            }

            // 4. Aplicar template de descrição (se selecionado)
            if (!empty($data['template_id']) && empty($data['description'])) {
                $data['description'] = $this->templateManager->renderTemplate(
                    $data['template_id'],
                    $data
                );
            }

            // 5. Estruturar payload final para ML API
            $listing = $this->structureListingPayload($data);

            // 6. Calcular score de qualidade previsto
            $predictedScore = $this->predictQualityScore($listing);

            // 7. Gerar preview
            $preview = $this->generatePreview($listing);

            return [
                'success' => true,
                'step' => 'ready_to_publish',
                'listing' => $listing,
                'predicted_score' => $predictedScore,
                'preview' => $preview,
                'recommendations' => $this->getFinalRecommendations($listing, $predictedScore),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Publica anúncio no Mercado Livre
     */
    public function publishListing(array $listingData, array $options = []): array
    {
        try {
            // Validação final
            $validation = $this->validator->validateListing($listingData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validação final falhou',
                    'details' => $validation['errors'],
                ];
            }

            // Publicar via API
            $response = $this->client->post('/items', $listingData);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao publicar',
                    'details' => $response,
                ];
            }

            // Verificar qualidade pós-publicação
            $itemId = $response['id'];
            $postQuality = $this->qualityScore->calculateQualityScore($itemId);

            return [
                'success' => true,
                'item_id' => $itemId,
                'permalink' => $response['permalink'] ?? '',
                'status' => $response['status'] ?? '',
                'quality_score' => $postQuality['score'] ?? 0,
                'message' => 'Anúncio publicado com sucesso!',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Salva rascunho do anúncio
     */
    public function saveDraft(array $data, string $draftName = ''): array
    {
        try {
            $now = date('Y-m-d H:i:s');
            $title = $data['title'] ?? $draftName ?: 'Rascunho sem título';

            $stmt = $this->db->prepare("
                INSERT INTO listing_drafts 
                (account_id, title, description, bullet_points, seo_keywords, suggested_price, category_id, status, created_at, updated_at)
                VALUES (:account_id, :title, :description, :bullets, :keywords, :price, :category_id, 'draft', :created_at, :updated_at)
            ");
            $stmt->execute([
                'account_id'  => $this->accountId,
                'title'       => $title,
                'description' => $data['description'] ?? '',
                'bullets'     => json_encode($data['bullet_points'] ?? []),
                'keywords'    => json_encode($data['seo_keywords'] ?? []),
                'price'       => $data['price'] ?? 0,
                'category_id' => $data['category_id'] ?? '',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $draftId = (int) $this->db->lastInsertId();

            return [
                'success'  => true,
                'draft_id' => $draftId,
                'message'  => 'Rascunho salvo com sucesso',
                'saved_at' => $now,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Erro ao salvar rascunho: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Carrega rascunho salvo
     */
    public function loadDraft(string $draftId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM listing_drafts WHERE id = :id AND (account_id = :account_id OR account_id IS NULL)
        ");
        $stmt->execute(['id' => $draftId, 'account_id' => $this->accountId]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draft) {
            return [
                'success' => false,
                'error'   => 'Rascunho não encontrado',
            ];
        }

        // Decode JSON fields
        $draft['bullet_points'] = json_decode($draft['bullet_points'] ?? '[]', true) ?: [];
        $draft['seo_keywords']  = json_decode($draft['seo_keywords'] ?? '[]', true) ?: [];

        return [
            'success' => true,
            'draft'   => $draft,
        ];
    }

    /**
     * Clona anúncio existente com melhorias
     */
    public function cloneListing(string $itemId, array $improvements = []): array
    {
        try {
            $item = $this->client->get("/items/{$itemId}");

            if (isset($item['error'])) {
                return [
                    'success' => false,
                    'error' => 'Item não encontrado',
                ];
            }

            // Extrair dados relevantes
            $clonedData = [
                'title' => $item['title'],
                'category_id' => $item['category_id'],
                'price' => $item['price'],
                'currency_id' => $item['currency_id'],
                'available_quantity' => $item['available_quantity'] ?? 1,
                'buying_mode' => $item['buying_mode'],
                'listing_type_id' => $item['listing_type_id'],
                'condition' => $item['condition'],
                'description' => '', // Buscar separadamente
                'pictures' => $item['pictures'] ?? [],
                'attributes' => $item['attributes'] ?? [],
                'shipping' => $item['shipping'] ?? [],
            ];

            // Aplicar melhorias sugeridas
            if (!empty($improvements)) {
                $clonedData = $this->applyImprovements($clonedData, $improvements);
            } else {
                // Sugerir melhorias automaticamente
                $clonedData = $this->suggestAutomaticImprovements($clonedData);
            }

            return [
                'success' => true,
                'cloned_data' => $clonedData,
                'improvements_applied' => $improvements,
                'message' => 'Anúncio clonado com sucesso',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ===================================
    // MÉTODOS DE VALIDAÇÃO POR STEP
    // ===================================

    private function validateBasicInfo(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        // Validar categoria
        if (empty($data['category_id'])) {
            $errors[] = 'Categoria é obrigatória';
            $score -= 30;
        }

        // Validar tipo de anúncio
        if (empty($data['listing_type_id'])) {
            $errors[] = 'Tipo de anúncio é obrigatório';
            $score -= 20;
        }

        // Validar condição
        if (empty($data['condition'])) {
            $errors[] = 'Condição do produto é obrigatória';
            $score -= 15;
        }

        // Sugestões
        if (!empty($data['category_id'])) {
            $insights = $this->analyzeMarketInsights($data['category_id']);
            if ($insights['average_price'] > 0) {
                $suggestions[] = "Preço médio na categoria: R$ {$insights['average_price']}";
            }
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    private function validateTitle(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        $title = $data['title'] ?? '';

        if (empty($title)) {
            $errors[] = 'Título é obrigatório';
            return ['errors' => $errors, 'warnings' => [], 'suggestions' => [], 'score' => 0];
        }

        // Validar comprimento
        $length = mb_strlen($title);
        if ($length < 20) {
            $errors[] = 'Título muito curto (mínimo 20 caracteres)';
            $score -= 30;
        } else if ($length > 60) {
            $errors[] = 'Título muito longo (máximo 60 caracteres)';
            $score -= 20;
        } else if ($length < 45) {
            $warnings[] = 'Título poderia ser mais descritivo (ideal 45-58 caracteres)';
            $score -= 10;
        }

        // Análise SEO
        if (!empty($data['category_id'])) {
            $seoAnalysis = $this->seoAnalyzer->analyzeItemData([
                'title' => $title,
                'category_id' => $data['category_id'],
            ]);
            $titleSeoScore = $seoAnalysis['scores']['title']['score'] ?? 100;
            
            if ($titleSeoScore < 70) {
                $warnings[] = "Score SEO baixo: {$titleSeoScore}/100";
                $score -= 15;
            }

            if (!empty($seoAnalysis['scores']['title']['suggestions'])) {
                $suggestions = array_merge($suggestions, $seoAnalysis['scores']['title']['suggestions']);
            }
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    private function validateDescription(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        $description = $data['description'] ?? '';

        if (empty($description)) {
            $warnings[] = 'Descrição não fornecida (recomendado para melhor conversão)';
            $score -= 20;
            
            // Sugerir templates
            if (!empty($data['category_id'])) {
                $templates = $this->templateManager->getTemplatesByCategory($data['category_id']);
                if (!empty($templates)) {
                    $suggestions[] = 'Use um template pronto para criar descrição profissional';
                }
            }
        } else {
            $length = mb_strlen($description);
            
            if ($length < 500) {
                $warnings[] = 'Descrição curta (recomendado mínimo 500 caracteres)';
                $score -= 10;
            }

            // Verificar estrutura
            if (strpos($description, '<ul>') === false && strpos($description, '<li>') === false) {
                $suggestions[] = 'Adicione listas com bullets para melhor legibilidade';
            }

            if (strpos($description, '<h') === false) {
                $suggestions[] = 'Use títulos (H2/H3) para organizar o conteúdo';
            }
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    private function validateAttributes(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        $attributes = $data['attributes'] ?? [];
        $categoryId = $data['category_id'] ?? null;

        if (!$categoryId) {
            return ['errors' => ['Categoria necessária para validar atributos'], 'warnings' => [], 'suggestions' => [], 'score' => 0];
        }

        // Buscar atributos obrigatórios
        $categoryAttrs = $this->categoryService->getCategoryAttributes($categoryId);
        $requiredAttrs = array_filter($categoryAttrs, fn($a) => ($a['tags']['required'] ?? false));

        $providedAttrIds = array_column($attributes, 'id');

        foreach ($requiredAttrs as $reqAttr) {
            if (!in_array($reqAttr['id'], $providedAttrIds)) {
                $errors[] = "Atributo obrigatório faltando: {$reqAttr['name']}";
                $score -= 15;
            }
        }

        // Verificar BRAND
        if (!in_array('BRAND', $providedAttrIds)) {
            $warnings[] = 'Marca (BRAND) não informada (recomendado para SEO)';
            $score -= 8;
        }

        // Verificar GTIN
        if (!in_array('GTIN', $providedAttrIds)) {
            $suggestions[] = 'Adicione GTIN (EAN/UPC) se disponível';
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    private function validateImages(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        $pictures = $data['pictures'] ?? [];

        if (empty($pictures)) {
            $errors[] = 'Pelo menos 1 imagem é obrigatória';
            return ['errors' => $errors, 'warnings' => [], 'suggestions' => [], 'score' => 0];
        }

        $count = count($pictures);

        if ($count < 6) {
            $warnings[] = "Use pelo menos 6 imagens (atual: {$count})";
            $score -= (6 - $count) * 5;
        }

        if ($count < 10) {
            $suggestions[] = 'Anúncios com 10+ imagens têm melhor conversão';
        }

        // Validar URLs das imagens são acessíveis
        foreach (array_slice($pictures, 0, 3) as $pic) {
            $url = is_array($pic) ? ($pic['source'] ?? '') : $pic;
            if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $warnings[] = 'URL de imagem inválida detectada';
                $score -= 5;
                break;
            }
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    private function validatePricing(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        $price = $data['price'] ?? 0;

        if ($price <= 0) {
            $errors[] = 'Preço deve ser maior que zero';
            return ['errors' => $errors, 'warnings' => [], 'suggestions' => [], 'score' => 0];
        }

        // Comparar com mercado
        if (!empty($data['category_id'])) {
            $insights = $this->analyzeMarketInsights($data['category_id']);
            $avgPrice = $insights['average_price'] ?? 0;

            if ($avgPrice > 0) {
                $diff = (($price - $avgPrice) / $avgPrice) * 100;

                if ($diff > 50) {
                    $warnings[] = sprintf('Preço %.1f%% acima da média (R$ %.2f)', $diff, $avgPrice);
                    $score -= 10;
                } else if ($diff < -30) {
                    $warnings[] = sprintf('Preço %.1f%% abaixo da média (R$ %.2f)', abs($diff), $avgPrice);
                    $suggestions[] = 'Preço muito baixo pode gerar desconfiança';
                }
            }
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    private function validateShipping(array $data): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        $score = 100;

        $shipping = $data['shipping'] ?? [];
        $dimensions = $data['dimensions'] ?? [];
        $weight = $data['weight'] ?? 0;

        // Validar modo de envio
        $mode = $shipping['mode'] ?? '';
        if (empty($mode) || $mode === 'not_specified') {
            $errors[] = 'Modo de envio deve ser especificado';
            $score -= 25;
        }

        // Validar dimensões
        if (empty($dimensions) || $weight <= 0) {
            $warnings[] = 'Dimensões e peso não fornecidos';
            $score -= 15;
        } else {
            // Validar com DimensionCalculator
            $dimValidation = $this->dimensionCalculator->validateForAllModes(
                $dimensions['length'] ?? 0,
                $dimensions['width'] ?? 0,
                $dimensions['height'] ?? 0,
                $weight
            );

            if (empty($dimValidation['compatible_modes'])) {
                $errors[] = 'Dimensões/peso excedem limites de todas as modalidades';
                $score -= 40;
            } else {
                $suggestions[] = "Modalidades compatíveis: " . implode(', ', $dimValidation['compatible_modes']);
                
                // Recomendar melhor opção
                if ($mode !== $dimValidation['recommended_mode']) {
                    $suggestions[] = "Considere usar: {$dimValidation['recommended_mode']}";
                }
            }
        }

        // Verificar frete grátis
        if (!($shipping['free_shipping'] ?? false)) {
            $warnings[] = 'Frete grátis aumenta conversão em até 40%';
            $score -= 12;
        }

        return compact('errors', 'warnings', 'suggestions', 'score');
    }

    // ===================================
    // MÉTODOS AUXILIARES
    // ===================================

    private function getWizardSteps(): array
    {
        return [
            ['step' => 'basic_info', 'label' => 'Informações Básicas', 'order' => 1],
            ['step' => 'title', 'label' => 'Título', 'order' => 2],
            ['step' => 'description', 'label' => 'Descrição', 'order' => 3],
            ['step' => 'attributes', 'label' => 'Atributos', 'order' => 4],
            ['step' => 'images', 'label' => 'Imagens', 'order' => 5],
            ['step' => 'pricing', 'label' => 'Preço', 'order' => 6],
            ['step' => 'shipping', 'label' => 'Envio', 'order' => 7],
            ['step' => 'review', 'label' => 'Revisar', 'order' => 8],
        ];
    }

    private function getRequiredFields(?string $categoryId): array
    {
        $fields = [
            'title' => true,
            'category_id' => true,
            'price' => true,
            'currency_id' => true,
            'available_quantity' => true,
            'buying_mode' => true,
            'listing_type_id' => true,
            'condition' => true,
            'pictures' => true,
        ];

        if ($categoryId) {
            try {
                $attrs = $this->categoryService->getCategoryAttributes($categoryId);
                foreach ($attrs as $attr) {
                    if (!empty($attr['tags']['required']) || !empty($attr['required'])) {
                        $fields[$attr['id']] = true;
                    }
                }
            } catch (\Exception $e) {
                // Falha silenciosa — campos base continuam válidos
            }
        }

        return $fields;
    }

    private function analyzeMarketInsights(string $categoryId, string $query = ''): array
    {
        try {
            $search = $this->client->get('/sites/MLB/search', [
                'category' => $categoryId,
                'limit' => 50,
                'q' => $query,
            ]);

            if (empty($search['results'])) {
                return ['available' => false];
            }

            $prices = array_column($search['results'], 'price');
            $avgPrice = !empty($prices) ? array_sum($prices) / count($prices) : 0;

            return [
                'available' => true,
                'total_results' => $search['paging']['total'] ?? 0,
                'average_price' => round($avgPrice, 2),
                'min_price' => !empty($prices) ? min($prices) : 0,
                'max_price' => !empty($prices) ? max($prices) : 0,
            ];

        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    private function optimizeShippingForListing(array $data): array
    {
        $dimensions = $data['dimensions'] ?? [];
        $weight = $data['weight'] ?? 0;

        if (empty($dimensions) || $weight <= 0) {
            return [];
        }

        // Usar ShippingSimulatorService para sugerir melhor configuração
        $simulator = new ShippingSimulatorService($this->accountId);
        
        // Simular custos
        $simulation = $simulator->simulateShipping([
            'dimensions' => $dimensions,
            'weight' => $weight,
        ]);

        if (!$simulation['success']) {
            return [];
        }

        $best = $simulation['recommendation']['best'] ?? 'me2';

        return [
            'mode' => $best,
            'free_shipping' => true,
            'dimensions' => $dimensions,
        ];
    }

    private function structureListingPayload(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'category_id' => $data['category_id'] ?? '',
            'price' => $data['price'] ?? 0,
            'currency_id' => $data['currency_id'] ?? 'BRL',
            'available_quantity' => $data['available_quantity'] ?? 1,
            'buying_mode' => $data['buying_mode'] ?? 'buy_it_now',
            'listing_type_id' => $data['listing_type_id'] ?? 'gold_special',
            'condition' => $data['condition'] ?? 'new',
            'description' => ['plain_text' => $data['description'] ?? ''],
            'pictures' => $data['pictures'] ?? [],
            'attributes' => $data['attributes'] ?? [],
            'shipping' => $data['shipping'] ?? [],
        ];
    }

    private function predictQualityScore(array $listing): array
    {
        $score = 0;
        $breakdown = [];

        // Título (0–20)
        $titleLen = mb_strlen($listing['title'] ?? '');
        if ($titleLen >= 45 && $titleLen <= 58) {
            $score += 20;
            $breakdown['title'] = 20;
        } elseif ($titleLen >= 30 && $titleLen <= 60) {
            $score += 12;
            $breakdown['title'] = 12;
        } elseif ($titleLen > 0) {
            $score += 5;
            $breakdown['title'] = 5;
        }

        // Descrição (0–15)
        $descText = $listing['description']['plain_text'] ?? '';
        $descLen = mb_strlen($descText);
        if ($descLen >= 500) {
            $score += 15;
            $breakdown['description'] = 15;
        } elseif ($descLen >= 200) {
            $score += 8;
            $breakdown['description'] = 8;
        } elseif ($descLen > 0) {
            $score += 4;
            $breakdown['description'] = 4;
        }

        // Imagens (0–20)
        $imageCount = count($listing['pictures'] ?? []);
        $imgScore = min(20, (int)($imageCount * 3.3));
        $score += $imgScore;
        $breakdown['images'] = $imgScore;

        // Atributos (0–20) — verificar obrigatórios e extras
        $attrCount = count($listing['attributes'] ?? []);
        $filledCount = 0;
        $hasBrand = false;
        $hasGtin = false;
        foreach ($listing['attributes'] ?? [] as $attr) {
            if (!empty($attr['value_name'] ?? $attr['value_id'] ?? null)) {
                $filledCount++;
                if (($attr['id'] ?? '') === 'BRAND') {
                    $hasBrand = true;
                }
                if (($attr['id'] ?? '') === 'GTIN') {
                    $hasGtin = true;
                }
            }
        }
        $attrScore = min(14, $filledCount * 2);
        if ($hasBrand) {
            $attrScore += 3;
        }
        if ($hasGtin) {
            $attrScore += 3;
        }
        $attrScore = min(20, $attrScore);
        $score += $attrScore;
        $breakdown['attributes'] = $attrScore;

        // Shipping (0–15)
        $shipScore = 0;
        if (!empty($listing['shipping']['free_shipping'])) {
            $shipScore += 8;
        }
        if (($listing['shipping']['logistic_type'] ?? '') === 'fulfillment'
            || ($listing['shipping']['mode'] ?? '') === 'me2') {
            $shipScore += 7;
        }
        $score += $shipScore;
        $breakdown['shipping'] = $shipScore;

        // Preço competitivo (0–10) — buscar na categoria se possível
        $priceScore = $this->evaluatePriceCompetitiveness($listing);
        $score += $priceScore;
        $breakdown['price'] = $priceScore;

        $finalScore = min(100, $score);

        return [
            'score' => $finalScore,
            'rating' => $this->getScoreRating($finalScore),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Avalia competitividade de preço comparando com a categoria via ML API
     */
    private function evaluatePriceCompetitiveness(array $listing): int
    {
        $price = floatval($listing['price'] ?? 0);
        $categoryId = $listing['category_id'] ?? '';
        if ($price <= 0 || $categoryId === '') {
            return 5; // Score neutro
        }

        try {
            $searchResults = $this->client->searchItems([
                'category' => $categoryId,
                'sort' => 'relevance',
                'limit' => 20,
            ]);

            $prices = [];
            foreach ($searchResults['results'] ?? [] as $item) {
                $p = floatval($item['price'] ?? 0);
                if ($p > 0) {
                    $prices[] = $p;
                }
            }

            if (empty($prices)) {
                return 5;
            }

            $avg = array_sum($prices) / count($prices);
            $ratio = $price / $avg;

            // Preço competitivo (80%-120% da média) = score alto
            if ($ratio >= 0.8 && $ratio <= 1.2) {
                return 10;
            }
            if ($ratio >= 0.6 && $ratio <= 1.5) {
                return 7;
            }
            return 3;
        } catch (\Exception $e) {
            return 5;
        }
    }

    private function getScoreRating(int $score): string
    {
        if ($score >= 90) return 'Excelente';
        if ($score >= 75) return 'Muito Bom';
        if ($score >= 60) return 'Bom';
        if ($score >= 45) return 'Regular';
        return 'Ruim';
    }

    private function generatePreview(array $listing): array
    {
        return [
            'title' => $listing['title'] ?? '',
            'price_formatted' => 'R$ ' . number_format($listing['price'] ?? 0, 2, ',', '.'),
            'condition_label' => $listing['condition'] === 'new' ? 'Novo' : 'Usado',
            'image_count' => count($listing['pictures'] ?? []),
            'has_free_shipping' => $listing['shipping']['free_shipping'] ?? false,
        ];
    }

    private function getFinalRecommendations(array $listing, array $predictedScore): array
    {
        $recommendations = [];

        if ($predictedScore['score'] < 80) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Score previsto abaixo de 80 - considere melhorar antes de publicar',
            ];
        }

        if (empty($listing['description']['plain_text'])) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => 'Adicione uma descrição detalhada para melhorar conversão',
            ];
        }

        if (count($listing['pictures'] ?? []) < 6) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => 'Adicione mais imagens (recomendado: 6-10)',
            ];
        }

        return $recommendations;
    }

    private function applyImprovements(array $data, array $improvements): array
    {
        // Aplicar melhorias específicas solicitadas
        foreach ($improvements as $key => $value) {
            if (isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function suggestAutomaticImprovements(array $data): array
    {
        // Sugerir melhorias automáticas
        
        // Analisar título via SEO
        if (!empty($data['title']) && !empty($data['category_id'])) {
            $titleAnalysis = $this->seoAnalyzer->analyzeItemData([
                'title' => $data['title'],
                'category_id' => $data['category_id'],
            ]);
            if (!empty($titleAnalysis['scores']['title']['suggestions'])) {
                $data['_title_suggestions'] = $titleAnalysis['scores']['title']['suggestions'];
            }
        }

        return $data;
    }
}
