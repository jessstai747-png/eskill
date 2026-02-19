<?php

namespace App\Services;

class PricingStrategyService
{
    private ?int $accountId;
    private MercadoLivreClient $mlClient;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->mlClient  = new MercadoLivreClient($accountId);
    }

    /**
     * Analisa preços de concorrentes em uma categoria usando busca pública do ML
     * e retorna estatísticas básicas (min, max, média, mediana).
     */
    public function analyzeCompetitorPrices(string $categoryId, ?string $brand = null, ?string $keyword = null): array
    {
        try {
            $params = [
                'category' => $categoryId,
                'limit'    => 50,
                'sort'     => 'price_asc'
            ];

            if (!empty($keyword)) {
                $params['q'] = $keyword;
            } elseif (!empty($brand)) {
                // fallback: usa a marca como termo de busca se não houver keyword
                $params['q'] = $brand;
            }

            $response = $this->mlClient->get('/sites/MLB/search', $params, 120, true);

            if (isset($response['error'])) {
                return [
                    'error'   => 'ml_api_error',
                    'message' => $response['message'] ?? 'Erro ao consultar Mercado Livre',
                    'status'  => $response['status'] ?? 0,
                ];
            }

            $items  = $response['results'] ?? [];
            $prices = array_values(array_filter(array_map(fn ($item) => (float)($item['price'] ?? 0), $items), fn ($p) => $p > 0));

            if (empty($prices)) {
                return [
                    'status'      => 'empty',
                    'price_stats' => [
                        'min'     => 0,
                        'max'     => 0,
                        'average' => 0,
                        'median'  => 0,
                        'count'   => 0,
                    ],
                    'samples'     => [],
                ];
            }

            sort($prices);
            $count  = count($prices);
            $median = $prices[(int)floor(($count - 1) / 2)];
            $avg    = array_sum($prices) / $count;

            return [
                'status'      => 'success',
                'price_stats' => [
                    'min'     => (float)min($prices),
                    'max'     => (float)max($prices),
                    'average' => round($avg, 2),
                    'median'  => round($median, 2),
                    'count'   => $count,
                ],
                // devolve só uma amostra para eventuais interfaces
                'samples'     => array_slice($items, 0, 10),
            ];
        } catch (\Throwable $e) {
            return [
                'error'   => 'ml_api_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sugere preço baseado nas estatísticas calculadas e na estratégia desejada.
     */
    public function suggestPrice(array $analysis, string $strategy = 'competitive', array $options = []): array
    {
        $stats = $analysis['price_stats'] ?? null;

        if (!$stats || empty($stats['count'])) {
            return [
                'status'          => 'error',
                'message'         => 'Sem dados suficientes para sugerir preço',
                'suggested_price' => null,
            ];
        }

        $min    = (float)$stats['min'];
        $max    = (float)$stats['max'];
        $avg    = (float)$stats['average'];
        $median = (float)$stats['median'];

        // margens simples usadas como heurística
        $suggested = match ($strategy) {
            'aggressive'   => max(0.01, $min * 0.99),                 // ligeiramente abaixo do menor
            'competitive'  => $median ?: $avg,                        // mediana como preço "justo"
            'premium'      => round(($avg ?: $median) * 1.08, 2),     // coloca ~8% acima da média
            'value',        => round(($avg ?: $median) * 0.97, 2),    // leve desconto
            default        => $median ?: $avg,
        };

        // Se usuário informou custo/margem alvo, ajusta para não vender abaixo do custo
        if (isset($options['cost'])) {
            $cost = (float)$options['cost'];
            $targetMargin = isset($options['target_margin']) ? (float)$options['target_margin'] : null;

            if ($targetMargin !== null) {
                $minPrice = $cost * (1 + $targetMargin);
                $suggested = max($suggested, $minPrice);
            } else {
                $suggested = max($suggested, $cost);
            }
        }

        return [
            'status'          => 'success',
            'strategy'        => $strategy,
            'suggested_price' => round($suggested, 2),
            'stats_used'      => $stats,
        ];
    }

    /**
     * Mantido para compatibilidade com chamadas antigas.
     */
    public function analyzeCategory(string $categoryId): array
    {
        return $this->analyzeCompetitorPrices($categoryId);
    }

    /**
     * Calcula preço aplicando uma margem simples (utilitário legacy).
     */
    public function calculatePrice(array $data): array
    {
        $price  = (float)($data['price'] ?? 0);
        $margin = (float)($data['margin'] ?? 0);
        $result = $margin > 0 ? $price * (1 + $margin) : $price;

        return [
            'success' => true,
            'price'   => $price,
            'margin'  => $margin,
            'result'  => round($result, 2),
        ];
    }
}
