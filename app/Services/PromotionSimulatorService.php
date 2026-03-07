<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * PromotionSimulatorService
 * 
 * Serviço para simulação de promoções e análise de impacto em margens.
 * Permite calcular cenários de desconto e prever impacto nas vendas.
 * 
 * Funcionalidades:
 * - Simulação de descontos com cálculo de margem
 * - Cenários múltiplos de promoção
 * - Projeção de vendas e receita
 * - Desconto máximo seguro
 * - Integração com Central de Ofertas do ML
 * - Histórico de simulações
 * 
 * @package App\Services
 */
class PromotionSimulatorService
{
    private PDO $db;
    private int $accountId;
    private MarginCalculatorService $marginService;
    private MercadoLivreClient $mlClient;

    // Fatores de conversão estimados por desconto
    private const CONVERSION_FACTORS = [
        5  => 1.10,  // 5% desconto = +10% vendas estimadas
        10 => 1.25,  // 10% desconto = +25% vendas
        15 => 1.40,  // 15% desconto = +40% vendas
        20 => 1.60,  // 20% desconto = +60% vendas
        25 => 1.80,  // 25% desconto = +80% vendas
        30 => 2.00,  // 30% desconto = +100% vendas
        40 => 2.30,  // 40% desconto = +130% vendas
        50 => 2.50,  // 50% desconto = +150% vendas
    ];

    // Margem mínima considerada segura
    private const MARGEM_MINIMA_SEGURA = 5.0;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->marginService = new MarginCalculatorService($accountId);
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Simula uma promoção completa para um item
     * 
     * @param string $itemId ID do anúncio no ML
     * @param float $descontoPercent Percentual de desconto (0-50)
     * @param array $options Opções adicionais
     * @return array Resultado da simulação
     */
    public function simularPromocao(string $itemId, float $descontoPercent, array $options = []): array
    {
        // Validar desconto
        if ($descontoPercent < 0 || $descontoPercent > 50) {
            return $this->errorResponse('Desconto deve estar entre 0 e 50%');
        }

        // Buscar dados do item
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return $this->errorResponse('Item não encontrado no Mercado Livre');
        }

        $precoOriginal = (float)$item['price'];
        $titulo = $item['title'] ?? '';

        // Buscar custos cadastrados
        $custos = $this->marginService->getCustosProduto($itemId);
        if (!$custos) {
            $custos = $options['custos'] ?? [
                'custo_producao' => 0,
                'taxa_comissao_ml' => 16,
                'taxa_imposto' => 9,
                'acos_medio' => 0
            ];
        }

        // Calcular preço promocional
        $precoPromocional = $precoOriginal * (1 - $descontoPercent / 100);

        // Calcular margens
        $margemOriginal = $this->marginService->calcularMargem($precoOriginal, $custos);
        $margemPromocao = $this->marginService->calcularMargem($precoPromocional, $custos);

        // Calcular desconto máximo seguro
        $descontoMaximoSeguro = $this->calcularDescontoMaximo($precoOriginal, $custos);

        // Verificar viabilidade
        $margemPromocaoValor = $margemPromocao['margem_real'] ?? 0;
        $viavel = $margemPromocaoValor >= self::MARGEM_MINIMA_SEGURA;

        // Calcular alerta
        $alerta = null;
        if ($margemPromocaoValor < 0) {
            $alerta = '⚠️ PREJUÍZO: Margem negativa neste desconto!';
        } elseif ($margemPromocaoValor < 5) {
            $alerta = '🔴 RISCO: Margem abaixo de 5%. Considere reduzir o desconto.';
        } elseif ($margemPromocaoValor < 10) {
            $alerta = '🟡 ATENÇÃO: Margem entre 5-10%. Monitore vendas.';
        }

        // Projeções de vendas
        $projecoes = $this->projetarVendas($itemId, $descontoPercent, $precoPromocional, $margemPromocaoValor);

        // Montar resultado
        $resultado = [
            'success' => true,
            'item_id' => $itemId,
            'titulo' => $titulo,
            'preco_original' => round($precoOriginal, 2),
            'desconto_percentual' => $descontoPercent,
            'preco_promocional' => round($precoPromocional, 2),
            'economia_cliente' => round($precoOriginal - $precoPromocional, 2),
            'breakdown' => [
                'custo_total' => $margemPromocao['breakdown']['custo_total'] ?? 0,
                'custos_fixos' => $margemPromocao['breakdown']['custos_fixos'] ?? [],
                'custos_variaveis' => $margemPromocao['breakdown']['custos_variaveis'] ?? []
            ],
            'margem' => [
                'original' => round($margemOriginal['margem_real'] ?? 0, 2),
                'promocao' => round($margemPromocaoValor, 2),
                'diferenca' => round(($margemOriginal['margem_real'] ?? 0) - $margemPromocaoValor, 2)
            ],
            'lucro' => [
                'original' => round($margemOriginal['lucro_unitario'] ?? 0, 2),
                'promocao' => round($margemPromocao['lucro_unitario'] ?? 0, 2),
                'diferenca' => round(($margemOriginal['lucro_unitario'] ?? 0) - ($margemPromocao['lucro_unitario'] ?? 0), 2)
            ],
            'desconto_maximo_seguro' => $descontoMaximoSeguro,
            'viavel' => $viavel,
            'alerta' => $alerta,
            'projecoes' => $projecoes,
            'cenarios' => $this->gerarCenarios($precoOriginal, $custos)
        ];

        // Salvar simulação se solicitado
        if ($options['salvar'] ?? false) {
            $this->salvarSimulacao($resultado);
        }

        return $resultado;
    }

    /**
     * Gera cenários de desconto (5%, 10%, 15%, 20%, 25%, 30%)
     */
    public function gerarCenarios(float $precoOriginal, array $custos): array
    {
        $cenarios = [];
        $descontos = [5, 10, 15, 20, 25, 30, 40, 50];

        foreach ($descontos as $desc) {
            $precoDesc = $precoOriginal * (1 - $desc / 100);
            $margem = $this->marginService->calcularMargem($precoDesc, $custos);
            $margemReal = $margem['margem_real'] ?? 0;

            $cenarios[] = [
                'desconto' => $desc,
                'preco' => round($precoDesc, 2),
                'economia' => round($precoOriginal - $precoDesc, 2),
                'margem' => round($margemReal, 2),
                'lucro' => round($margem['lucro_unitario'] ?? 0, 2),
                'viavel' => $margemReal >= self::MARGEM_MINIMA_SEGURA,
                'indicador' => $this->getIndicador($margemReal),
                'aumento_vendas_estimado' => $this->getAumentoVendasEstimado($desc)
            ];
        }

        return $cenarios;
    }

    /**
     * Calcula desconto máximo mantendo margem mínima de 5%
     */
    public function calcularDescontoMaximo(float $precoOriginal, array $custos, float $margemMinima = 5.0): float
    {
        // Buscar preço mínimo para margem alvo
        $resultado = $this->marginService->calcularPrecoMinimo($custos, $margemMinima);

        if (!$resultado['success']) {
            return 0;
        }

        $precoMinimo = $resultado['preco_minimo'];

        if ($precoMinimo >= $precoOriginal) {
            return 0;
        }

        $descontoMaximo = (($precoOriginal - $precoMinimo) / $precoOriginal) * 100;

        return round(min(50, max(0, $descontoMaximo)), 2);
    }

    /**
     * Projeta vendas baseado no desconto
     */
    private function projetarVendas(string $itemId, float $desconto, float $precoPromocional, float $margem): array
    {
        // Buscar vendas históricas do item
        $vendasBase = $this->getVendasMedias($itemId);
        
        // Calcular fator de conversão
        $fator = $this->getConversionFactor($desconto);
        
        // Projeções
        $vendasEstimadas = ceil($vendasBase * $fator);
        $receitaProjetada = $vendasEstimadas * $precoPromocional;
        $lucroProjetado = $vendasEstimadas * ($precoPromocional * ($margem / 100));

        return [
            'vendas_base_semanal' => $vendasBase,
            'fator_conversao' => $fator,
            'vendas_estimadas_semanal' => $vendasEstimadas,
            'aumento_percentual' => round(($fator - 1) * 100, 0),
            'receita_projetada_semanal' => round($receitaProjetada, 2),
            'lucro_projetado_semanal' => round($lucroProjetado, 2),
            'observacao' => $this->getObservacaoProjecao($desconto, $margem)
        ];
    }

    /**
     * Busca média de vendas semanais de um item
     */
    private function getVendasMedias(string $itemId): int
    {
        try {
            // Buscar do histórico de pedidos (últimos 30 dias)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) / 4 as media_semanal
                FROM ml_orders o
                WHERE o.ml_account_id = :account_id
                AND JSON_SEARCH(o.order_data, 'one', :item_id, NULL, '$.order_items[*].item.id') IS NOT NULL
                AND o.date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return max(1, (int)($result['media_semanal'] ?? 1));
        } catch (\Throwable $e) {
            // Padrão: 5 vendas/semana se não conseguir buscar
            return 5;
        }
    }

    /**
     * Retorna fator de conversão estimado por desconto
     */
    private function getConversionFactor(float $desconto): float
    {
        $descontoArredondado = (int)round($desconto / 5) * 5;
        $descontoArredondado = max(5, min(50, $descontoArredondado));

        return self::CONVERSION_FACTORS[$descontoArredondado] ?? 1.0;
    }

    /**
     * Retorna aumento de vendas estimado
     */
    private function getAumentoVendasEstimado(float $desconto): string
    {
        $fator = $this->getConversionFactor($desconto);
        return '+' . round(($fator - 1) * 100, 0) . '% vendas';
    }

    /**
     * Retorna indicador visual
     */
    private function getIndicador(float $margem): string
    {
        if ($margem >= 20) return '🟢';
        if ($margem >= 10) return '🟡';
        if ($margem >= 5) return '🟠';
        return '🔴';
    }

    /**
     * Gera observação para projeção
     */
    private function getObservacaoProjecao(float $desconto, float $margem): string
    {
        if ($margem < 0) {
            return '❌ Promoção inviável: margem negativa';
        }
        if ($margem < 5) {
            return '⚠️ Risco alto: margem muito baixa para esta promoção';
        }
        if ($desconto >= 30) {
            return '💡 Desconto agressivo: ideal para liquidação de estoque';
        }
        if ($desconto >= 20) {
            return '🔥 Bom para campanhas especiais (Black Friday, etc)';
        }
        if ($desconto >= 10) {
            return '✅ Desconto competitivo com margem saudável';
        }
        return '💰 Promoção conservadora com boa margem';
    }

    /**
     * Simula participação na Central de Ofertas do ML
     * 
     * @param string $itemId ID do item
     * @param string $tipoOferta Tipo: 'deal_of_day', 'lightning_deal', 'best_seller'
     * @return array Simulação de participação
     */
    public function simularCentralOfertas(string $itemId, string $tipoOferta = 'deal_of_day'): array
    {
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return $this->errorResponse('Item não encontrado');
        }

        $precoOriginal = (float)$item['price'];
        $custos = $this->marginService->getCustosProduto($itemId);

        // Requisitos mínimos por tipo de oferta
        $requisitos = [
            'deal_of_day' => ['desconto_minimo' => 30, 'estoque_minimo' => 50, 'vendas_minimas' => 10],
            'lightning_deal' => ['desconto_minimo' => 20, 'estoque_minimo' => 20, 'vendas_minimas' => 5],
            'best_seller' => ['desconto_minimo' => 10, 'estoque_minimo' => 100, 'vendas_minimas' => 20]
        ];

        $req = $requisitos[$tipoOferta] ?? $requisitos['lightning_deal'];

        // Verificar elegibilidade
        $estoque = (int)($item['available_quantity'] ?? 0);
        $elegivel = $estoque >= $req['estoque_minimo'];

        // Simular com desconto mínimo necessário
        $simulacao = $this->simularPromocao($itemId, $req['desconto_minimo'], ['custos' => $custos]);

        return [
            'success' => true,
            'tipo_oferta' => $tipoOferta,
            'requisitos' => $req,
            'elegibilidade' => [
                'elegivel' => $elegivel,
                'estoque_atual' => $estoque,
                'estoque_necessario' => $req['estoque_minimo'],
                'desconto_necessario' => $req['desconto_minimo']
            ],
            'simulacao' => $simulacao,
            'recomendacao' => $this->getRecomendacaoOferta($simulacao, $elegivel, $tipoOferta)
        ];
    }

    /**
     * Gera recomendação para participar de ofertas
     */
    private function getRecomendacaoOferta(array $simulacao, bool $elegivel, string $tipo): string
    {
        if (!$elegivel) {
            return '❌ Estoque insuficiente para participar. Considere reabastecer.';
        }

        if (!$simulacao['viavel']) {
            return '⚠️ Margem ficaria abaixo do mínimo seguro. Considere reduzir custos ou não participar.';
        }

        $margem = $simulacao['margem']['promocao'] ?? 0;

        if ($margem >= 15) {
            return '✅ Excelente oportunidade! Margem saudável mesmo com desconto.';
        }
        if ($margem >= 10) {
            return '🟢 Participação recomendada. Bom equilíbrio entre volume e margem.';
        }
        if ($margem >= 5) {
            return '🟡 Participação viável, mas monitore o desempenho de perto.';
        }

        return '🔴 Participação arriscada. Avalie se o aumento de vendas compensa.';
    }

    /**
     * Salva simulação no banco de dados
     */
    private function salvarSimulacao(array $simulacao): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO promotion_simulations (
                    account_id, item_id, preco_original, titulo,
                    desconto_percentual, preco_promocional,
                    custo_total, margem_promocao, lucro_unitario_promocao,
                    desconto_maximo_seguro, viavel, alerta,
                    vendas_estimadas_aumento, receita_projetada, lucro_projetado
                ) VALUES (
                    :account_id, :item_id, :preco_original, :titulo,
                    :desconto_percentual, :preco_promocional,
                    :custo_total, :margem_promocao, :lucro_unitario_promocao,
                    :desconto_maximo_seguro, :viavel, :alerta,
                    :vendas_estimadas_aumento, :receita_projetada, :lucro_projetado
                )
            ");

            return $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $simulacao['item_id'],
                'preco_original' => $simulacao['preco_original'],
                'titulo' => $simulacao['titulo'] ?? null,
                'desconto_percentual' => $simulacao['desconto_percentual'],
                'preco_promocional' => $simulacao['preco_promocional'],
                'custo_total' => $simulacao['breakdown']['custo_total'] ?? null,
                'margem_promocao' => $simulacao['margem']['promocao'] ?? null,
                'lucro_unitario_promocao' => $simulacao['lucro']['promocao'] ?? null,
                'desconto_maximo_seguro' => $simulacao['desconto_maximo_seguro'],
                'viavel' => $simulacao['viavel'] ? 1 : 0,
                'alerta' => $simulacao['alerta'],
                'vendas_estimadas_aumento' => $simulacao['projecoes']['aumento_percentual'] ?? null,
                'receita_projetada' => $simulacao['projecoes']['receita_projetada_semanal'] ?? null,
                'lucro_projetado' => $simulacao['projecoes']['lucro_projetado_semanal'] ?? null
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Busca histórico de simulações
     */
    public function getHistoricoSimulacoes(string $itemId = null, int $limit = 20): array
    {
        try {
            $sql = "SELECT * FROM promotion_simulations WHERE account_id = :account_id";
            $params = ['account_id' => $this->accountId];

            if ($itemId) {
                $sql .= " AND item_id = :item_id";
                $params['item_id'] = $itemId;
            }

            $sql .= " ORDER BY criado_em DESC LIMIT {$limit}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Aplica promoção no Mercado Livre
     * 
     * @param string $itemId ID do item
     * @param float $precoPromocional Novo preço promocional
     * @param array $options Opções (data_fim, etc)
     * @return array Resultado da aplicação
     */
    public function aplicarPromocao(string $itemId, float $precoPromocional, array $options = []): array
    {
        // Buscar preço atual
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return $this->errorResponse('Item não encontrado');
        }

        $precoAtual = (float)$item['price'];

        // Verificar se é realmente um desconto
        if ($precoPromocional >= $precoAtual) {
            return $this->errorResponse('Preço promocional deve ser menor que o atual');
        }

        // Atualizar preço no ML
        $response = $this->mlClient->put("/items/{$itemId}", [
            'price' => $precoPromocional
        ]);

        if (isset($response['error'])) {
            return $this->errorResponse('Erro ao aplicar promoção: ' . ($response['message'] ?? 'Erro desconhecido'));
        }

        // Registrar no histórico
        $desconto = round((1 - $precoPromocional / $precoAtual) * 100, 2);
        $this->marginService->registrarAlteracaoPreco($itemId, $precoAtual, $precoPromocional, [
            'origem' => 'promocao',
            'motivo' => $options['motivo'] ?? "Promoção: {$desconto}% off",
            'estrategia' => 'promotional'
        ]);

        // Marcar simulação como aplicada (se existir)
        $this->marcarSimulacaoAplicada($itemId, $desconto);

        return [
            'success' => true,
            'item_id' => $itemId,
            'preco_anterior' => $precoAtual,
            'preco_promocional' => $precoPromocional,
            'desconto_aplicado' => $desconto,
            'message' => "Promoção de {$desconto}% aplicada com sucesso!"
        ];
    }

    /**
     * Marca última simulação como aplicada
     */
    private function marcarSimulacaoAplicada(string $itemId, float $desconto): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE promotion_simulations 
                SET aplicada = 1, aplicada_em = NOW()
                WHERE account_id = :account_id 
                AND item_id = :item_id
                AND ABS(desconto_percentual - :desconto) < 1
                ORDER BY criado_em DESC
                LIMIT 1
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'desconto' => $desconto
            ]);
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Resposta de erro padronizada
     */
    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}
