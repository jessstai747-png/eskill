<?php

namespace App\Services;

use App\Services\AI\SEO\TitleKiller;
use App\Services\AI\SEO\DescriptionKiller;
use App\Services\AI\SEO\AttributeKiller;
use App\Services\CategoryService;
use PDO;
use App\Database;

/**
 * ListingBuilderService V2
 *
 * Complete implementation for building SEO-optimized listings
 * with AI-generated titles, descriptions, and attributes.
 */
class ListingBuilderService
{
    public MercadoLivreClient $client;
    private $accountId;
    private PDO $db;

    public const DESCRIPTION_TEMPLATES = [
        'default' => "{{title}} - descrição padrão",
    ];

    public function __construct(?string $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = new MercadoLivreClient($accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Build a complete listing with AI optimization
     */
    public function buildListing(array $productData): array
    {
        $listing = [
            'title' => $productData['title'] ?? '',
            'price' => (float)($productData['price'] ?? 0),
            'category_id' => $productData['category_id'] ?? '',
            'currency_id' => 'BRL',
            'available_quantity' => $productData['quantity'] ?? 1,
            'buying_mode' => 'buy_it_now',
            'condition' => $productData['condition'] ?? 'new',
            'listing_type_id' => $productData['listing_type'] ?? 'bronze',
            'description' => ['plain_text' => $productData['description'] ?? ''],
            'pictures' => [],
            'attributes' => [],
        ];

        // Add pictures
        if (!empty($productData['images'])) {
            foreach ($productData['images'] as $imgUrl) {
                $listing['pictures'][] = ['source' => $imgUrl];
            }
        }

        // Add attributes
        if (!empty($productData['attributes'])) {
            $listing['attributes'] = $productData['attributes'];
        }

        return $listing;
    }

    /**
     * Build SEO-optimized listing using AI services
     */
    public function buildOptimizedListing(array $productData): array
    {
        $listing = $this->buildListing($productData);

        try {
            // Optimize title with AI
            if ($this->accountId) {
                $titleKiller = new TitleKiller((int)$this->accountId);
                $optimized = $titleKiller->generateKillerTitle([
                    // TitleKiller suporta tanto 'title' quanto 'product_name'
                    'title' => $productData['title'] ?? '',
                    'product_name' => $productData['title'] ?? '',
                    'category_id' => $productData['category_id'] ?? '',
                    'brand' => $productData['brand'] ?? '',
                    'model' => $productData['model'] ?? '',
                    'attributes' => $productData['attributes'] ?? [],
                ]);

                $newTitle = $optimized['primary'] ?? ($optimized['titles'][0] ?? null);
                if (is_string($newTitle) && $newTitle !== '') {
                    $listing['title'] = $newTitle;
                }
            }
        } catch (\Exception $e) {
            // Log but continue with original title
            log_warning('Falha na otimização de título do listing', [
                'error' => $e->getMessage(),
            ]);
        }

        return $listing;
    }

    /**
     * Build HTML description using AI
     */
    public function buildDescription(array $productData = [], array $keywords = []): string
    {
        if (!$this->accountId) {
            return $productData['description'] ?? '';
        }

        try {
            $descKiller = new DescriptionKiller((int)$this->accountId);

            $features = $productData['features'] ?? [];
            if (!is_array($features)) {
                $features = [];
            }

            // Opcional: usar keywords como reforço de contexto/itens (sem forçar stuffing)
            if ($keywords !== []) {
                $primary = $keywords['primary_keywords'] ?? [];
                if (is_array($primary) && $primary !== []) {
                    $features = array_values(array_unique(array_merge($features, array_slice($primary, 0, 8))));
                }
            }

            $payload = $productData;
            $payload['title'] = $productData['title'] ?? '';
            $payload['category_id'] = $productData['category_id'] ?? '';
            $payload['features'] = $features;

            $result = $descKiller->generateKillerDescription($payload);

            $desc = $result['description'] ?? null;
            if (is_string($desc) && $desc !== '') {
                return $desc;
            }

            return $productData['description'] ?? '';
        } catch (\Exception $e) {
            log_warning('Falha na geração de descrição do listing', [
                'error' => $e->getMessage(),
            ]);
            return $productData['description'] ?? '';
        }
    }

    /**
     * Duplica um anúncio existente (ML) e retorna uma versão otimizada (preview).
     *
     * Obs: Este método NÃO publica automaticamente; ele apenas gera o payload de um novo anúncio.
     */
    public function duplicateAndOptimize(string $itemId): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return ['success' => false, 'error' => 'Item ID inválido'];
        }

        try {
            $item = $this->client->get('/items/' . $itemId);

            $description = [];
            try {
                $description = $this->client->get('/items/' . $itemId . '/description');
            } catch (\Throwable $e) {
                // Descrição pode falhar; seguimos com o básico.
            }

            $images = [];
            if (isset($item['pictures']) && is_array($item['pictures'])) {
                foreach ($item['pictures'] as $pic) {
                    if (!is_array($pic)) {
                        continue;
                    }
                    $src = $pic['secure_url'] ?? $pic['url'] ?? $pic['source'] ?? null;
                    if (is_string($src) && $src !== '') {
                        $images[] = $src;
                    }
                }
            }

            $productData = [
                'item_id' => $itemId,
                'item_data' => (is_array($item) ? $item : []),
                'title' => $item['title'] ?? '',
                'price' => $item['price'] ?? 0,
                'category_id' => $item['category_id'] ?? '',
                'quantity' => $item['available_quantity'] ?? 1,
                'condition' => $item['condition'] ?? 'new',
                'listing_type' => $item['listing_type_id'] ?? 'bronze',
                'description' => $description['plain_text'] ?? ($description['text'] ?? ''),
                'images' => $images,
                'attributes' => (isset($item['attributes']) && is_array($item['attributes'])) ? $item['attributes'] : [],
            ];

            $listing = $this->buildOptimizedListing($productData);
            $listing['description'] = ['plain_text' => $this->buildDescription($productData)];
            $listing['attributes'] = $this->buildAttributes($productData);

            return [
                'success' => true,
                'source_item_id' => $itemId,
                'generated_at' => date('c'),
                'listing' => $listing,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Falha ao duplicar e otimizar anúncio',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build attributes using AI analysis
     */
    public function buildAttributes(array $productData = []): array
    {
        if (!$this->accountId || empty($productData['category_id'])) {
            return $productData['attributes'] ?? [];
        }

        try {
            $attrKiller = new AttributeKiller((int)$this->accountId);

            $categoryId = (string)$productData['category_id'];
            $attrs = $productData['attributes'] ?? [];
            if (!is_array($attrs)) {
                $attrs = [];
            }

            $existingIds = [];
            foreach ($attrs as $a) {
                if (is_array($a) && isset($a['id']) && is_string($a['id'])) {
                    $existingIds[$a['id']] = true;
                }
            }

            // Caso de duplicação: temos o item original (evita PUT no item original).
            // Geramos apenas o plano (dry-run) e aplicamos as sugestões ao novo payload.
            if (!empty($productData['item_id']) && is_string($productData['item_id'])) {
                $itemId = $productData['item_id'];
                $itemData = (isset($productData['item_data']) && is_array($productData['item_data']))
                    ? $productData['item_data']
                    : [];

                $plan = $attrKiller->planMissingAttributes($itemId, $categoryId, $itemData);
                $toFill = $plan['attributes_to_fill'] ?? [];
                if (is_array($toFill)) {
                    foreach ($toFill as $fillAttr) {
                        if (!is_array($fillAttr)) {
                            continue;
                        }
                        $id = $fillAttr['id'] ?? null;
                        if (!is_string($id) || $id === '' || isset($existingIds[$id])) {
                            continue;
                        }
                        $attrs[] = $fillAttr;
                        $existingIds[$id] = true;
                    }
                }

                return $attrs;
            }

            // Caso geral (pré-publicação): sugere atributos a partir do título.
            $suggest = $attrKiller->extractAttributesFromTitle((string)($productData['title'] ?? ''), $categoryId);
            $suggested = $suggest['attributes'] ?? [];
            if (is_array($suggested)) {
                foreach ($suggested as $s) {
                    if (!is_array($s)) {
                        continue;
                    }
                    $id = $s['attribute_id'] ?? null;
                    $value = $s['value'] ?? null;
                    if (!is_string($id) || $id === '' || isset($existingIds[$id])) {
                        continue;
                    }
                    if (!is_string($value) || $value === '') {
                        continue;
                    }
                    $attrs[] = ['id' => $id, 'value_name' => $value];
                    $existingIds[$id] = true;
                }
            }

            return $attrs;
        } catch (\Exception $e) {
            log_warning('Falha ao preencher atributos do listing', [
                'error' => $e->getMessage(),
            ]);
            return $productData['attributes'] ?? [];
        }
    }

    /**
     * Publish listing to Mercado Livre
     */
    public function publishListing(array $listingData): array
    {
        try {
            $response = $this->client->post('/items', $listingData);
            
            if (!empty($response['id'])) {
                // Save to local database
                $this->saveListing($response);
                
                return [
                    'success' => true,
                    'item_id' => $response['id'],
                    'permalink' => $response['permalink'] ?? null,
                    'data' => $response
                ];
            }
            
            return [
                'success' => false,
                'error' => 'API returned no item ID',
                'response' => $response
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Save listing to local database
     */
    private function saveListing(array $item): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO items 
            (ml_item_id, account_id, title, category_id, price, available_quantity, status, data, created_at)
            VALUES (:ml_id, :acc, :title, :cat, :price, :qty, :status, :data, NOW())
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title),
                price = VALUES(price),
                status = VALUES(status),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            'ml_id' => $item['id'],
            'acc' => $this->accountId,
            'title' => $item['title'],
            'cat' => $item['category_id'],
            'price' => $item['price'],
            'qty' => $item['available_quantity'],
            'status' => $item['status'],
            'data' => json_encode($item)
        ]);
    }

    /**
     * Get category suggestions
     */
    public function suggestCategory(string $productName): array
    {
        try {
            return $this->client->get('/sites/MLB/domain_discovery/search', [
                'q' => $productName
            ]);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
