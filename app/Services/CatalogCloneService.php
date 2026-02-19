<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\PricingStrategyService;
use Exception;

class CatalogCloneService
{
    private \PDO $db;
    private ?CloneTemplateService $templateService = null;
    private ?CloneMetricsService $metricsService = null;
    private ?ClonePostActionsService $postActionsService = null;
    private ?CloneMonitoringService $monitoringService = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lazy load template service
     */
    private function getTemplateService(): CloneTemplateService
    {
        if ($this->templateService === null) {
            $this->templateService = new CloneTemplateService();
        }
        return $this->templateService;
    }

    /**
     * Lazy load metrics service
     */
    private function getMetricsService(): CloneMetricsService
    {
        if ($this->metricsService === null) {
            $this->metricsService = new CloneMetricsService();
        }
        return $this->metricsService;
    }

    /**
     * Lazy load post actions service
     */
    private function getPostActionsService(): ClonePostActionsService
    {
        if ($this->postActionsService === null) {
            $this->postActionsService = new ClonePostActionsService();
        }
        return $this->postActionsService;
    }

    /**
     * Lazy load monitoring service
     */
    private function getMonitoringService(): CloneMonitoringService
    {
        if ($this->monitoringService === null) {
            $this->monitoringService = new CloneMonitoringService();
        }
        return $this->monitoringService;
    }

    /**
     * Obtém um cliente ML autenticado para buscas por seller.
     * A API do ML agora requer autenticação para endpoints que antes eram públicos.
     * Usa a primeira conta ativa disponível.
     *
     * @return MercadoLivreClient Cliente autenticado ou sem autenticação como fallback
     */
    private function getAuthenticatedClientForSearch(): MercadoLivreClient
    {
        try {
            $stmt = $this->db->query(
                "SELECT id FROM ml_accounts WHERE status = 'active' ORDER BY id ASC LIMIT 1"
            );
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row && !empty($row['id'])) {
                return new MercadoLivreClient((int)$row['id']);
            }
        } catch (\Throwable $e) {
            log_error('Erro ao buscar conta para autenticação no CatalogClone', [
                'error' => $e->getMessage(),
            ]);
        }
        
        // Fallback: tenta sem autenticação (pode falhar dependendo das políticas do ML)
        return new MercadoLivreClient();
    }

    /**
     * Obtém o seller_id de uma conta própria vinculada
     * Retorna o primeiro encontrado ou null se nenhum
     *
     * @return string|null
     */
    private function getOwnSellerIdFromAccounts(): ?string
    {
        try {
            $stmt = $this->db->query(
                "SELECT ml_user_id FROM ml_accounts WHERE status = 'active' ORDER BY id ASC"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            return !empty($rows) ? (string)$rows[0] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lista todos os seller_ids de contas próprias vinculadas
     *
     * @return array Lista de seller_ids
     */
    private function getAllOwnSellerIds(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT ml_user_id FROM ml_accounts WHERE status = 'active'"
            );
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Lista itens de uma conta própria vinculada usando /users/{id}/items/search
     *
     * @param string $sellerId ML Seller ID da conta própria
     * @param array $filters Filtros opcionais
     * @return array Lista paginada com items, total, facets
     */
    private function listOwnAccountItems(string $sellerId, array $filters = []): array
    {
        // Encontrar a conta pelo seller_id
        $stmt = $this->db->prepare(
            "SELECT id FROM ml_accounts WHERE ml_user_id = :seller_id AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['seller_id' => $sellerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            throw new Exception('Conta não encontrada para o seller ID: ' . $sellerId);
        }
        
        $client = new MercadoLivreClient((int)$row['id']);
        
        $limit = min((int)($filters['limit'] ?? 50), 50);
        $offset = (int)($filters['offset'] ?? 0);
        
        // Buscar itens usando endpoint da conta própria
        $params = [
            'limit' => $limit,
            'offset' => $offset,
        ];
        
        if (!empty($filters['category'])) {
            $params['category'] = $filters['category'];
        }
        
        if (!empty($filters['keyword'])) {
            $params['q'] = $filters['keyword'];
        }
        
        $searchResults = $client->get("/users/{$sellerId}/items/search", $params);
        
        if (isset($searchResults['error'])) {
            throw new Exception('Erro ao buscar itens da conta: ' . ($searchResults['message'] ?? 'Unknown'));
        }
        
        $itemIds = $searchResults['results'] ?? [];
        $total = $searchResults['paging']['total'] ?? count($itemIds);
        
        // Buscar detalhes dos itens
        $processedItems = [];
        $brandCounts = [];
        $categoryFacets = [];
        $catalogCount = 0;
        $nonCatalogCount = 0;
        
        if (!empty($itemIds)) {
            // Buscar detalhes em lotes de 20
            foreach (array_chunk($itemIds, 20) as $chunk) {
                $idsParam = implode(',', $chunk);
                $itemsDetails = $client->get("/items", ['ids' => $idsParam]);
                
                if (!is_array($itemsDetails)) continue;
                
                foreach ($itemsDetails as $itemWrapper) {
                    $item = $itemWrapper['body'] ?? $itemWrapper;
                    if (empty($item['id'])) continue;
                    
                    $isCatalog = !empty($item['catalog_product_id']);
                    $brand = $this->extractBrandFromItem($item);
                    $categoryId = $item['category_id'] ?? '';
                    
                    if ($isCatalog) {
                        $catalogCount++;
                    } else {
                        $nonCatalogCount++;
                    }
                    
                    if ($brand) {
                        $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                    }
                    
                    if ($categoryId) {
                        if (!isset($categoryFacets[$categoryId])) {
                            $categoryFacets[$categoryId] = ['name' => $categoryId, 'count' => 0];
                        }
                        $categoryFacets[$categoryId]['count']++;
                    }
                    
                    // Aplicar filtros locais
                    if (isset($filters['is_catalog'])) {
                        $filterCatalog = filter_var($filters['is_catalog'], FILTER_VALIDATE_BOOLEAN);
                        if ($filterCatalog !== $isCatalog) {
                            continue;
                        }
                    }
                    
                    if (!empty($filters['brand']) && strcasecmp($brand, $filters['brand']) !== 0) {
                        continue;
                    }
                    
                    $processedItems[] = [
                        'id' => $item['id'],
                        'title' => $item['title'] ?? '',
                        'price' => $item['price'] ?? 0,
                        'currency_id' => $item['currency_id'] ?? 'BRL',
                        'thumbnail' => $item['thumbnail'] ?? '',
                        'permalink' => $item['permalink'] ?? '',
                        'condition' => $item['condition'] ?? 'new',
                        'available_quantity' => $item['available_quantity'] ?? 0,
                        'sold_quantity' => $item['sold_quantity'] ?? 0,
                        'category_id' => $categoryId,
                        'is_catalog' => $isCatalog,
                        'catalog_product_id' => $item['catalog_product_id'] ?? null,
                        'brand' => $brand,
                        'listing_type_id' => $item['listing_type_id'] ?? '',
                        'shipping_free' => ($item['shipping']['free_shipping'] ?? false),
                    ];
                }
            }
        }
        
        arsort($brandCounts);
        
        return [
            'status' => 'success',
            'seller_id' => $sellerId,
            'items' => $processedItems,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'summary' => [
                'catalog' => $catalogCount,
                'non_catalog' => $nonCatalogCount,
                'total_in_page' => count($processedItems),
            ],
            'facets' => [
                'brands' => $brandCounts,
                'categories' => $categoryFacets,
            ],
        ];
    }

    /**
     * Obtém resumo/summary de uma conta própria vinculada
     *
     * @param string $sellerId ML Seller ID da conta própria
     * @return array Summary com contadores e facets
     */
    private function getOwnAccountSummary(string $sellerId): array
    {
        // Encontrar a conta pelo seller_id
        $stmt = $this->db->prepare(
            "SELECT id, nickname FROM ml_accounts WHERE ml_user_id = :seller_id AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['seller_id' => $sellerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            throw new Exception('Conta não encontrada para o seller ID: ' . $sellerId);
        }
        
        $client = new MercadoLivreClient((int)$row['id']);
        $nickname = $row['nickname'] ?? 'Seller ' . $sellerId;
        
        // Buscar total de itens
        $searchResults = $client->get("/users/{$sellerId}/items/search", ['limit' => 0]);
        
        if (isset($searchResults['error'])) {
            throw new Exception('Erro ao buscar summary da conta: ' . ($searchResults['message'] ?? 'Unknown'));
        }
        
        $total = $searchResults['paging']['total'] ?? 0;
        
        // Buscar amostra para classificar catálogo/não-catálogo e extrair marcas
        $sampleSize = min($total, 100);
        $catalogCount = 0;
        $nonCatalogCount = 0;
        $brandCounts = [];
        $categoryFacets = [];
        
        if ($sampleSize > 0) {
            $itemsResult = $client->get("/users/{$sellerId}/items/search", ['limit' => $sampleSize]);
            $itemIds = $itemsResult['results'] ?? [];
            
            if (!empty($itemIds)) {
                foreach (array_chunk($itemIds, 20) as $chunk) {
                    $idsParam = implode(',', $chunk);
                    $itemsDetails = $client->get("/items", ['ids' => $idsParam]);
                    
                    if (!is_array($itemsDetails)) continue;
                    
                    foreach ($itemsDetails as $itemWrapper) {
                        $item = $itemWrapper['body'] ?? $itemWrapper;
                        if (empty($item['id'])) continue;
                        
                        $isCatalog = !empty($item['catalog_product_id']);
                        $brand = $this->extractBrandFromItem($item);
                        $categoryId = $item['category_id'] ?? '';
                        
                        if ($isCatalog) {
                            $catalogCount++;
                        } else {
                            $nonCatalogCount++;
                        }
                        
                        if ($brand) {
                            $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                        }
                        
                        if ($categoryId) {
                            if (!isset($categoryFacets[$categoryId])) {
                                $categoryFacets[$categoryId] = ['name' => $categoryId, 'count' => 0];
                            }
                            $categoryFacets[$categoryId]['count']++;
                        }
                    }
                }
            }
        }
        
        arsort($brandCounts);
        
        if (!empty($categoryFacets)) {
            uasort($categoryFacets, static function (array $a, array $b): int {
                return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            });
        }
        
        $collected = $catalogCount + $nonCatalogCount;
        
        return [
            'status' => 'success',
            'seller_id' => $sellerId,
            'seller_nickname' => $nickname,
            'seller_reputation' => null,
            'total_items' => $total,
            'sample_size' => $collected,
            'catalog_count' => $catalogCount,
            'non_catalog_count' => $nonCatalogCount,
            'catalog_percentage' => $collected > 0 ? round(($catalogCount / $collected) * 100, 1) : 0,
            'brands' => array_slice($brandCounts, 0, 50, true),
            'categories' => array_slice($categoryFacets, 0, 50, true),
        ];
    }

    // =========================================================================
    // VALIDAÇÃO E UTILITÁRIOS
    // =========================================================================

    /**
     * Valida parâmetros obrigatórios para clonagem
     * 
     * @throws \InvalidArgumentException se parâmetros inválidos
     */
    private function validateCloneParams(array $params): void
    {
        $required = ['source_account_id', 'source_item_id', 'target_account_id'];
        
        foreach ($required as $field) {
            if (!isset($params[$field]) || $params[$field] === '' || $params[$field] === null) {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }

        // Validar formato do item ID (MLx seguido de números)
        if (!preg_match('/^ML[A-Z]\d+$/', $params['source_item_id'])) {
            throw new \InvalidArgumentException("Formato de source_item_id inválido: {$params['source_item_id']}");
        }

        // Validar que account IDs são numéricos
        if (!is_numeric($params['source_account_id']) || !is_numeric($params['target_account_id'])) {
            throw new \InvalidArgumentException("Account IDs devem ser numéricos");
        }
    }

    /**
     * Calcula o preço final baseado na estratégia
     * 
     * @param float $originalPrice Preço original
     * @param array $pricingStrategy Estratégia ['type' => string, 'value' => float]
     * @param int $targetAccountId Conta destino (para análise de mercado)
     * @param array $sourceItem Item fonte (para estratégias inteligentes)
     * @return array ['price' => float, 'strategy_applied' => string]
     */
    private function calculateFinalPrice(
        float $originalPrice, 
        array $pricingStrategy, 
        int $targetAccountId, 
        array $sourceItem
    ): array {
        $monitoring = $this->getMonitoringService();
        $strategyType = $pricingStrategy['type'] ?? 'copy';
        $price = $originalPrice;
        $strategyApplied = 'Cópia Exata';

        switch ($strategyType) {
            case 'copy':
                // Mantém preço original
                break;

            case 'markup_percent':
                $markup = (float)($pricingStrategy['value'] ?? 0);
                $price = $originalPrice * (1 + ($markup / 100));
                $strategyApplied = "Markup de {$markup}%";
                break;

            case 'markdown_percent':
                $markdown = (float)($pricingStrategy['value'] ?? 0);
                $price = $originalPrice * (1 - ($markdown / 100));
                $strategyApplied = "Markdown de {$markdown}%";
                break;

            case 'aggressive':
            case 'competitive':
            case 'premium':
            case 'value':
                try {
                    $pricingService = new PricingStrategyService($targetAccountId);
                    $analysis = $pricingService->analyzeCompetitorPrices(
                        $sourceItem['category_id'] ?? '',
                        null,
                        $sourceItem['title'] ?? ''
                    );
                    
                    $suggestion = $pricingService->suggestPrice($analysis, $strategyType);
                    
                    if (isset($suggestion['suggested_price'])) {
                        $price = $suggestion['suggested_price'];
                        $strategyApplied = "Estratégia " . ucfirst($strategyType);
                    } else {
                        $monitoring->logCloneEvent('pricing_fallback', [
                            'reason' => $suggestion['error'] ?? 'Unknown',
                            'strategy' => $strategyType,
                            'item_id' => $sourceItem['id'] ?? 'unknown'
                        ], 'WARNING');
                    }
                } catch (Exception $e) {
                    $monitoring->logCloneEvent('pricing_error', [
                        'error' => $e->getMessage(),
                        'strategy' => $strategyType
                    ], 'ERROR');
                }
                break;

            default:
                $monitoring->logCloneEvent('unknown_pricing_strategy', [
                    'strategy' => $strategyType
                ], 'WARNING');
        }

        return [
            'price' => round($price, 2),
            'strategy_applied' => $strategyApplied
        ];
    }

    public function cloneCatalogItem(array $params): array
    {
        $startTime = microtime(true);
        $monitoring = $this->getMonitoringService();
        
        // Verificar se módulo está habilitado
        $canClone = $monitoring->canClone();
        if (!$canClone['allowed']) {
            return [
                'status' => 'error',
                'message' => $canClone['reason'],
                'health' => $canClone['health'] ?? null
            ];
        }

        // Validar parâmetros de entrada
        try {
            $this->validateCloneParams($params);
        } catch (\InvalidArgumentException $e) {
            $monitoring->logCloneEvent('validation_error', ['error' => $e->getMessage()], 'ERROR');
            return [
                'status' => 'error',
                'message' => 'Parâmetros inválidos: ' . $e->getMessage()
            ];
        }
        
        // 1. Extrair parâmetros validados
        $sourceAccountId = (int)$params['source_account_id'];
        $sourceItemId = $params['source_item_id'];
        $targetAccountId = (int)$params['target_account_id'];
        $pricingStrategy = $params['pricing_strategy'] ?? ['type' => 'copy'];
        $stockStrategy = $params['stock_strategy'] ?? ['type' => 'copy'];
        $jobId = $params['job_id'] ?? null;
        
        // Log de início da operação
        $operationId = $monitoring->logCloneStart($sourceItemId, $sourceAccountId, $targetAccountId, [
            'pricing_strategy' => $pricingStrategy,
            'job_id' => $jobId,
            'is_batch' => isset($params['_batch'])
        ]);

        if ($sourceAccountId == $targetAccountId) {
            $monitoring->logCloneEnd($operationId, 'error', null, 'Mesma conta', microtime(true) - $startTime);
            return $this->logAndReturnError($sourceAccountId, $sourceItemId, $targetAccountId, null, null, 'Clonagem para a mesma conta não permitida no MVP.', 'error', $jobId, $pricingStrategy['type'] ?? 'copy');
        }

        // 2. Buscar item origem
        $sourceClient = new MercadoLivreClient($sourceAccountId);
        $sourceItem = $sourceClient->get("/items/{$sourceItemId}");

        if (isset($sourceItem['error'])) {
            $monitoring->logCloneEnd($operationId, 'error', null, 'Item não encontrado', microtime(true) - $startTime);
            $monitoring->logApiError("/items/{$sourceItemId}", 404, $sourceItem['message'] ?? 'Unknown error');
            return $this->logAndReturnError($sourceAccountId, $sourceItemId, $targetAccountId, null, null, 'Erro ao buscar item origem: ' . ($sourceItem['message'] ?? 'Unknown error'), 'error', $jobId, $pricingStrategy['type'] ?? 'copy');
        }
        
        $originalPrice = $sourceItem['price'];

        // 3. Validar se é catálogo (Removida restrição para permitir clonagem geral)
        $catalogProductId = $sourceItem['catalog_product_id'] ?? null;

        // 4. Verificar duplicidade na conta destino
        $targetClient = new MercadoLivreClient($targetAccountId);
        $sellerId = $targetClient->getSellerId();
        
        // Busca duplicidade via endpoint de itens do seller
        if ($catalogProductId) {
            // Usar /users/{seller_id}/items/search + multi-get para verificar duplicidade
            $sellerItems = $targetClient->get("/users/{$sellerId}/items/search", [
                'status' => 'active',
                'limit' => 50
            ]);
            
            $itemIds = $sellerItems['results'] ?? [];
            if (!empty($itemIds)) {
                // Verificar em lotes de 20
                $chunks = array_chunk($itemIds, 20);
                foreach ($chunks as $chunk) {
                    $itemsResponse = $targetClient->get('/items', ['ids' => implode(',', $chunk)]);
                    foreach ($itemsResponse as $itemData) {
                        $item = $itemData['body'] ?? $itemData;
                        if (($item['catalog_product_id'] ?? null) === $catalogProductId) {
                            return $this->logAndReturnError($sourceAccountId, $sourceItemId, $targetAccountId, null, $catalogProductId, 'Item duplicado detectado na conta destino.', 'skipped_duplicate', $jobId, $pricingStrategy['type'] ?? 'copy', $originalPrice);
                        }
                    }
                }
            }
        }

        // 5. Calcular preço usando método centralizado
        $priceResult = $this->calculateFinalPrice($originalPrice, $pricingStrategy, $targetAccountId, $sourceItem);
        $price = $priceResult['price'];
        $finalPrice = $price;
        $pricingStrategyType = $pricingStrategy['type'] ?? 'copy';

        // 6. Definir estoque
        $stock = $sourceItem['available_quantity'];
        if (($stockStrategy['type'] ?? '') === 'fixed' && isset($stockStrategy['value'])) {
            $stock = (int)$stockStrategy['value'];
        }

        // 7. Montar payload
        $payload = [
            'title' => $sourceItem['title'],
            'category_id' => $sourceItem['category_id'],
            'price' => $price,
            'currency_id' => $sourceItem['currency_id'],
            'available_quantity' => $stock,
            'buying_mode' => $sourceItem['buying_mode'],
            'listing_type_id' => $sourceItem['listing_type_id'],
            'condition' => $sourceItem['condition'],
            // 'catalog_product_id' => $catalogProductId, // Adicionado apenas se existir
            'pictures' => array_map(function($pic) { return ['source' => $pic['url']]; }, $sourceItem['pictures'] ?? []),
        ];

        if ($catalogProductId) {
             $payload['catalog_product_id'] = $catalogProductId;
        } else {
             // Lógica para itens não-catálogo
             
             // Atributos
             if (!empty($sourceItem['attributes'])) {
                 $payload['attributes'] = [];
                 foreach ($sourceItem['attributes'] as $attr) {
                     // Ignorar atributos somente leitura e internos do ML
                     if (in_array($attr['id'], ['ITEM_CONDITION', 'GTIN', 'BRAND', 'MODEL', 'LENGTH', 'WEIGHT', 'WIDTH', 'HEIGHT'])) continue; 
                     
                     if (isset($attr['value_id']) && $attr['value_id']) {
                         $payload['attributes'][] = ['id' => $attr['id'], 'value_id' => $attr['value_id']];
                     } elseif (isset($attr['value_name']) && $attr['value_name']) {
                         $payload['attributes'][] = ['id' => $attr['id'], 'value_name' => $attr['value_name']];
                     }
                 }
                 // Adicionar atributos obrigatórios BRAND/MODEL se disponíveis como texto simples se não copiados acima
             }

             // Variações
             if (!empty($sourceItem['variations'])) {
                 $payload['variations'] = [];
                 foreach ($sourceItem['variations'] as $variation) {
                     $varPayload = [
                         'price' => $payload['price'], // Usa o mesmo preço base
                         'attribute_combinations' => [],
                         'available_quantity' => $variation['available_quantity'],
                         'picture_ids' => $variation['picture_ids']
                     ];
                     foreach ($variation['attribute_combinations'] as $attrComb) {
                          if (isset($attrComb['value_id']) && $attrComb['value_id']) {
                              $varPayload['attribute_combinations'][] = ['id' => $attrComb['id'], 'value_id' => $attrComb['value_id']];
                          } else {
                              $varPayload['attribute_combinations'][] = ['id' => $attrComb['id'], 'value_name' => $attrComb['value_name']];
                          }
                     }
                     $payload['variations'][] = $varPayload;
                 }
                 unset($payload['available_quantity']); // Se tem variações, estoque é nelas
                  // Pictures devem ser processadas diferente para variações?
                  // No ML, as imagens principais ficam no 'pictures', e 'variations' referenciam 'picture_ids'.
                  // Ao criar, precisamos garantir que enviamos 'pictures' com 'source' URL, e as variations usam indices ou referências?
                  // Na criação (POST), variations usam 'picture_ids' que são URLs ou IDs?
                  // Para criação simples, costuma-se mandar attributes de variação e pictures associadas.
                  // Simplificação: Se item tem variações, a clonagem exata é complexa. Vamos copiar as variações com seus atributos.
                  // A gestão de imagens em variations no POST exige cuidado.
             }
             
             // Video, Warranty, etc.
             if (!empty($sourceItem['video_id'])) $payload['video_id'] = $sourceItem['video_id'];
             if (!empty($sourceItem['warranty'])) $payload['warranty'] = $sourceItem['warranty'];
             
             // Descrição (precisa de endpoint separado, aqui só preparamos clone)
        }
        
        // Tentar copiar shipping mode se existir
        if (isset($sourceItem['shipping']['mode'])) {
            $payload['shipping'] = ['mode' => $sourceItem['shipping']['mode']];
        }

        // 8. Postar item
        $newItem = $targetClient->post('/items', $payload);

        if (isset($newItem['error']) || !isset($newItem['id'])) {
             // Tentar capturar mensagem de erro detalhada
             $msg = $newItem['message'] ?? 'Erro desconhecido';
             if (isset($newItem['cause']) && is_array($newItem['cause'])) {
                 $causes = array_map(function($c) { return $c['message'] ?? ''; }, $newItem['cause']);
                 $msg .= ' Causes: ' . implode('; ', $causes);
             }
             $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);
             return $this->logAndReturnError($sourceAccountId, $sourceItemId, $targetAccountId, null, $catalogProductId, 'Erro ao criar item na API: ' . $msg, 'error', $jobId, $pricingStrategyType, $originalPrice, $finalPrice, $processingTimeMs);
        }

        $targetItemId = $newItem['id'];
        
        // Clonar descrição para itens não-catálogo
        if (!$catalogProductId) {
             try {
                $descriptionData = $sourceClient->get("/items/{$sourceItemId}/description");
                if (!isset($descriptionData['error']) && isset($descriptionData['plain_text'])) {
                     $descPayload = ['plain_text' => $descriptionData['plain_text']];
                     $targetClient->post("/items/{$targetItemId}/description", $descPayload);
                }
             } catch (Exception $eDesc) {
                 log_warning('Falha ao clonar descrição do item', [
                     'target_item_id' => $targetItemId,
                     'error' => $eDesc->getMessage(),
                 ]);
             }
        }

        // 10. Calcular tempo de processamento e logar sucesso
        $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);
        $clonedId = $this->logResult(
            $sourceAccountId, 
            $sourceItemId, 
            $targetAccountId, 
            $targetItemId, 
            $catalogProductId, 
            'created',
            'Item clonado com sucesso',
            $jobId,
            $pricingStrategyType ?? null,
            $originalPrice,
            $finalPrice ?? $originalPrice,
            $processingTimeMs
        );

        // 9. Post Actions
        // Agenda ações pós-clone usando ClonePostActionsService
        try {
             $postActionsService = $this->getPostActionsService();
             // Definição das ações padrão
             $actions = ['tech_sheet', 'seo_optimize', 'pricing_apply'];
             if (!$catalogProductId) {
                 // Para itens não catalogo, podemos adicionar mais ações se necessário
             }
             
             $postActionsService->scheduleActions(
                 $targetItemId, 
                 $actions,
                 $params['job_id'] ?? null, // Recebe job_id se vier do worker
                 $clonedId
             );
        } catch(Exception $eJob) {
             $monitoring->logCloneEvent('post_actions_error', [
                 'error' => $eJob->getMessage(),
                 'target_item_id' => $targetItemId,
                 'actions' => $actions ?? []
             ], 'ERROR');
        }

        // Log de sucesso final
        $monitoring->logCloneEnd($operationId, 'success', $targetItemId, null, microtime(true) - $startTime);

        return [
            'status' => 'success',
            'target_item_id' => $targetItemId,
            'message' => 'Item clonado com sucesso.'
        ];
    }

    private function logAndReturnError(
        $sourceAccountId, 
        $sourceItemId, 
        $targetAccountId, 
        $targetItemId, 
        $catalogProductId, 
        $message, 
        $status = 'error',
        ?int $jobId = null,
        ?string $pricingStrategyType = null,
        ?float $originalPrice = null,
        ?float $finalPrice = null,
        ?int $processingTimeMs = null
    ) {
        $this->logResult(
            $sourceAccountId, $sourceItemId, $targetAccountId, $targetItemId, 
            $catalogProductId, $status, $message, $jobId, $pricingStrategyType,
            $originalPrice, $finalPrice, $processingTimeMs
        );
        return [
            'status' => $status,
            'message' => $message
        ];
    }

    private function logResult(
        $sourceAccountId, 
        $sourceItemId, 
        $targetAccountId, 
        $targetItemId, 
        $catalogProductId, 
        $status, 
        ?string $errorMessage = null,
        ?int $jobId = null,
        ?string $pricingStrategy = null,
        ?float $originalPrice = null,
        ?float $finalPrice = null,
        ?int $processingTimeMs = null
    ): ?int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cloned_items (
                    job_id, source_account_id, source_item_id, target_account_id, target_item_id, 
                    catalog_product_id, status, error_message, pricing_strategy, 
                    original_price, final_price, processing_time_ms
                )
                VALUES (
                    :job_id, :source_account_id, :source_item_id, :target_account_id, :target_item_id, 
                    :catalog_product_id, :status, :error_message, :pricing_strategy,
                    :original_price, :final_price, :processing_time_ms
                )
            ");
            $stmt->execute([
                'job_id' => $jobId,
                'source_account_id' => $sourceAccountId,
                'source_item_id' => $sourceItemId,
                'target_account_id' => $targetAccountId,
                'target_item_id' => $targetItemId,
                'catalog_product_id' => $catalogProductId,
                'status' => $status,
                'error_message' => $errorMessage,
                'pricing_strategy' => $pricingStrategy,
                'original_price' => $originalPrice,
                'final_price' => $finalPrice,
                'processing_time_ms' => $processingTimeMs
            ]);
            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            log_error('Erro ao registrar log de clonagem', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Obtém histórico de clonagens
     */
    public function getCloneHistory(int $limit = 50): array
    {
        $limitSql = max(1, min(500, (int)$limit));
        $sql = "
            SELECT 
                ci.*,
                sa.nickname as source_account_name,
                sa.ml_user_id as source_user_id,
                ta.nickname as target_account_name,
                ta.ml_user_id as target_user_id
            FROM cloned_items ci
            LEFT JOIN ml_accounts sa ON ci.source_account_id = sa.id
            LEFT JOIN ml_accounts ta ON ci.target_account_id = ta.id
            ORDER BY ci.created_at DESC
            LIMIT {$limitSql}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCloneMetrics(): array
    {
        // Métricas do dia
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        // Clonagens de hoje
        $sql = "SELECT COUNT(*) as count FROM cloned_items WHERE created_at BETWEEN :start AND :end";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start' => $todayStart, 'end' => $todayEnd]);
        $todayClones = $stmt->fetchColumn();

        // Clonagens bem-sucedidas de hoje
        $sql = "SELECT COUNT(*) as count FROM cloned_items WHERE created_at BETWEEN :start AND :end AND status = 'success'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start' => $todayStart, 'end' => $todayEnd]);
        $todaySuccess = $stmt->fetchColumn();

        // Taxa de sucesso
        $successRate = $todayClones > 0 ? round(($todaySuccess / $todayClones) * 100, 1) : 0;

        // Total histórico
        $sql = "SELECT COUNT(*) as count FROM cloned_items";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $totalClones = $stmt->fetchColumn();

        // Média por hora (últimas 24h)
        $last24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $sql = "SELECT COUNT(*) as count FROM cloned_items WHERE created_at >= :time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['time' => $last24h]);
        $last24hCount = $stmt->fetchColumn();
        $avgPerHour = round($last24hCount / 24, 1);

        // Jobs pendentes (se existir tabela de jobs)
        try {
            $sql = "SELECT COUNT(*) as count FROM jobs WHERE status = 'pending' AND job_type = 'catalog_clone_item'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $pendingJobs = $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Tabela jobs pode não existir ainda
            log_warning('Falha ao consultar jobs pendentes de clone', ['error' => $e->getMessage()]);
            $pendingJobs = 0;
        }

        // Erros de hoje
        $sql = "SELECT COUNT(*) as count FROM cloned_items WHERE created_at BETWEEN :start AND :end AND status = 'error'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start' => $todayStart, 'end' => $todayEnd]);
        $errorCount = $stmt->fetchColumn();

        return [
            'today' => (int) $todayClones,
            'success_rate' => (float) $successRate,
            'total' => (int) $totalClones,
            'avg_per_hour' => (float) $avgPerHour,
            'pending' => (int) $pendingJobs,
            'errors' => (int) $errorCount
        ];
    }

    public function searchItemsWithFilters(string $sourceAccountId, array $filters): array
    {
        $client = new MercadoLivreClient((int)$sourceAccountId);
        
        // Build search query
        $query = $filters['keyword'] ?? '';
        $status = $filters['status'] ?? 'active';
        $categoryId = $filters['category_id'] ?? null;

        // Usar /users/{seller_id}/items/search
        $sellerId = $client->getSellerId();
        
        $searchParams = [
            'status' => $status,
            'limit' => 50,
            'offset' => 0,
        ];
        
        $searchResults = $client->get("/users/{$sellerId}/items/search", $searchParams);
        
        if (isset($searchResults['error'])) {
            throw new Exception('Erro na busca: ' . ($searchResults['message'] ?? 'Unknown error'));
        }
        
        $itemIds = $searchResults['results'] ?? [];
        $items = [];
        
        if (!empty($itemIds)) {
            // Buscar detalhes em lotes de 20
            $chunks = array_chunk($itemIds, 20);
            foreach ($chunks as $chunk) {
                $itemsResponse = $client->get('/items', ['ids' => implode(',', $chunk)]);
                foreach ($itemsResponse as $itemData) {
                    $item = $itemData['body'] ?? $itemData;
                    if (!empty($item['id'])) {
                        // Filtrar por categoria se especificado
                        if ($categoryId && ($item['category_id'] ?? '') !== $categoryId) {
                            continue;
                        }
                        // Filtrar por keyword se especificado
                        if ($query && stripos($item['title'] ?? '', $query) === false) {
                            continue;
                        }
                        $items[] = $item;
                    }
                }
            }
        }
        
        $searchResults['results'] = $items;
        
        $items = $searchResults['results'] ?? [];
        
        // Apply price filters
        if ($filters['min_price'] || $filters['max_price']) {
            $items = array_filter($items, function($item) use ($filters) {
                $price = $item['price'] ?? 0;
                
                if ($filters['min_price'] && $price < (float) $filters['min_price']) {
                    return false;
                }
                
                if ($filters['max_price'] && $price > (float) $filters['max_price']) {
                    return false;
                }
                
                return true;
            });
        }
        
        // Filter only catalog items
        $catalogItems = array_filter($items, function($item) {
            return !empty($item['catalog_product_id']);
        });
        
        return [
            'status' => 'success',
            'items' => array_values($catalogItems),
            'total' => count($catalogItems),
            'filters_applied' => array_filter($filters)
        ];
    }

    public function createCloneSchedule(array $data): array
    {
        $sourceAccountId = $data['source_account_id'];
        $targetAccountId = $data['target_account_id'];
        $scheduledDate = $data['scheduled_date'];
        $scheduledTime = $data['scheduled_time'];
        $frequency = $data['frequency'] ?? 'once';
        $filters = $data['filters'] ?? '';

        // Criar datetime
        $scheduledDateTime = $scheduledDate . ' ' . $scheduledTime . ':00';

        // Validar se a data não é no passado
        if (strtotime($scheduledDateTime) <= time()) {
            throw new Exception('Scheduled date/time cannot be in the past');
        }

        // Buscar informações das contas
        $sql = "SELECT nickname FROM ml_accounts WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute(['id' => $sourceAccountId]);
        $sourceAccount = $stmt->fetchColumn();
        
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetchColumn();

        if (!$sourceAccount || !$targetAccount) {
            throw new Exception('Invalid account ID(s)');
        }

        // Inserir agendamento
        $sql = "
            INSERT INTO clone_schedules (
                source_account_id, target_account_id, 
                source_account_name, target_account_name,
                scheduled_datetime, frequency, filters, 
                status, created_at
            ) VALUES (
                :source_account_id, :target_account_id,
                :source_account_name, :target_account_name,
                :scheduled_datetime, :frequency, :filters,
                'active', NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'source_account_id' => $sourceAccountId,
            'target_account_id' => $targetAccountId,
            'source_account_name' => $sourceAccount,
            'target_account_name' => $targetAccount,
            'scheduled_datetime' => $scheduledDateTime,
            'frequency' => $frequency,
            'filters' => $filters
        ]);

        $scheduleId = $this->db->lastInsertId();

        return [
            'status' => 'success',
            'schedule_id' => $scheduleId,
            'message' => 'Schedule created successfully'
        ];
    }

    public function getActiveSchedules(): array
    {
        $sql = "
            SELECT 
                id, source_account_name, target_account_name,
                scheduled_datetime, frequency, status,
                created_at
            FROM clone_schedules 
            WHERE status = 'active'
            ORDER BY scheduled_datetime ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $schedules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Format data for frontend
        return array_map(function($schedule) {
            return [
                'id' => $schedule['id'],
                'source_account' => $schedule['source_account_name'],
                'target_account' => $schedule['target_account_name'],
                'scheduled_datetime' => date('d/m/Y H:i', strtotime($schedule['scheduled_datetime'])),
                'frequency' => ucfirst($schedule['frequency']),
                'status' => $schedule['status']
            ];
        }, $schedules);
    }

    public function cancelSchedule(int $scheduleId): bool
    {
        $sql = "UPDATE clone_schedules SET status = 'canceled' WHERE id = :id AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $scheduleId]);
        
        return $stmt->rowCount() > 0;
    }

    public function processScheduledClones(): array
    {
        $now = date('Y-m-d H:i:00'); // Truncate seconds for matching
        
        // Buscar agendamentos para executar agora
        $sql = "
            SELECT * FROM clone_schedules 
            WHERE status = 'active' 
            AND scheduled_datetime <= :now
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['now' => $now]);
        $schedules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];

        foreach ($schedules as $schedule) {
            try {
                // Processar filtros se houver
                $filters = [];
                if (!empty($schedule['filters'])) {
                    // Parse query string format: "category=MLB1055&min_price=50"
                    parse_str($schedule['filters'], $parsedFilters);
                    
                    // Converter para formato esperado
                    $filters = [
                        'category_id' => $parsedFilters['category'] ?? null,
                        'min_price' => $parsedFilters['min_price'] ?? null,
                        'max_price' => $parsedFilters['max_price'] ?? null,
                        'keyword' => $parsedFilters['keyword'] ?? null,
                        'status' => $parsedFilters['status'] ?? null
                    ];
                }

                // Se há filtros, usar busca com filtros, senão processar todos os produtos ativos
                if (!empty($filters)) {
                    $searchResult = $this->searchItemsWithFilters($schedule['source_account_id'], $filters);
                    $items = array_column($searchResult['items'], 'id');
                } else {
                    // Buscar alguns produtos ativos da conta (limite para segurança)
                    $items = $this->getActiveItemsFromAccount($schedule['source_account_id'], 10);
                }

                // Criar jobs para cada item
                $jobService = new JobService();
                $jobsCreated = 0;

                foreach ($items as $itemId) {
                    $payload = [
                        'source_account_id' => $schedule['source_account_id'],
                        'source_item_id' => $itemId,
                        'target_account_id' => $schedule['target_account_id'],
                        'scheduled_clone_id' => $schedule['id']
                    ];

                    $jobService->dispatch('catalog_clone_item', $payload);
                    $jobsCreated++;
                }

                // Atualizar status do agendamento
                $this->updateScheduleAfterExecution($schedule['id'], $schedule['frequency']);

                $results[] = [
                    'schedule_id' => $schedule['id'],
                    'status' => 'success',
                    'jobs_created' => $jobsCreated
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'schedule_id' => $schedule['id'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    private function getActiveItemsFromAccount(string $accountId, int $limit = 10): array
    {
        $client = new MercadoLivreClient((int)$accountId);
        $sellerId = $client->getSellerId();
        
        // Usar /users/{seller_id}/items/search
        $searchResults = $client->get("/users/{$sellerId}/items/search", [
            'status' => 'active',
            'limit' => $limit,
            'offset' => 0
        ]);
        
        if (isset($searchResults['error'])) {
            return [];
        }

        $itemIds = $searchResults['results'] ?? [];
        
        if (empty($itemIds)) {
            return [];
        }
        
        // Buscar detalhes para verificar se são itens de catálogo
        $chunks = array_chunk($itemIds, 20);
        $catalogItems = [];
        
        foreach ($chunks as $chunk) {
            $itemsResponse = $client->get('/items', ['ids' => implode(',', $chunk)]);
            foreach ($itemsResponse as $itemData) {
                $item = $itemData['body'] ?? $itemData;
                if (!empty($item['id']) && !empty($item['catalog_product_id'])) {
                    $catalogItems[] = $item['id'];
                }
            }
        }

        return $catalogItems;
    }

    private function updateScheduleAfterExecution(int $scheduleId, string $frequency): void
    {
        if ($frequency === 'once') {
            // Agendamento único - marcar como concluído
            $sql = "UPDATE clone_schedules SET status = 'completed', executed_at = NOW() WHERE id = :id";
        } else {
            // Agendamento recorrente - calcular próxima execução
            $nextExecution = $this->calculateNextExecution($frequency);
            $sql = "
                UPDATE clone_schedules 
                SET scheduled_datetime = :next_execution, executed_at = NOW() 
                WHERE id = :id
            ";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['id' => $scheduleId];
        
        if ($frequency !== 'once') {
            $params['next_execution'] = $nextExecution;
        }
        
        $stmt->execute($params);
    }

    private function calculateNextExecution(string $frequency): string
    {
        $now = new \DateTime();
        
        switch ($frequency) {
            case 'daily':
                $now->modify('+1 day');
                break;
            case 'weekly':
                $now->modify('+1 week');
                break;
            case 'monthly':
                $now->modify('+1 month');
                break;
        }

        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Simula a clonagem para prever preço e verificar duplicidade
     * Suporta itens de catálogo E não-catálogo
     */
    public function simulateClone(array $params): array
    {
        $sourceAccountId = $params['source_account_id'] ?? null;
        $sourceItemId = $params['source_item_id'];
        $targetAccountId = $params['target_account_id'];
        $pricingStrategy = $params['pricing_strategy'] ?? ['type' => 'copy'];

        if ($sourceAccountId && $sourceAccountId == $targetAccountId) {
            return ['status' => 'error', 'message' => 'Conta origem e destino iguais.'];
        }

        // 1. Buscar item origem (pode ser de seller público ou conta própria)
        if ($sourceAccountId) {
            $sourceClient = new MercadoLivreClient($sourceAccountId);
        } else {
            $sourceClient = new MercadoLivreClient();
        }
        $sourceItem = $sourceClient->get("/items/{$sourceItemId}");

        if (isset($sourceItem['error'])) {
            return ['status' => 'error', 'message' => 'Erro ao buscar item origem: ' . ($sourceItem['message'] ?? '')];
        }

        // 2. Identificar tipo (catálogo ou não)
        $isCatalog = !empty($sourceItem['catalog_product_id']);
        $catalogProductId = $sourceItem['catalog_product_id'] ?? null;

        // 3. Verificar duplicidade
        $isDuplicate = false;
        $duplicateReason = null;

        if ($isCatalog && $catalogProductId) {
            // Verificar duplicidade por catalog_product_id
            $isDuplicate = $this->checkLocalDuplicate($catalogProductId, $targetAccountId);

            if (!$isDuplicate) {
                // Se não achou local, verifica na API usando /users/{seller_id}/items/search
                $targetClient = new MercadoLivreClient($targetAccountId);
                $sellerId = $targetClient->getSellerId();
                
                // Buscar todos os itens ativos do seller e verificar catalog_product_id
                $sellerItems = $targetClient->get("/users/{$sellerId}/items/search", [
                    'status' => 'active',
                    'limit' => 50
                ]);
                
                $itemIds = $sellerItems['results'] ?? [];
                if (!empty($itemIds)) {
                    $chunks = array_chunk($itemIds, 20);
                    foreach ($chunks as $chunk) {
                        $itemsResponse = $targetClient->get('/items', ['ids' => implode(',', $chunk)]);
                        foreach ($itemsResponse as $itemData) {
                            $item = $itemData['body'] ?? $itemData;
                            if (($item['catalog_product_id'] ?? null) === $catalogProductId) {
                                $isDuplicate = true;
                                $duplicateReason = 'Produto de catálogo já existe na conta destino';
                                break 2;
                            }
                        }
                    }
                }
            } else {
                $duplicateReason = 'Produto de catálogo já cadastrado localmente';
            }
        } else {
            // Para não-catálogo, verificar por SKU ou título similar
            $existingCheck = $this->checkNonCatalogDuplicate($targetAccountId, $sourceItem);
            if ($existingCheck['is_duplicate']) {
                $isDuplicate = true;
                $duplicateReason = $existingCheck['reason'];
            }
        }

        // 4. Calcular preço usando método centralizado
        $originalPrice = $sourceItem['price'];
        $priceResult = $this->calculateFinalPrice($originalPrice, $pricingStrategy, $targetAccountId, $sourceItem);
        $finalPrice = $priceResult['price'];
        $strategyApplied = $priceResult['strategy_applied'];

        // 5. Extrair marca
        $brand = $this->extractBrandFromItem($sourceItem);

        return [
            'status' => 'success',
            'item_title' => $sourceItem['title'],
            'original_price' => $originalPrice,
            'final_price' => $finalPrice,
            'currency_id' => $sourceItem['currency_id'] ?? 'BRL',
            'is_catalog' => $isCatalog,
            'is_duplicate' => $isDuplicate,
            'duplicate_reason' => $duplicateReason,
            'strategy_applied' => $strategyApplied,
            'catalog_product_id' => $catalogProductId,
            'brand' => $brand,
            'pictures_count' => count($sourceItem['pictures'] ?? []),
            'has_variations' => !empty($sourceItem['variations']),
            'variations_count' => count($sourceItem['variations'] ?? []),
        ];
    }

    /**
     * Preview de preço em lote para múltiplos item_ids.
     * Retorna preço original vs preço final calculado, e indicador de duplicidade.
     */
    public function pricePreviewBatch(array $params): array
    {
        $itemIds = $params['item_ids'] ?? [];
        $targetAccountId = (int)$params['target_account_id'];
        $sourceAccountId = $params['source_account_id'] ?? null;
        $pricingStrategy = $params['pricing_strategy'] ?? ($params['options']['pricing_strategy'] ?? ['type' => 'copy']);

        if (!is_array($itemIds)) {
            throw new \InvalidArgumentException('item_ids deve ser um array');
        }

        // Guardrail: evitar previews gigantes (UI também limita, mas garantimos aqui)
        $itemIds = array_values(array_filter(array_map('trim', $itemIds)));
        if (count($itemIds) > 50) {
            $itemIds = array_slice($itemIds, 0, 50);
        }

        $results = [];
        $sumOriginal = 0.0;
        $sumFinal = 0.0;
        $countOk = 0;
        $countDup = 0;
        $countErr = 0;

        foreach ($itemIds as $itemId) {
            $sim = $this->simulateClone([
                'source_account_id' => $sourceAccountId,
                'source_item_id' => $itemId,
                'target_account_id' => $targetAccountId,
                'pricing_strategy' => $pricingStrategy,
            ]);

            if (($sim['status'] ?? null) !== 'success') {
                $countErr++;
                $results[] = [
                    'id' => $itemId,
                    'status' => 'error',
                    'message' => $sim['message'] ?? 'Erro ao simular',
                ];
                continue;
            }

            $original = (float)($sim['original_price'] ?? 0);
            $final = (float)($sim['final_price'] ?? 0);
            $delta = round($final - $original, 2);
            $deltaPct = $original > 0 ? round(($delta / $original) * 100, 2) : null;

            $sumOriginal += $original;
            $sumFinal += $final;
            $countOk++;
            if (!empty($sim['is_duplicate'])) {
                $countDup++;
            }

            $results[] = [
                'id' => $itemId,
                'status' => 'success',
                'title' => $sim['item_title'] ?? null,
                'currency_id' => $sim['currency_id'] ?? 'BRL',
                'original_price' => $original,
                'final_price' => $final,
                'delta' => $delta,
                'delta_percent' => $deltaPct,
                'strategy_applied' => $sim['strategy_applied'] ?? null,
                'is_duplicate' => (bool)($sim['is_duplicate'] ?? false),
                'duplicate_reason' => $sim['duplicate_reason'] ?? null,
                'is_catalog' => (bool)($sim['is_catalog'] ?? false),
                'brand' => $sim['brand'] ?? null,
                'catalog_product_id' => $sim['catalog_product_id'] ?? null,
            ];
        }

        return [
            'status' => 'success',
            'pricing_strategy' => $pricingStrategy,
            'results' => $results,
            'summary' => [
                'items_requested' => count($itemIds),
                'items_simulated' => $countOk,
                'duplicates' => $countDup,
                'errors' => $countErr,
                'total_original' => round($sumOriginal, 2),
                'total_final' => round($sumFinal, 2),
                'total_delta' => round($sumFinal - $sumOriginal, 2),
                'total_delta_percent' => $sumOriginal > 0 ? round((($sumFinal - $sumOriginal) / $sumOriginal) * 100, 2) : null,
            ],
        ];
    }

    /**
     * Verifica duplicidade na tabela items local
     */
    public function checkLocalDuplicate(string $catalogProductId, int $targetAccountId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM items 
                WHERE account_id = :account_id 
                AND catalog_product_id = :catalog_id 
                AND status != 'closed'
            ");
            $stmt->execute([
                'account_id' => $targetAccountId,
                'catalog_id' => $catalogProductId
            ]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // FASE 1: Listagem por Seller ID (público) + Classificação + Facets
    // =========================================================================

    /**
     * Lista anúncios de um seller público (qualquer vendedor do ML)
     * Classifica como catálogo/não-catálogo e extrai facets de marca
     *
     * NOTA: Devido a restrições da API do Mercado Livre (desde 2025),
     * a busca por seller_id de terceiros retorna 403 Forbidden.
     * Esta funcionalidade só funciona para contas próprias vinculadas.
     *
     * @param string $sellerId ML Seller ID (numérico)
     * @param array $filters Filtros opcionais: category, brand, is_catalog, keyword, offset, limit
     * @return array Lista paginada com items, total, facets
     */
    public function listSellerItems(string $sellerId, array $filters = []): array
    {
        // Limpar ID
        $sellerId = preg_replace('/\D/', '', $sellerId);

        // Detectar site do vendedor
        $siteId = $this->getSellerSiteId($sellerId);

        // Usar cliente autenticado (API do ML agora requer autenticação para busca por seller_id)
        $client = $this->getAuthenticatedClientForSearch();

        $limit = min((int)($filters['limit'] ?? 50), 50);
        $offset = (int)($filters['offset'] ?? 0);

        // Verificar se o seller_id é de uma conta própria vinculada
        $ownSellerIds = $this->getAllOwnSellerIds();
        $isOwnAccount = in_array($sellerId, $ownSellerIds, true);

        // Para contas próprias, usar endpoint /users/{id}/items/search
        if ($isOwnAccount) {
            return $this->listOwnAccountItems($sellerId, $filters);
        }

        // Para sellers externos, tentar a busca (pode falhar com 403)
        $params = [
            'seller_id' => $sellerId,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if (!empty($filters['category'])) {
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['keyword'])) {
            $params['q'] = $filters['keyword'];
        }

        // Buscar na API (requer autenticação - passar public: false)
        $searchUrl = "/sites/{$siteId}/search";
        $searchResults = $client->get($searchUrl, $params, null, false);

        // Tratar erro 403 (bloqueio da API para sellers externos)
        if (isset($searchResults['error'])) {
            $errorMsg = $searchResults['message'] ?? $searchResults['error'] ?? 'Unknown';
            
            if ($searchResults['status'] === 403 || stripos($errorMsg, 'forbidden') !== false) {
                throw new Exception(
                    'A API do Mercado Livre não permite mais buscar anúncios de outros vendedores. ' .
                    'Esta funcionalidade só está disponível para suas próprias contas vinculadas. ' .
                    'Use a opção "Minha Conta" ou informe IDs de anúncios específicos.'
                );
            }
            
            throw new Exception('Erro ao buscar itens do seller: ' . $errorMsg);
        }

        $items = $searchResults['results'] ?? [];
        $total = $searchResults['paging']['total'] ?? count($items);

        // Facets de categoria (preferir available_filters para contagens globais)
        $categoryFacets = [];
        if (!empty($searchResults['available_filters']) && is_array($searchResults['available_filters'])) {
            foreach ($searchResults['available_filters'] as $filter) {
                if (($filter['id'] ?? null) !== 'category') {
                    continue;
                }
                foreach (($filter['values'] ?? []) as $value) {
                    $categoryId = (string)($value['id'] ?? '');
                    if ($categoryId === '') {
                        continue;
                    }
                    $categoryFacets[$categoryId] = [
                        'name' => (string)($value['name'] ?? $categoryId),
                        'count' => (int)($value['results'] ?? 0),
                    ];
                }
                break;
            }
        }

        // Processar cada item para classificar e extrair dados
        $processedItems = [];
        $brandCounts = [];
        $catalogCount = 0;
        $nonCatalogCount = 0;

        // Fallback: se não veio facets de categoria, contar pelo que está na página
        if (empty($categoryFacets)) {
            foreach ($items as $item) {
                $categoryId = (string)($item['category_id'] ?? '');
                if ($categoryId === '') {
                    continue;
                }
                if (!isset($categoryFacets[$categoryId])) {
                    $categoryFacets[$categoryId] = [
                        'name' => $categoryId,
                        'count' => 0,
                    ];
                }
                $categoryFacets[$categoryId]['count']++;
            }
        }

        foreach ($items as $item) {
            $isCatalog = !empty($item['catalog_product_id']);
            $brand = $this->extractBrandFromItem($item);

            if ($isCatalog) {
                $catalogCount++;
            } else {
                $nonCatalogCount++;
            }

            if ($brand) {
                $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
            }

            // Aplicar filtros locais (pós-busca)
            if (isset($filters['is_catalog'])) {
                $filterCatalog = filter_var($filters['is_catalog'], FILTER_VALIDATE_BOOLEAN);
                if ($filterCatalog !== $isCatalog) {
                    continue;
                }
            }

            if (!empty($filters['brand']) && strcasecmp($brand, $filters['brand']) !== 0) {
                continue;
            }

            $processedItems[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'price' => $item['price'],
                'currency_id' => $item['currency_id'] ?? 'BRL',
                'thumbnail' => $item['thumbnail'] ?? '',
                'permalink' => $item['permalink'] ?? '',
                'condition' => $item['condition'] ?? 'new',
                'available_quantity' => $item['available_quantity'] ?? 0,
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'category_id' => $item['category_id'] ?? '',
                'is_catalog' => $isCatalog,
                'catalog_product_id' => $item['catalog_product_id'] ?? null,
                'brand' => $brand,
                'listing_type_id' => $item['listing_type_id'] ?? '',
                'shipping_free' => ($item['shipping']['free_shipping'] ?? false),
            ];
        }

        // Ordenar facets de marca por contagem
        arsort($brandCounts);

        // Ordenar facets de categoria por contagem (desc)
        if (!empty($categoryFacets)) {
            uasort($categoryFacets, static function (array $a, array $b): int {
                return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            });
        }

        return [
            'status' => 'success',
            'seller_id' => $sellerId,
            'items' => $processedItems,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'summary' => [
                'catalog' => $catalogCount,
                'non_catalog' => $nonCatalogCount,
                'total_in_page' => count($items),
            ],
            'facets' => [
                'brands' => $brandCounts,
                'categories' => $categoryFacets,
            ],
        ];
    }

    /**
     * Obtém o site_id de um vendedor
     */
    private function getSellerSiteId(string $sellerId): string
    {
        try {
            // Se já for numérico, buscar na API
            if (is_numeric($sellerId)) {
                $client = new MercadoLivreClient();
                $response = $client->get("/users/{$sellerId}", [], null, true); // Cache
                return $response['site_id'] ?? 'MLB';
            }
            return 'MLB';
        } catch (\Throwable $e) {
            return 'MLB';
        }
    }

    /**
     * Obtém resumo/summary de um seller (contadores + facets de marca)
     *
     * @param string $sellerId ML Seller ID
     * @return array Summary com contadores e facets
     */
    public function getSellerSummary(string $sellerId): array
    {
        // Limpar ID
        $sellerId = preg_replace('/\D/', '', $sellerId);

        // Detectar site do vendedor
        $siteId = $this->getSellerSiteId($sellerId);

        // Verificar se o seller_id é de uma conta própria vinculada
        $ownSellerIds = $this->getAllOwnSellerIds();
        $isOwnAccount = in_array($sellerId, $ownSellerIds, true);

        // Para contas próprias, usar endpoint /users/{id}/items/search
        if ($isOwnAccount) {
            return $this->getOwnAccountSummary($sellerId);
        }

        // Para sellers externos, tentar a busca (pode falhar com 403)
        $client = $this->getAuthenticatedClientForSearch();

        // Buscar com limit=0 apenas para pegar paging.total e filtros disponíveis
        $searchResults = $client->get("/sites/{$siteId}/search", [
            'seller_id' => $sellerId,
            'limit' => 0,
        ], null, false);

        // Tratar erro 403 (bloqueio da API para sellers externos)
        if (isset($searchResults['error'])) {
            $errorMsg = $searchResults['message'] ?? $searchResults['error'] ?? 'Unknown';
            
            if ($searchResults['status'] === 403 || stripos($errorMsg, 'forbidden') !== false) {
                throw new Exception(
                    'A API do Mercado Livre não permite mais buscar anúncios de outros vendedores. ' .
                    'Esta funcionalidade só está disponível para suas próprias contas vinculadas. ' .
                    'Use a opção "Minha Conta" ou informe IDs de anúncios específicos.'
                );
            }
            
            throw new Exception('Erro ao buscar summary do seller: ' . $errorMsg);
        }

        $total = $searchResults['paging']['total'] ?? 0;

        // Buscar facets de categoria (contagem global + nome) a partir de available_filters
        $categoryFacets = [];
        $facetProbe = $client->get("/sites/{$siteId}/search", [
            'seller_id' => $sellerId,
            'limit' => 50,
            'offset' => 0,
        ], null, false);

        if (!empty($facetProbe['available_filters']) && is_array($facetProbe['available_filters'])) {
            foreach ($facetProbe['available_filters'] as $filter) {
                if (($filter['id'] ?? null) !== 'category') {
                    continue;
                }
                foreach (($filter['values'] ?? []) as $value) {
                    $categoryId = (string)($value['id'] ?? '');
                    if ($categoryId === '') {
                        continue;
                    }
                    $categoryFacets[$categoryId] = [
                        'name' => (string)($value['name'] ?? $categoryId),
                        'count' => (int)($value['results'] ?? 0),
                    ];
                }
                break;
            }
        }

        // Buscar amostra para classificar catálogo/não-catálogo e extrair marcas
        $sampleSize = min($total, 200);
        $catalogCount = 0;
        $nonCatalogCount = 0;
        $brandCounts = [];

        // Paginar para coletar amostra
        $collected = 0;
        $offset = 0;
        $batchSize = 50;

        while ($collected < $sampleSize && $offset < $total) {
            $batchResults = $client->get("/sites/MLB/search", [
                'seller_id' => $sellerId,
                'limit' => $batchSize,
                'offset' => $offset,
            ], null, false);

            if (isset($batchResults['error']) || empty($batchResults['results'])) {
                break;
            }

            foreach ($batchResults['results'] as $item) {
                $isCatalog = !empty($item['catalog_product_id']);
                $brand = $this->extractBrandFromItem($item);

                if ($isCatalog) {
                    $catalogCount++;
                } else {
                    $nonCatalogCount++;
                }

                if ($brand) {
                    $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                }

                $collected++;
            }

            $offset += $batchSize;
        }

        arsort($brandCounts);

        if (!empty($categoryFacets)) {
            uasort($categoryFacets, static function (array $a, array $b): int {
                return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            });
        }

        // Buscar informações básicas do seller (também requer autenticação agora)
        $sellerInfo = $client->get("/users/{$sellerId}", [], null, false);
        $sellerNickname = $sellerInfo['nickname'] ?? 'Seller ' . $sellerId;
        $sellerReputation = $sellerInfo['seller_reputation']['level_id'] ?? null;

        return [
            'status' => 'success',
            'seller_id' => $sellerId,
            'seller_nickname' => $sellerNickname,
            'seller_reputation' => $sellerReputation,
            'total_items' => $total,
            'sample_size' => $collected,
            'catalog_count' => $catalogCount,
            'non_catalog_count' => $nonCatalogCount,
            'catalog_percentage' => $collected > 0 ? round(($catalogCount / $collected) * 100, 1) : 0,
            'brands' => array_slice($brandCounts, 0, 50, true), // Top 50 marcas
            'categories' => array_slice($categoryFacets, 0, 50, true), // Top 50 categorias (contagem global quando disponível)
        ];
    }

    /**
     * Resolve lista de Item IDs para obter detalhes e classificação
     *
     * @param array $itemIds Lista de IDs de itens (ex: ['MLB123', 'MLB456'])
     * @return array Items com classificação catálogo/não-catálogo
     */
    public function resolveItemIds(array $itemIds): array
    {
        $client = new MercadoLivreClient();
        $results = [];
        $brandCounts = [];
        $categoryCounts = [];
        $catalogCount = 0;
        $nonCatalogCount = 0;

        // API suporta até 20 IDs por chamada
        $chunks = array_chunk($itemIds, 20);

        foreach ($chunks as $chunk) {
            $ids = implode(',', $chunk);
            $response = $client->get("/items", ['ids' => $ids]);

            if (is_array($response)) {
                foreach ($response as $itemData) {
                    if (isset($itemData['code']) && $itemData['code'] !== 200) {
                        // Item não encontrado ou erro
                        $results[] = [
                            'id' => $itemData['body']['id'] ?? $chunk[0] ?? 'unknown',
                            'error' => true,
                            'message' => $itemData['body']['message'] ?? 'Item não encontrado',
                        ];
                        continue;
                    }

                    $item = $itemData['body'] ?? $itemData;
                    $isCatalog = !empty($item['catalog_product_id']);
                    $brand = $this->extractBrandFromItem($item);

                    if ($isCatalog) {
                        $catalogCount++;
                    } else {
                        $nonCatalogCount++;
                    }

                    if ($brand) {
                        $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                    }

                    $categoryId = (string)($item['body']['category_id'] ?? $item['category_id'] ?? '');
                    if ($categoryId !== '') {
                        if (!isset($categoryCounts[$categoryId])) {
                            $categoryCounts[$categoryId] = [
                                'name' => $categoryId,
                                'count' => 0,
                            ];
                        }
                        $categoryCounts[$categoryId]['count']++;
                    }

                    $results[] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'currency_id' => $item['currency_id'] ?? 'BRL',
                        'thumbnail' => $item['thumbnail'] ?? '',
                        'permalink' => $item['permalink'] ?? '',
                        'condition' => $item['condition'] ?? 'new',
                        'available_quantity' => $item['available_quantity'] ?? 0,
                        'category_id' => $item['category_id'] ?? '',
                        'is_catalog' => $isCatalog,
                        'catalog_product_id' => $item['catalog_product_id'] ?? null,
                        'brand' => $brand,
                        'seller_id' => $item['seller_id'] ?? null,
                        'status' => $item['status'] ?? 'unknown',
                        'has_variations' => !empty($item['variations']),
                        'variations_count' => count($item['variations'] ?? []),
                        'pictures_count' => count($item['pictures'] ?? []),
                        'error' => false,
                    ];
                }
            }
        }

        arsort($brandCounts);

        if (!empty($categoryCounts)) {
            uasort($categoryCounts, static function (array $a, array $b): int {
                return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            });
        }

        return [
            'status' => 'success',
            'items' => $results,
            'total' => count($results),
            'summary' => [
                'catalog' => $catalogCount,
                'non_catalog' => $nonCatalogCount,
            ],
            'facets' => [
                'brands' => $brandCounts,
                'categories' => $categoryCounts,
            ],
        ];
    }

    /**
     * Extrai a marca de um item a partir dos atributos
     */
    private function extractBrandFromItem(array $item): ?string
    {
        $attributes = $item['attributes'] ?? [];

        foreach ($attributes as $attr) {
            if (in_array($attr['id'] ?? '', ['BRAND', 'MARCA', 'brand'])) {
                return $attr['value_name'] ?? null;
            }
        }

        return null;
    }

    // =========================================================================
    // FASE 1: Clone de Anúncios Não-Catálogo
    // =========================================================================

    /**
     * Clona um anúncio (catálogo OU não-catálogo) para a conta destino
     *
     * @param array $params Parâmetros de clonagem
     * @return array Resultado da operação
     */
    public function cloneItem(array $params): array
    {
        $sourceItemId = $params['source_item_id'];
        $targetAccountId = $params['target_account_id'];
        $sourceAccountId = $params['source_account_id'] ?? null;
        $pricingStrategy = $params['pricing_strategy'] ?? ['type' => 'copy'];
        $stockStrategy = $params['stock_strategy'] ?? ['type' => 'copy'];
        $options = $params['options'] ?? [];
        $templateSlug = $params['template_slug'] ?? null;
        $jobId = $params['job_id'] ?? null;

        // Se template foi especificado, carregar e aplicar regras
        $template = null;
        if ($templateSlug) {
            $template = $this->getTemplateService()->getTemplate($templateSlug);
            if ($template) {
                // Template override das estratégias
                $pricingStrategy = $template['price_rules'] ?? $pricingStrategy;
                $stockStrategy = $template['stock_rules'] ?? $stockStrategy;
                $options = array_merge($options, $template['title_rules'] ?? []);
            }
        }

        // 1. Buscar item origem (pode ser de seller público ou conta própria)
        if ($sourceAccountId) {
            $sourceClient = new MercadoLivreClient($sourceAccountId);
        } else {
            $sourceClient = new MercadoLivreClient();
        }

        $sourceItem = $sourceClient->get("/items/{$sourceItemId}");

        if (isset($sourceItem['error'])) {
            return $this->logAndReturnError(
                $sourceAccountId,
                $sourceItemId,
                $targetAccountId,
                null,
                null,
                'Erro ao buscar item origem: ' . ($sourceItem['message'] ?? 'Unknown error'),
                'error',
                $jobId,
                $pricingStrategy['type'] ?? 'copy'
            );
        }

        // Se temos template, aplicar transformações ao item
        if ($template) {
            $sourceItem = $this->getTemplateService()->applyTemplateRules($sourceItem, $template);
        }

        // 2. Verificar tipo (catálogo ou não)
        $isCatalog = !empty($sourceItem['catalog_product_id']);
        $catalogProductId = $sourceItem['catalog_product_id'] ?? null;

        // 3. Verificar duplicidade na conta destino
        $targetClient = new MercadoLivreClient($targetAccountId);
        $targetSellerId = $targetClient->getSellerId();

        if ($isCatalog && $catalogProductId) {
            // Verificar duplicidade por catalog_product_id usando /users/{seller_id}/items/search
            $sellerItems = $targetClient->get("/users/{$targetSellerId}/items/search", [
                'status' => 'active',
                'limit' => 50
            ]);
            
            $itemIds = $sellerItems['results'] ?? [];
            $foundDuplicate = false;
            
            if (!empty($itemIds)) {
                foreach (array_chunk($itemIds, 20) as $chunk) {
                    $itemsResponse = $targetClient->get('/items', ['ids' => implode(',', $chunk)]);
                    foreach ($itemsResponse as $itemData) {
                        $item = $itemData['body'] ?? $itemData;
                        if (($item['catalog_product_id'] ?? null) === $catalogProductId) {
                            $foundDuplicate = true;
                            break 2;
                        }
                    }
                }
            }

            if ($foundDuplicate) {
                return $this->logAndReturnError(
                    $sourceAccountId,
                    $sourceItemId,
                    $targetAccountId,
                    null,
                    $catalogProductId,
                    'Item de catálogo duplicado na conta destino.',
                    'skipped_duplicate',
                    $jobId,
                    $pricingStrategy['type'] ?? 'copy',
                    $sourceItem['price'] ?? null
                );
            }
        } else {
            // Para não-catálogo, verificar por SKU ou título similar
            $existingCheck = $this->checkNonCatalogDuplicate($targetAccountId, $sourceItem);
            if ($existingCheck['is_duplicate']) {
                return $this->logAndReturnError(
                    $sourceAccountId,
                    $sourceItemId,
                    $targetAccountId,
                    null,
                    null,
                    'Possível duplicata detectada: ' . $existingCheck['reason'],
                    'skipped_duplicate',
                    $jobId,
                    $pricingStrategy['type'] ?? 'copy',
                    $sourceItem['price'] ?? null
                );
            }
        }

        // 4. Calcular preço
        $price = $this->calculatePrice($sourceItem['price'], $pricingStrategy, $targetAccountId, $sourceItem);

        // 5. Calcular estoque
        $stock = $this->calculateStock($sourceItem['available_quantity'], $stockStrategy);

        // 6. Buscar descrição do item origem
        $description = $sourceClient->get("/items/{$sourceItemId}/description");
        $descriptionText = $description['plain_text'] ?? '';

        // 7. Montar payload base
        $payload = [
            'title' => $this->sanitizeTitle($sourceItem['title'], $options),
            'category_id' => $sourceItem['category_id'],
            'price' => $price,
            'currency_id' => $sourceItem['currency_id'] ?? 'BRL',
            'available_quantity' => $stock,
            'buying_mode' => $sourceItem['buying_mode'] ?? 'buy_it_now',
            'listing_type_id' => $sourceItem['listing_type_id'] ?? 'gold_special',
            'condition' => $sourceItem['condition'] ?? 'new',
        ];

        // Aplicar guardrails de conteúdo
        $includePictures = $options['include_pictures'] ?? false;
        $includeDescription = $options['include_description'] ?? false;

        // Adicionar pictures apenas se permitido
        if ($includePictures && !empty($sourceItem['pictures'])) {
            $payload['pictures'] = $this->preparePictures($sourceItem['pictures']);
        } else {
            // Usar imagem placeholder ou deixar vazio para o usuário adicionar depois
            $payload['pictures'] = [];
        }

        // 8. Adicionar catalog_product_id se for catálogo
        if ($isCatalog && $catalogProductId) {
            $payload['catalog_product_id'] = $catalogProductId;
        }

        // 9. Copiar atributos relevantes (não-catálogo precisa)
        if (!$isCatalog && !empty($sourceItem['attributes'])) {
            $payload['attributes'] = $this->filterClonableAttributes($sourceItem['attributes']);
        }

        // 10. Copiar shipping mode
        if (isset($sourceItem['shipping']['mode'])) {
            $payload['shipping'] = ['mode' => $sourceItem['shipping']['mode']];
        }

        // 11. Copiar variações (se existirem e não for catálogo)
        if (!$isCatalog && !empty($sourceItem['variations'])) {
            $payload['variations'] = $this->prepareVariations($sourceItem['variations'], $price);
        }

        // 12. Status inicial (pausado por padrão para segurança)
        if ($options['start_paused'] ?? true) {
            $payload['status'] = 'paused';
        }

        // 13. Criar item na conta destino
        $newItem = $targetClient->post('/items', $payload);

        if (isset($newItem['error']) || !isset($newItem['id'])) {
            $msg = $newItem['message'] ?? 'Erro desconhecido';
            if (isset($newItem['cause']) && is_array($newItem['cause'])) {
                $causes = array_map(fn($c) => $c['message'] ?? '', $newItem['cause']);
                $msg .= ' Causes: ' . implode('; ', $causes);
            }

            return $this->logAndReturnError(
                $sourceAccountId,
                $sourceItemId,
                $targetAccountId,
                null,
                $catalogProductId,
                'Erro ao criar item na API: ' . $msg,
                'error',
                $jobId,
                $pricingStrategy['type'] ?? 'copy',
                $sourceItem['price'] ?? null,
                $price ?? null
            );
        }

        $targetItemId = $newItem['id'];

        // 14. Adicionar descrição ao novo item (apenas se guardrail permitir)
        if ($includeDescription && !empty($descriptionText)) {
            $targetClient->put("/items/{$targetItemId}/description", [
                'plain_text' => $descriptionText,
            ]);
        }

        // 15. Logar sucesso
        $this->logCloneResult($sourceAccountId, $sourceItemId, $targetAccountId, $targetItemId, $catalogProductId, $isCatalog, $this->extractBrandFromItem($sourceItem), $sourceItem);

        // 16. Registrar métricas
        try {
            $this->getMetricsService()->recordItemMetrics(
                $sourceItemId,
                $targetItemId,
                $targetAccountId,
                $sourceItem['category_id'] ?? null,
                $templateSlug,
                [
                    'source_price' => $sourceItem['price'] ?? 0,
                    'target_price' => $price,
                ]
            );
        } catch (Exception $e) {
            // Não falhar o clone por causa de métricas
            log_warning('Falha ao salvar metricas do clone', ['error' => $e->getMessage()]);
        }

        // 17. Agendar ações pós-clone se template definir
        if ($template && !empty($template['post_actions'])) {
            try {
                $postActions = is_string($template['post_actions']) 
                    ? json_decode($template['post_actions'], true) 
                    : $template['post_actions'];

                if (!empty($postActions)) {
                    // Buscar cloned_item_id do log
                    $stmt = $this->db->prepare("SELECT id FROM cloned_items WHERE target_item_id = :item_id ORDER BY id DESC LIMIT 1");
                    $stmt->execute(['item_id' => $targetItemId]);
                    $clonedItemId = $stmt->fetchColumn() ?: null;

                    $this->getPostActionsService()->scheduleActions(
                        $targetItemId,
                        $postActions,
                        $jobId,
                        $clonedItemId
                    );
                }
            } catch (Exception $e) {
                // Não falhar o clone por causa de ações pós-clone
                log_warning('Falha ao executar acoes pos-clone', ['error' => $e->getMessage()]);
            }
        }

        return [
            'status' => 'success',
            'target_item_id' => $targetItemId,
            'is_catalog' => $isCatalog,
            'template_applied' => $template ? $template['name'] : null,
            'message' => 'Item clonado com sucesso.',
        ];
    }

    /**
     * Verifica duplicidade para itens não-catálogo
     */
    private function checkNonCatalogDuplicate(int $targetAccountId, array $sourceItem): array
    {
        // Verificar por SKU se existir
        $sku = null;
        foreach ($sourceItem['attributes'] ?? [] as $attr) {
            if (in_array($attr['id'], ['SELLER_SKU', 'SKU'])) {
                $sku = $attr['value_name'] ?? null;
                break;
            }
        }

        if ($sku) {
            try {
                $stmt = $this->db->prepare("
                    SELECT item_id FROM items 
                    WHERE account_id = :account_id 
                    AND seller_custom_field = :sku
                    AND status != 'closed'
                    LIMIT 1
                ");
                $stmt->execute([
                    'account_id' => $targetAccountId,
                    'sku' => $sku,
                ]);
                if ($stmt->fetchColumn()) {
                    return ['is_duplicate' => true, 'reason' => "SKU '{$sku}' já existe"];
                }
            } catch (Exception $e) {
                // Ignorar erro de tabela não existente
                log_warning('Falha ao verificar duplicidade de SKU', ['error' => $e->getMessage()]);
            }
        }

        return ['is_duplicate' => false, 'reason' => null];
    }

    /**
     * Calcula preço baseado na estratégia
     */
    private function calculatePrice(float $originalPrice, array $strategy, int $targetAccountId, array $sourceItem): float
    {
        $type = $strategy['type'] ?? 'copy';

        switch ($type) {
            case 'copy':
                return round($originalPrice, 2);

            case 'markup_percent':
                $markup = (float)($strategy['value'] ?? 0);
                return round($originalPrice * (1 + ($markup / 100)), 2);

            case 'markdown_percent':
                $markdown = (float)($strategy['value'] ?? 0);
                return round($originalPrice * (1 - ($markdown / 100)), 2);

            case 'fixed':
                return round((float)($strategy['value'] ?? $originalPrice), 2);

            case 'aggressive':
            case 'competitive':
            case 'premium':
            case 'value':
                try {
                    $pricingService = new PricingStrategyService($targetAccountId);
                    $analysis = $pricingService->analyzeCompetitorPrices(
                        $sourceItem['category_id'],
                        null,
                        $sourceItem['title']
                    );
                    $suggestion = $pricingService->suggestPrice($analysis, $type);
                    if (isset($suggestion['suggested_price'])) {
                        return round($suggestion['suggested_price'], 2);
                    }
                } catch (Exception $e) {
                    log_warning('Erro em estratégia de preço inteligente', [
                        'category_id' => $sourceItem['category_id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
                return round($originalPrice, 2);

            default:
                return round($originalPrice, 2);
        }
    }

    /**
     * Calcula estoque baseado na estratégia
     */
    private function calculateStock(int $originalStock, array $strategy): int
    {
        $type = $strategy['type'] ?? 'copy';

        switch ($type) {
            case 'copy':
                return $originalStock;

            case 'fixed':
                return max(0, (int)($strategy['value'] ?? $originalStock));

            case 'zero':
                return 0;

            case 'percentage':
                $pct = (float)($strategy['value'] ?? 100);
                return max(0, (int)round($originalStock * ($pct / 100)));

            default:
                return $originalStock;
        }
    }

    /**
     * Sanitiza título aplicando regras opcionais
     */
    private function sanitizeTitle(string $title, array $options): string
    {
        $maxLength = 60;

        // Aplicar prefixo/sufixo se configurado
        if (!empty($options['title_prefix'])) {
            $title = trim($options['title_prefix']) . ' ' . $title;
        }

        if (!empty($options['title_suffix'])) {
            $title = $title . ' ' . trim($options['title_suffix']);
        }

        // Truncar se exceder limite
        if (mb_strlen($title) > $maxLength) {
            $title = mb_substr($title, 0, $maxLength - 3) . '...';
        }

        return trim($title);
    }

    /**
     * Prepara array de imagens para o payload
     */
    private function preparePictures(array $pictures): array
    {
        return array_map(function ($pic) {
            return ['source' => $pic['url'] ?? $pic['source'] ?? ''];
        }, $pictures);
    }

    /**
     * Filtra atributos que podem ser clonados
     */
    private function filterClonableAttributes(array $attributes): array
    {
        $excludeIds = ['ITEM_CONDITION', 'SELLER_SKU']; // Atributos que não devem ser copiados diretamente

        return array_filter($attributes, function ($attr) use ($excludeIds) {
            return !in_array($attr['id'], $excludeIds);
        });
    }

    /**
     * Prepara variações para o payload
     */
    private function prepareVariations(array $variations, float $basePrice): array
    {
        return array_map(function ($variation) use ($basePrice) {
            $v = [
                'attribute_combinations' => $variation['attribute_combinations'] ?? [],
                'available_quantity' => $variation['available_quantity'] ?? 0,
                'price' => $variation['price'] ?? $basePrice,
            ];

            if (!empty($variation['picture_ids'])) {
                $v['picture_ids'] = $variation['picture_ids'];
            }

            return $v;
        }, $variations);
    }

    /**
     * Loga resultado de clone com campos expandidos
     */
    private function logCloneResult(
        ?int $sourceAccountId,
        string $sourceItemId,
        int $targetAccountId,
        string $targetItemId,
        ?string $catalogProductId,
        bool $isCatalog,
        ?string $brand,
        array $sourceItem
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cloned_items 
                (source_account_id, source_item_id, target_account_id, target_item_id, catalog_product_id, status, is_catalog, brand, source_snapshot)
                VALUES (:source_account_id, :source_item_id, :target_account_id, :target_item_id, :catalog_product_id, 'created', :is_catalog, :brand, :source_snapshot)
            ");
            $stmt->execute([
                'source_account_id' => $sourceAccountId,
                'source_item_id' => $sourceItemId,
                'target_account_id' => $targetAccountId,
                'target_item_id' => $targetItemId,
                'catalog_product_id' => $catalogProductId,
                'is_catalog' => $isCatalog ? 1 : 0,
                'brand' => $brand,
                'source_snapshot' => json_encode([
                    'title' => $sourceItem['title'] ?? '',
                    'price' => $sourceItem['price'] ?? 0,
                    'category_id' => $sourceItem['category_id'] ?? '',
                ]),
            ]);
        } catch (Exception $e) {
            log_warning('Erro ao registrar log de clonagem expandida', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // FASE 3: Dry-run Avançado com Validações
    // =========================================================================

    /**
     * Realiza dry-run avançado com validações detalhadas
     *
     * @param array $params Parâmetros incluindo items, target_account_id, options
     * @return array Resultado com can_clone, warnings, errors por item
     */
    public function dryRunBatch(array $params): array
    {
        $itemIds = $params['item_ids'] ?? [];
        $targetAccountId = $params['target_account_id'];
        $sourceAccountId = $params['source_account_id'] ?? null;
        $options = $params['options'] ?? [];

        $results = [];
        $validCount = 0;
        $invalidCount = 0;

        // Resolver items
        $resolvedData = $this->resolveItemIds($itemIds);
        $items = $resolvedData['items'] ?? [];

        foreach ($items as $item) {
            if ($item['error'] ?? false) {
                $results[] = [
                    'id' => $item['id'],
                    'can_clone' => false,
                    'errors' => ['Item não encontrado: ' . ($item['message'] ?? '')],
                    'warnings' => [],
                ];
                $invalidCount++;
                continue;
            }

            $validation = $this->validateItemForClone($item, $targetAccountId, $options);

            $results[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'price' => $item['price'],
                'is_catalog' => $item['is_catalog'],
                'brand' => $item['brand'],
                'can_clone' => $validation['can_clone'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'suggested_fixes' => $validation['suggested_fixes'] ?? [],
            ];

            if ($validation['can_clone']) {
                $validCount++;
            } else {
                $invalidCount++;
            }
        }

        return [
            'status' => 'success',
            'results' => $results,
            'summary' => [
                'total' => count($items),
                'valid' => $validCount,
                'invalid' => $invalidCount,
            ],
        ];
    }

    /**
     * Valida um item individual para clonagem
     */
    private function validateItemForClone(array $item, int $targetAccountId, array $options = []): array
    {
        $errors = [];
        $warnings = [];
        $fixes = [];

        // 1. Validar título
        $titleLen = mb_strlen($item['title'] ?? '');
        if ($titleLen === 0) {
            $errors[] = 'Título vazio';
        } elseif ($titleLen > 60) {
            $warnings[] = "Título excede 60 caracteres ({$titleLen})";
            $fixes[] = 'Título será truncado automaticamente';
        } elseif ($titleLen < 20) {
            $warnings[] = 'Título muito curto (< 20 caracteres) - pode afetar SEO';
        }

        // 2. Validar imagens
        $picturesCount = $item['pictures_count'] ?? 0;
        if ($picturesCount === 0) {
            $errors[] = 'Item sem imagens';
        } elseif ($picturesCount < 3) {
            $warnings[] = "Poucas imagens ({$picturesCount}) - recomendado mínimo 6";
        }

        // 3. Validar status
        if (($item['status'] ?? '') === 'closed') {
            $errors[] = 'Item fechado não pode ser clonado';
        } elseif (($item['status'] ?? '') === 'paused') {
            $warnings[] = 'Item origem está pausado';
        }

        // 4. Validar variações
        if ($item['has_variations'] ?? false) {
            $warnings[] = "Item possui {$item['variations_count']} variações - verificar compatibilidade";
        }

        // 5. Verificar duplicidade
        if ($item['is_catalog'] && !empty($item['catalog_product_id'])) {
            $isDuplicate = $this->checkLocalDuplicate($item['catalog_product_id'], $targetAccountId);
            if ($isDuplicate) {
                $errors[] = 'Produto de catálogo já existe na conta destino';
            }
        }

        // 6. Validar marca
        if (empty($item['brand'])) {
            $warnings[] = 'Marca não identificada - pode afetar visibilidade';
        }

        // 7. Verificar preço mínimo
        if (($item['price'] ?? 0) < 1) {
            $errors[] = 'Preço inválido ou menor que mínimo';
        }

        return [
            'can_clone' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'suggested_fixes' => $fixes,
        ];
    }

    // =========================================================================
    // FASE 4: Job Batch Assíncrono
    // =========================================================================

    /**
     * Cria um job de clonagem em lote
     *
     * @param array $params Parâmetros do job
     * @return array Dados do job criado
     */
    public function createBatchJob(array $params): array
    {
        $jobId = 'clone_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $targetAccountId = $params['target_account_id'];
        $sourceType = $params['source_type'] ?? 'item_ids';
        $sourceSellerId = $params['source_seller_id'] ?? null;
        $sourceAccountId = $params['source_account_id'] ?? null;
        $itemIds = $params['item_ids'] ?? [];
        $options = $params['options'] ?? [];
        $userId = $params['user_id'] ?? null;
        $templateSlug = $params['template_slug'] ?? null;

        // Validar template se especificado
        $templateId = null;
        if ($templateSlug) {
            $template = $this->getTemplateService()->getTemplate($templateSlug);
            if ($template) {
                $templateId = $template['id'];
            }
        }

        // Inserir job principal
        $stmt = $this->db->prepare("
            INSERT INTO catalog_clone_jobs 
            (job_id, target_account_id, source_type, source_seller_id, source_account_id, total_items, options, created_by_user_id, template_id, template_slug, status)
            VALUES (:job_id, :target_account_id, :source_type, :source_seller_id, :source_account_id, :total_items, :options, :user_id, :template_id, :template_slug, 'pending')
        ");

        $stmt->execute([
            'job_id' => $jobId,
            'target_account_id' => $targetAccountId,
            'source_type' => $sourceType,
            'source_seller_id' => $sourceSellerId,
            'source_account_id' => $sourceAccountId,
            'total_items' => count($itemIds),
            'options' => json_encode($options),
            'user_id' => $userId,
            'template_id' => $templateId,
            'template_slug' => $templateSlug,
        ]);

        // Inserir itens do job
        $stmtItem = $this->db->prepare("
            INSERT INTO catalog_clone_job_items 
            (job_id, source_item_id, status)
            VALUES (:job_id, :source_item_id, 'pending')
        ");

        foreach ($itemIds as $itemId) {
            $stmtItem->execute([
                'job_id' => $jobId,
                'source_item_id' => trim($itemId),
            ]);
        }

        // Disparar job service se disponível
        try {
            $jobService = new JobService();
            $jobService->dispatch('catalog_clone_batch', ['batch_job_id' => $jobId]);
        } catch (Exception $e) {
            log_error('Erro ao disparar job de clone batch', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'status' => 'created',
            'job_id' => $jobId,
            'total_items' => count($itemIds),
            'template_applied' => $templateSlug,
            'message' => 'Job de clonagem em lote criado com sucesso',
        ];
    }

    /**
     * Obtém status de um job de clonagem
     *
     * @param string $jobId ID do job
     * @return array Status do job
     */
    public function getJobStatus(string $jobId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM catalog_clone_jobs WHERE job_id = :job_id
        ");
        $stmt->execute(['job_id' => $jobId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            return ['status' => 'error', 'message' => 'Job não encontrado'];
        }

        // Buscar itens do job
        $stmtItems = $this->db->prepare("
            SELECT source_item_id, target_item_id, status, error_message, is_catalog, brand
            FROM catalog_clone_job_items 
            WHERE job_id = :job_id
        ");
        $stmtItems->execute(['job_id' => $jobId]);
        $items = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'status' => 'success',
            'job' => [
                'job_id' => $job['job_id'],
                'status' => $job['status'],
                'total_items' => (int)$job['total_items'],
                'processed_items' => (int)$job['processed_items'],
                'successful_items' => (int)$job['successful_items'],
                'failed_items' => (int)$job['failed_items'],
                'skipped_items' => (int)$job['skipped_items'],
                'progress_percent' => $job['total_items'] > 0
                    ? round(($job['processed_items'] / $job['total_items']) * 100, 1)
                    : 0,
                'created_at' => $job['created_at'],
                'started_at' => $job['started_at'],
                'completed_at' => $job['completed_at'],
            ],
            'items' => $items,
        ];
    }
}
