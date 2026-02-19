<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * AdsWizardService - Assistente Inteligente para Criação de Campanhas
 *
 * Pensado para usuários leigos: linguagem simples, sugestões automáticas,
 * criação com 1 clique e diagnósticos em português claro.
 */
class AdsWizardService
{
    private PDO $db;
    private AdsService $adsService;
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId ?? ($_SESSION['active_ml_account_id'] ?? 1);
        $this->db = Database::getInstance();
        $this->adsService = new AdsService($this->accountId);
    }

    /**
     * Retorna produtos elegíveis para anunciar com sugestão inteligente.
     * Ordena por potencial de venda (mais visitas + mais vendas = melhor candidato).
     */
    public function getEligibleProducts(int $limit = 20): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT
                    i.ml_id AS item_id,
                    i.title,
                    i.price,
                    i.available_quantity AS stock,
                    i.status,
                    COALESCE(i.sold_quantity, 0) AS sold,
                    COALESCE(i.thumbnail, '') AS thumbnail,
                    i.category_id
                FROM items i
                WHERE i.account_id = :account_id
                    AND i.status = 'active'
                    AND i.available_quantity > 0
                ORDER BY COALESCE(i.sold_quantity, 0) DESC, i.price DESC
                LIMIT {$limitSql}
            ");
            $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
            $stmt->execute();

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function ($item) {
                $item['price'] = (float)$item['price'];
                $item['stock'] = (int)$item['stock'];
                $item['sold'] = (int)$item['sold'];
                $item['suggestion'] = $this->getItemSuggestion($item);
                return $item;
            }, $items);
        } catch (\Exception $e) {
            log_error('Erro ao buscar produtos elegíveis para Ads', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Gera sugestão textual para um item (linguagem leiga).
     */
    private function getItemSuggestion(array $item): string
    {
        $sold = (int)$item['sold'];
        $price = (float)$item['price'];

        if ($sold >= 50) {
            return 'Campeão de vendas! Investir em Ads pode aumentar ainda mais.';
        }
        if ($sold >= 10) {
            return 'Bom histórico de vendas. Anunciar pode acelerar o resultado.';
        }
        if ($price >= 200) {
            return 'Produto com bom valor. Cada venda extra compensa o custo do anúncio.';
        }

        return 'Produto elegível para anúncio. Experimente com um orçamento pequeno.';
    }

    /**
     * Sugere orçamento diário ideal baseado no preço médio e volume de vendas.
     */
    public function suggestBudget(array $selectedItemIds): array
    {
        if (empty($selectedItemIds)) {
            return [
                'suggested' => 20.00,
                'min' => 10.00,
                'max' => 100.00,
                'explanation' => 'Selecione produtos para uma sugestão personalizada.',
            ];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($selectedItemIds), '?'));
            $params = array_merge($selectedItemIds, [$this->accountId]);

            $stmt = $this->db->prepare("
                SELECT
                    AVG(price) AS avg_price,
                    SUM(COALESCE(sold_quantity, 0)) AS total_sold,
                    COUNT(*) AS item_count
                FROM items
                WHERE ml_id IN ({$placeholders})
                    AND account_id = ?
            ");
            $stmt->execute($params);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $avgPrice = (float)($data['avg_price'] ?? 50);
            $totalSold = (int)($data['total_sold'] ?? 0);
            $itemCount = (int)($data['item_count'] ?? 1);

            // Lógica: investir ~5-8% do preço médio por dia, ajustado pelo volume
            $baseBudget = max(10, round($avgPrice * 0.06, 2));
            $multiplier = $itemCount > 3 ? 1.5 : 1.0;
            $suggested = round($baseBudget * $multiplier, 2);
            $min = max(10.00, round($suggested * 0.5, 2));
            $max = round($suggested * 3, 2);

            $explanation = "Com produtos com preço médio de R$ " . number_format($avgPrice, 2, ',', '.')
                . ", sugerimos R$ " . number_format($suggested, 2, ',', '.') . "/dia. "
                . "Isso é aproximadamente " . round(($suggested / max($avgPrice, 1)) * 100, 1) . "% do preço médio.";

            if ($totalSold > 50) {
                $explanation .= " Seus produtos já vendem bem — o ads deve potencializar!";
            }

            return [
                'suggested' => $suggested,
                'min' => $min,
                'max' => $max,
                'avg_price' => $avgPrice,
                'total_items' => $itemCount,
                'explanation' => $explanation,
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao sugerir orçamento de Ads', [
                'error' => $e->getMessage(),
            ]);
            return [
                'suggested' => 20.00,
                'min' => 10.00,
                'max' => 100.00,
                'explanation' => 'Sugestão padrão. Comece com R$ 20/dia e ajuste conforme os resultados.',
            ];
        }
    }

    /**
     * Cria campanha simplificada (wizard mode).
     * O usuário só precisa informar: produtos e orçamento.
     */
    public function createSimpleCampaign(array $params): array
    {
        $items = $params['items'] ?? [];
        $budget = (float)($params['budget'] ?? 20.00);
        $name = $params['name'] ?? $this->generateCampaignName($items);

        if (empty($items)) {
            return ['success' => false, 'error' => 'Selecione pelo menos um produto para anunciar.'];
        }

        if ($budget < 5) {
            return ['success' => false, 'error' => 'O orçamento mínimo é R$ 5,00 por dia.'];
        }

        // Converter lista de IDs para o formato esperado pela API do ML
        $formattedItems = array_map(function ($itemId) {
            return is_array($itemId) ? $itemId : ['id' => (string)$itemId];
        }, $items);

        $result = $this->adsService->createCampaign([
            'name' => $name,
            'type' => 'product_ad',
            'status' => 'paused',
            'daily_budget' => min($budget, 5000.00),
            'budget_type' => 'daily',
            'items' => $formattedItems,
            'bidding_strategy' => 'automatic',
        ]);

        if ($result['success']) {
            $result['message'] = "Campanha '{$name}' criada com sucesso! "
                . "Ela está pausada — ative quando estiver pronto.";
            $result['tips'] = [
                'A campanha começa PAUSADA por segurança. Ative quando quiser.',
                'O lance (bid) é automático — o ML otimiza para você.',
                'Acompanhe os resultados diariamente nos primeiros 7 dias.',
                'Se o ACOS passar de 15%, considere pausar para avaliar.',
            ];
        }

        return $result;
    }

    /**
     * Gera nome automático para campanha.
     */
    private function generateCampaignName(array $items): string
    {
        $count = count($items);
        $date = date('d/m');
        return "Campanha Automática ({$count} " . ($count === 1 ? 'produto' : 'produtos') . ") - {$date}";
    }

    /**
     * Diagnóstico simplificado para leigos.
     * Retorna array com semáforo (verde/amarelo/vermelho) e frases simples.
     */
    public function getDiagnostic(): array
    {
        $metrics = $this->adsService->getMetrics('last_30_days');
        $campaigns = $this->adsService->getCampaigns('all');
        $bonuses = $this->adsService->getAvailableBonuses();

        $investment = (float)($metrics['investment'] ?? 0);
        $revenue = (float)($metrics['revenue'] ?? 0);
        $acos = (float)($metrics['acos'] ?? 0);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $impressions = (int)($metrics['impressions'] ?? 0);
        $ctr = (float)($metrics['ctr'] ?? 0);

        $counts = $this->countCampaignsByStatus($campaigns['campaigns'] ?? []);
        $health = $this->calculateHealth($investment, $revenue, $acos, $clicks, $counts['active']);

        $summary = array_merge(
            $this->buildInvestmentSummary($investment, $revenue),
            $this->buildAcosSummary($investment, $acos),
            $this->buildClicksSummary($clicks, $ctr),
            $this->buildBonusSummary($bonuses),
            $this->buildCampaignStatusSummary($counts['active'], $counts['paused'])
        );

        return [
            'health' => $health,
            'summary' => $summary,
            'metrics_simple' => [
                'investiu' => $investment,
                'vendeu_com_ads' => $revenue,
                'lucro_estimado' => $revenue - $investment,
                'cliques' => $clicks,
                'vezes_visto' => $impressions,
                'campanhas_ativas' => $counts['active'],
                'campanhas_pausadas' => $counts['paused'],
                'bonus_disponiveis' => $bonuses['total'] ?? 0,
            ],
            'raw_metrics' => $metrics,
            'campaigns_count' => $counts['active'] + $counts['paused'],
        ];
    }

    private function countCampaignsByStatus(array $campaigns): array
    {
        $active = 0;
        $paused = 0;
        foreach ($campaigns as $c) {
            if (($c['status'] ?? '') === 'active') {
                $active++;
            } else {
                $paused++;
            }
        }
        return ['active' => $active, 'paused' => $paused];
    }

    private function buildInvestmentSummary(float $investment, float $revenue): array
    {
        if ($investment > 0 && $revenue > 0) {
            $lucro = $revenue - $investment;
            return [[
                'icon' => $lucro > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill',
                'color' => $lucro > 0 ? 'success' : 'danger',
                'text' => $lucro > 0
                    ? "Você investiu R$ " . number_format($investment, 2, ',', '.') . " e vendeu R$ " . number_format($revenue, 2, ',', '.') . " com anúncios. Lucro estimado: R$ " . number_format($lucro, 2, ',', '.') . "."
                    : "Atenção: você investiu R$ " . number_format($investment, 2, ',', '.') . " mas vendeu apenas R$ " . number_format($revenue, 2, ',', '.') . ". Os anúncios estão dando prejuízo.",
            ]];
        }

        if ($investment === 0.0) {
            return [[
                'icon' => 'bi-info-circle-fill',
                'color' => 'info',
                'text' => 'Você não investiu em anúncios nos últimos 30 dias. Comece com um orçamento pequeno para testar!',
            ]];
        }

        return [];
    }

    private function buildAcosSummary(float $investment, float $acos): array
    {
        if ($investment <= 0) {
            return [];
        }

        if ($acos > 20) {
            return [[
                'icon' => 'bi-exclamation-triangle-fill',
                'color' => 'danger',
                'text' => "Custo por venda alto ({$acos}%). Significa que " . round($acos) . "% do valor vendido foi gasto com anúncio. Ideal: abaixo de 15%.",
            ]];
        }

        if ($acos > 10) {
            return [[
                'icon' => 'bi-dash-circle-fill',
                'color' => 'warning',
                'text' => "Custo por venda em " . number_format($acos, 1, ',', '.') . "%. Está aceitável, mas pode melhorar. Fique de olho!",
            ]];
        }

        return [[
            'icon' => 'bi-check-circle-fill',
            'color' => 'success',
            'text' => "Excelente! Custo por venda em apenas " . number_format($acos, 1, ',', '.') . "%. Seus anúncios estão eficientes!",
        ]];
    }

    private function buildClicksSummary(int $clicks, float $ctr): array
    {
        if ($clicks > 0 && $ctr < 0.5) {
            return [[
                'icon' => 'bi-hand-index',
                'color' => 'warning',
                'text' => 'Seus anúncios estão aparecendo, mas poucas pessoas clicam. Dica: melhore as fotos e títulos dos produtos.',
            ]];
        }
        return [];
    }

    private function buildBonusSummary(array $bonuses): array
    {
        $totalBonuses = $bonuses['total'] ?? 0;
        if ($totalBonuses > 0) {
            $bonusAmount = array_sum(array_column($bonuses['bonuses'] ?? [], 'amount'));
            return [[
                'icon' => 'bi-gift-fill',
                'color' => 'success',
                'text' => "Você tem R$ " . number_format($bonusAmount, 2, ',', '.') . " em bônus do Mercado Livre para usar em anúncios! Não deixe expirar!",
            ]];
        }
        return [];
    }

    private function buildCampaignStatusSummary(int $activeCampaigns, int $pausedCampaigns): array
    {
        if ($activeCampaigns === 0 && $pausedCampaigns > 0) {
            $label = $pausedCampaigns === 1 ? 'campanha pausada' : 'campanhas pausadas';
            return [[
                'icon' => 'bi-pause-circle-fill',
                'color' => 'warning',
                'text' => "Você tem {$pausedCampaigns} {$label}. Reative para voltar a aparecer nos resultados.",
            ]];
        }
        return [];
    }

    /**
     * Calcula saúde geral do Ads (verde/amarelo/vermelho).
     */
    private function calculateHealth(float $investment, float $revenue, float $acos, int $clicks, int $activeCampaigns): array
    {
        if ($investment === 0.0 && $activeCampaigns === 0) {
            return [
                'status' => 'neutral',
                'color' => 'secondary',
                'label' => 'Sem Anúncios',
                'emoji' => '➖',
                'message' => 'Você ainda não está anunciando. Crie sua primeira campanha!',
            ];
        }

        $score = 0;
        if ($revenue > $investment) {
            $score += 3;
        }
        if ($acos > 0 && $acos < 15) {
            $score += 2;
        }
        if ($clicks > 100) {
            $score += 1;
        }
        if ($activeCampaigns > 0) {
            $score += 1;
        }

        if ($acos > 25) {
            $score -= 3;
        }
        if ($investment > 0 && $revenue === 0.0) {
            $score -= 4;
        }

        if ($score >= 4) {
            return [
                'status' => 'good',
                'color' => 'success',
                'label' => 'Tudo Certo!',
                'emoji' => '🟢',
                'message' => 'Seus anúncios estão funcionando bem. Continue assim!',
            ];
        }
        if ($score >= 1) {
            return [
                'status' => 'attention',
                'color' => 'warning',
                'label' => 'Pode Melhorar',
                'emoji' => '🟡',
                'message' => 'Seus anúncios funcionam, mas têm espaço para melhorar.',
            ];
        }

        return [
            'status' => 'critical',
            'color' => 'danger',
            'label' => 'Atenção!',
            'emoji' => '🔴',
            'message' => 'Seus anúncios precisam de ajustes urgentes para não dar prejuízo.',
        ];
    }

    /**
     * Ações rápidas com 1 clique (para leigos).
     */
    public function executeQuickAction(string $action): array
    {
        switch ($action) {
            case 'pause_unprofitable':
                return $this->pauseUnprofitableCampaigns();

            case 'activate_all':
                return $this->activateAllCampaigns();

            case 'optimize':
                return $this->optimizeCampaigns();

            default:
                return ['success' => false, 'error' => 'Ação não reconhecida.'];
        }
    }

    /**
     * Pausa campanhas com ACOS > 20% (gastando demais).
     */
    private function pauseUnprofitableCampaigns(): array
    {
        $campaigns = $this->adsService->getCampaigns('active');
        $paused = 0;
        $errors = [];

        foreach ($campaigns['campaigns'] ?? [] as $campaign) {
            $campaignId = $campaign['id'] ?? '';
            if (empty($campaignId)) {
                continue;
            }

            $report = $this->adsService->getCampaignReport(
                $campaignId,
                date('Y-m-d', strtotime('-30 days')),
                date('Y-m-d')
            );

            $acos = (float)($report['calculated_metrics']['acos'] ?? 0);
            if ($acos > 20) {
                $result = $this->adsService->updateCampaignStatus($campaignId, 'paused');
                if ($result['success'] ?? false) {
                    $paused++;
                } else {
                    $errors[] = $campaignId;
                }
            }
        }

        $message = $paused > 0
            ? "Pronto! {$paused} " . ($paused === 1 ? 'campanha foi pausada' : 'campanhas foram pausadas') . " por estar gastando demais (ACOS > 20%)."
            : 'Nenhuma campanha precisou ser pausada — todas estão com custos aceitáveis!';

        return [
            'success' => true,
            'paused' => $paused,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Ativa todas as campanhas pausadas.
     */
    private function activateAllCampaigns(): array
    {
        $campaigns = $this->adsService->getCampaigns('paused');
        $activated = 0;
        $errors = [];

        foreach ($campaigns['campaigns'] ?? [] as $campaign) {
            $campaignId = $campaign['id'] ?? '';
            if (empty($campaignId)) {
                continue;
            }
            $result = $this->adsService->updateCampaignStatus($campaignId, 'active');
            if ($result['success'] ?? false) {
                $activated++;
            } else {
                $errors[] = $campaignId;
            }
        }

        $message = $activated > 0
            ? "{$activated} " . ($activated === 1 ? 'campanha reativada' : 'campanhas reativadas') . " com sucesso!"
            : 'Nenhuma campanha pausada encontrada para ativar.';

        return [
            'success' => true,
            'activated' => $activated,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Otimização automática usando MLAdsAdvancedService.
     */
    private function optimizeCampaigns(): array
    {
        try {
            $advancedService = new MercadoLivre\MLAdsAdvancedService($this->accountId);
            $result = $advancedService->optimizeCampaigns();

            if ($result['success'] ?? false) {
                $count = $result['optimized_campaigns'] ?? 0;
                return [
                    'success' => true,
                    'message' => "Otimização concluída! {$count} " . ($count === 1 ? 'campanha ajustada' : 'campanhas ajustadas') . " automaticamente.",
                    'details' => $result['summary'] ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => 'Não foi possível otimizar as campanhas agora. Tente novamente em alguns minutos.',
                'error' => $result['error'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao otimizar campanhas. Tente novamente.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Glossário de termos para leigos.
     */
    public function getGlossary(): array
    {
        return [
            'ACOS' => [
                'nome' => 'Custo por Venda',
                'descricao' => 'Porcentagem do valor da venda que foi gasto com anúncio. Quanto menor, melhor.',
                'exemplo' => 'Se você vendeu R$ 100 e gastou R$ 10 em ads, o ACOS é 10%.',
                'meta' => 'Abaixo de 15% é bom. Acima de 20% liga o alerta.',
            ],
            'ROAS' => [
                'nome' => 'Retorno sobre Investimento',
                'descricao' => 'Quantos reais você vendeu para cada R$ 1 investido em anúncio.',
                'exemplo' => 'ROAS de 5x = para cada R$ 1 gasto, vendeu R$ 5.',
                'meta' => 'Acima de 5x é excelente. Abaixo de 2x precisa de atenção.',
            ],
            'CPC' => [
                'nome' => 'Custo por Clique',
                'descricao' => 'Quanto você paga cada vez que alguém clica no seu anúncio.',
                'exemplo' => 'CPC de R$ 0,50 = cada clique custa 50 centavos.',
                'meta' => 'Depende do produto. Produtos caros toleram CPC maior.',
            ],
            'CTR' => [
                'nome' => 'Taxa de Cliques',
                'descricao' => 'De todas as vezes que seu anúncio apareceu, quantas pessoas clicaram.',
                'exemplo' => 'CTR de 2% = de 100 vezes que apareceu, 2 clicaram.',
                'meta' => 'Acima de 1% é bom. Abaixo de 0,5% melhore fotos e título.',
            ],
            'Impressões' => [
                'nome' => 'Vezes que Apareceu',
                'descricao' => 'Quantas vezes seu anúncio foi mostrado para compradores.',
                'exemplo' => '1.000 impressões = seu anúncio apareceu 1.000 vezes.',
                'meta' => 'Quanto mais, melhor — significa que o ML está mostrando seu produto.',
            ],
            'Bid' => [
                'nome' => 'Lance',
                'descricao' => 'Quanto você aceita pagar por clique. O lance automático é recomendado para iniciantes.',
                'exemplo' => 'O ML compete com outros vendedores pelo espaço do anúncio.',
                'meta' => 'Modo automático é ideal para quem está começando.',
            ],
        ];
    }
}
