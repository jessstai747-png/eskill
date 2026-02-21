<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Monitora concorrentes de um anúncio via API do Mercado Livre.
 *
 * Responsabilidades:
 * - Buscar o item base (/items/{id})
 * - Buscar resultados de mercado via /sites/MLB/search (endpoint público)
 * - Filtrar o próprio anúncio e anúncios do mesmo seller
 * - Calcular estatísticas de preços e percentil
 * - Persistir snapshot em competitor_pricing_cache (best-effort)
 */
class PricingCompetitorMonitorService
{
    private int $accountId;
    private MercadoLivreClient $mlClient;
    private StructuredLogService $logger;
    private ?PDO $db;

    public function __construct(
        int $accountId,
        ?PDO $db = null,
        ?MercadoLivreClient $mlClient = null,
        ?StructuredLogService $logger = null
    ) {
        $this->accountId = $accountId;
        $this->logger = $logger ?? new StructuredLogService();
        $this->mlClient = $mlClient ?? new MercadoLivreClient($accountId);

        if ($db instanceof PDO) {
            $this->db = $db;
            return;
        }

        try {
            $this->db = Database::getInstance();
        } catch (\Throwable $e) {
            $this->db = null;
            $this->logger->warning('PricingCompetitorMonitorService: DB indisponível (cache não será persistido)', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{success:bool, message?:string, error?:string, item?:array, concorrentes?:array<int, array>, estatisticas?:array, posicao_preco?:int, posicao_total?:int, atualizado_em?:string}
     */
    public function monitorItem(string $itemId, int $limit = 50, ?string $searchQuery = null): array
    {
        $limit = max(1, min(50, $limit));

        try {
            // Item base (público; cache curto para reduzir custo e rate-limit)
            $itemData = $this->mlClient->get("/items/{$itemId}", [], 60, true);
            if (!$itemData || isset($itemData['error'])) {
                return [
                    'success' => false,
                    'error' => $itemData['error'] ?? 'item_not_found',
                    'message' => $itemData['message'] ?? 'Item não encontrado',
                ];
            }

            $precoAtual = (float)($itemData['price'] ?? 0);
            $categoryId = (string)($itemData['category_id'] ?? '');
            $titulo = (string)($itemData['title'] ?? '');
            $ourSellerId = (string)($itemData['seller_id'] ?? '');
            $ourCatalogProductId = (string)($itemData['catalog_product_id'] ?? '');

            if ($precoAtual <= 0 || $categoryId === '' || $titulo === '') {
                return [
                    'success' => false,
                    'error' => 'invalid_item_data',
                    'message' => 'Dados do item insuficientes para monitorar concorrentes',
                ];
            }

            $keywords = $this->extractKeywords($titulo);
            $q = $this->normalizeSearchQuery($searchQuery, $keywords);

            $searchParams = [
                'category' => $categoryId,
                'limit' => $limit,
                'sort' => 'relevance',
            ];
            if ($q !== null) {
                $searchParams['q'] = $q;
            }

            // Search público (com cache curto para evitar espancar a API)
            $searchResult = $this->mlClient->searchItems($searchParams, 120);
            $results = $searchResult['results'] ?? [];

            $rawCompetitors = [];
            foreach ($results as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $candidateId = (string)($row['id'] ?? '');
                if ($candidateId === '' || $candidateId === $itemId) {
                    continue;
                }

                $candidateSellerId = (string)($row['seller']['id'] ?? $row['seller_id'] ?? '');
                if ($ourSellerId !== '' && $candidateSellerId !== '' && $candidateSellerId === $ourSellerId) {
                    // Excluir anúncios do mesmo seller (evita comparar consigo mesmo)
                    continue;
                }

                $candidatePrice = (float)($row['price'] ?? 0);
                if ($candidatePrice <= 0) {
                    continue;
                }

                $rawCompetitors[] = [
                    'id' => $candidateId,
                    'titulo' => (string)($row['title'] ?? ''),
                    'preco' => $candidatePrice,
                    'vendidos' => (int)($row['sold_quantity'] ?? 0),
                    'frete_gratis' => (bool)($row['shipping']['free_shipping'] ?? false),
                    'tipo_anuncio' => (string)($row['listing_type_id'] ?? 'gold_special'),
                    'vendedor' => $row['seller']['nickname'] ?? null,
                    'reputacao' => $row['seller']['seller_reputation']['level_id'] ?? null,
                    'catalog_product_id' => $row['catalog_product_id'] ?? null,
                    'diferenca_preco' => round((($candidatePrice - $precoAtual) / $precoAtual) * 100, 2),
                ];
            }

            // Se o item é de catálogo, priorizar concorrentes com o mesmo catalog_product_id.
            // Regra: só filtra se ainda houver um conjunto minimamente útil.
            $concorrentes = $this->maybeFilterByCatalogProductId($rawCompetitors, $ourCatalogProductId);

            // Ordenar por preço
            usort($concorrentes, static fn(array $a, array $b): int => ($a['preco'] <=> $b['preco']));

            $precos = array_values(array_filter(array_map(static fn(array $c): float => (float)($c['preco'] ?? 0), $concorrentes), static fn(float $p): bool => $p > 0));
            sort($precos);

            $stats = $this->buildStats($precos);

            // Posição no ranking de preços
            $posicao = 1;
            foreach ($concorrentes as $c) {
                if ((float)($c['preco'] ?? 0) < $precoAtual) {
                    $posicao++;
                }
            }

            $this->persistCacheSnapshot(
                itemId: $itemId,
                categoryId: $categoryId,
                concorrentes: $concorrentes,
                stats: $stats,
                posicao: $posicao
            );

            return [
                'success' => true,
                'item' => [
                    'id' => $itemId,
                    'titulo' => $titulo,
                    'preco' => $precoAtual,
                    'categoria' => $categoryId,
                    'seller_id' => $ourSellerId !== '' ? $ourSellerId : null,
                    'catalog_product_id' => $ourCatalogProductId !== '' ? $ourCatalogProductId : null,
                    'query' => $q,
                ],
                'posicao_preco' => $posicao,
                'posicao_total' => count($concorrentes) + 1,
                'estatisticas' => $stats,
                'concorrentes' => array_slice($concorrentes, 0, 20),
                'atualizado_em' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Erro ao monitorar concorrentes (ML)', [
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'monitor_failed',
                'message' => 'Erro ao monitorar concorrentes: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $titulo): array
    {
        $stopwords = ['de', 'da', 'do', 'para', 'com', 'em', 'e', 'a', 'o', 'as', 'os', 'um', 'uma'];
        $words = preg_split('/\s+/', mb_strtolower($titulo));
        if (!is_array($words)) {
            return [];
        }

        $keywords = array_filter($words, static function (string $w) use ($stopwords): bool {
            $w = trim($w);
            return $w !== '' && mb_strlen($w) > 2 && !in_array($w, $stopwords, true);
        });

        return array_values(array_slice($keywords, 0, 6));
    }

    /**
     * @param array<int, string> $keywords
     */
    private function normalizeSearchQuery(?string $searchQuery, array $keywords): ?string
    {
        $q = $searchQuery !== null ? trim($searchQuery) : '';
        if ($q !== '') {
            return mb_substr($q, 0, 120);
        }

        if (empty($keywords)) {
            return null;
        }

        $joined = trim(implode(' ', array_slice($keywords, 0, 5)));
        return $joined !== '' ? $joined : null;
    }

    /**
     * @param array<int, float> $precosSorted
     * @return array{total:int, preco_minimo:float, preco_maximo:float, preco_medio:float, preco_mediano:float}
     */
    private function buildStats(array $precosSorted): array
    {
        $total = count($precosSorted);
        if ($total === 0) {
            return [
                'total' => 0,
                'preco_minimo' => 0.0,
                'preco_maximo' => 0.0,
                'preco_medio' => 0.0,
                'preco_mediano' => 0.0,
            ];
        }

        $min = (float)$precosSorted[0];
        $max = (float)$precosSorted[$total - 1];
        $avg = (float)round(array_sum($precosSorted) / $total, 2);

        $median = 0.0;
        $mid = (int)floor($total / 2);
        if ($total % 2 === 0) {
            $median = (float)round(((float)$precosSorted[$mid - 1] + (float)$precosSorted[$mid]) / 2, 2);
        } else {
            $median = (float)$precosSorted[$mid];
        }

        return [
            'total' => $total,
            'preco_minimo' => $min,
            'preco_maximo' => $max,
            'preco_medio' => $avg,
            'preco_mediano' => $median,
        ];
    }

    /**
     * @param array<int, array> $concorrentes
     * @return array<int, array>
     */
    private function maybeFilterByCatalogProductId(array $concorrentes, string $catalogProductId): array
    {
        $catalogProductId = trim($catalogProductId);
        if ($catalogProductId === '') {
            return $concorrentes;
        }

        $matching = array_values(array_filter($concorrentes, static fn(array $c): bool => (string)($c['catalog_product_id'] ?? '') === $catalogProductId));
        if (count($matching) >= 3) {
            return $matching;
        }

        return $concorrentes;
    }

    /**
     * @param array<int, array> $concorrentes
     * @param array{total:int, preco_minimo:float, preco_maximo:float, preco_medio:float, preco_mediano:float} $stats
     */
    private function persistCacheSnapshot(string $itemId, string $categoryId, array $concorrentes, array $stats, int $posicao): void
    {
        if (!$this->db instanceof PDO) {
            return;
        }

        try {
            $totalConcorrentes = (int)($stats['total'] ?? 0);
            $totalComNossoItem = $totalConcorrentes + 1;
            $percentilPreco = $totalComNossoItem > 1
                ? round((($posicao - 1) / ($totalComNossoItem - 1)) * 100, 2)
                : 50.0;

            $topConcorrentes = array_map(static function (array $c): array {
                return [
                    'id' => $c['id'] ?? null,
                    'titulo' => $c['titulo'] ?? null,
                    'preco' => $c['preco'] ?? null,
                    'vendedor' => $c['vendedor'] ?? null,
                    'reputacao' => $c['reputacao'] ?? null,
                ];
            }, array_slice($concorrentes, 0, 20));

            $stmt = $this->db->prepare("
                INSERT INTO competitor_pricing_cache
                (
                    account_id, item_id, category_id,
                    preco_minimo, preco_maximo, preco_medio, preco_mediano,
                    qtd_concorrentes, top_concorrentes,
                    nossa_posicao_preco, percentil_preco,
                    expira_em
                )
                VALUES
                (
                    :account_id, :item_id, :category_id,
                    :preco_minimo, :preco_maximo, :preco_medio, :preco_mediano,
                    :qtd_concorrentes, :top_concorrentes,
                    :nossa_posicao_preco, :percentil_preco,
                    DATE_ADD(NOW(), INTERVAL 12 HOUR)
                )
                ON DUPLICATE KEY UPDATE
                    category_id = VALUES(category_id),
                    preco_minimo = VALUES(preco_minimo),
                    preco_maximo = VALUES(preco_maximo),
                    preco_medio = VALUES(preco_medio),
                    preco_mediano = VALUES(preco_mediano),
                    qtd_concorrentes = VALUES(qtd_concorrentes),
                    top_concorrentes = VALUES(top_concorrentes),
                    nossa_posicao_preco = VALUES(nossa_posicao_preco),
                    percentil_preco = VALUES(percentil_preco),
                    expira_em = VALUES(expira_em),
                    atualizado_em = NOW()
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'category_id' => $categoryId,
                'preco_minimo' => $stats['preco_minimo'] ?? null,
                'preco_maximo' => $stats['preco_maximo'] ?? null,
                'preco_medio' => $stats['preco_medio'] ?? null,
                'preco_mediano' => $stats['preco_mediano'] ?? null,
                'qtd_concorrentes' => $totalConcorrentes,
                'top_concorrentes' => json_encode($topConcorrentes),
                'nossa_posicao_preco' => $posicao,
                'percentil_preco' => $percentilPreco,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Falha ao salvar competitor_pricing_cache', [
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
