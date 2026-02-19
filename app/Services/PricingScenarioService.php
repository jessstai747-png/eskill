<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * PricingScenarioService
 * 
 * Serviço para análise e gerenciamento de cenários de precificação.
 * Permite criar regras automáticas, comparar estratégias e otimizar preços.
 * 
 * Funcionalidades:
 * - Criação de cenários "e se" (what-if)
 * - Comparação de estratégias de preço
 * - Regras de precificação automática
 * - Análise de demanda e elasticidade
 * - Otimização baseada em concorrência
 * 
 * @package App\Services
 */
class PricingScenarioService
{
    private PDO $db;
    private int $accountId;
    private MarginCalculatorService $marginService;
    private PricingStrategyService $strategyService;
    private MercadoLivreClient $mlClient;

    // Estratégias disponíveis
    public const ESTRATEGIA_AGRESSIVO = 'agressivo';
    public const ESTRATEGIA_COMPETITIVO = 'competitivo';
    public const ESTRATEGIA_PREMIUM = 'premium';
    public const ESTRATEGIA_VALOR = 'valor';
    public const ESTRATEGIA_LIQUIDACAO = 'liquidacao';

    // Descrições das estratégias
    private const ESTRATEGIAS_DESC = [
        self::ESTRATEGIA_AGRESSIVO => [
            'nome' => 'Agressivo',
            'descricao' => 'Preço ligeiramente abaixo do menor concorrente',
            'margem_alvo' => 5,
            'fator' => 0.98
        ],
        self::ESTRATEGIA_COMPETITIVO => [
            'nome' => 'Competitivo',
            'descricao' => 'Preço alinhado com a mediana do mercado',
            'margem_alvo' => 15,
            'fator' => 1.0
        ],
        self::ESTRATEGIA_PREMIUM => [
            'nome' => 'Premium',
            'descricao' => 'Preço acima da média para posicionamento superior',
            'margem_alvo' => 25,
            'fator' => 1.10
        ],
        self::ESTRATEGIA_VALOR => [
            'nome' => 'Valor',
            'descricao' => 'Pequeno desconto abaixo da média',
            'margem_alvo' => 12,
            'fator' => 0.95
        ],
        self::ESTRATEGIA_LIQUIDACAO => [
            'nome' => 'Liquidação',
            'descricao' => 'Desconto agressivo para girar estoque',
            'margem_alvo' => 0,
            'fator' => 0.70
        ]
    ];

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->marginService = new MarginCalculatorService($accountId);
        $this->strategyService = new PricingStrategyService($accountId);
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Compara múltiplas estratégias para um item
     * 
     * @param string $itemId ID do item no ML
     * @return array Comparação de estratégias
     */
    public function compararEstrategias(string $itemId): array
    {
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return $this->errorResponse('Item não encontrado');
        }

        $precoAtual = (float)$item['price'];
        $categoryId = $item['category_id'];

        // Buscar custos
        $custos = $this->marginService->getCustosProduto($itemId);
        if (!$custos) {
            return $this->errorResponse('Custos não cadastrados para este item. Configure os custos primeiro.');
        }

        // Análise de concorrência
        $concorrencia = $this->strategyService->analyzeCompetitorPrices($categoryId);
        $stats = $concorrencia['price_stats'] ?? [];

        if (empty($stats['count'])) {
            return $this->errorResponse('Sem dados de concorrência disponíveis');
        }

        // Calcular margem atual
        $margemAtual = $this->marginService->calcularMargem($precoAtual, $custos);

        // Comparar estratégias
        $comparacao = [];
        foreach (self::ESTRATEGIAS_DESC as $key => $estrategia) {
            $precoSugerido = $this->calcularPrecoEstrategia($key, $stats, $custos);
            $margemCenario = $this->marginService->calcularMargem($precoSugerido, $custos);
            
            $impactoRanking = $this->marginService->analisarImpactoRanking($precoAtual, $precoSugerido);
            
            $comparacao[$key] = [
                'estrategia' => $key,
                'nome' => $estrategia['nome'],
                'descricao' => $estrategia['descricao'],
                'preco_sugerido' => round($precoSugerido, 2),
                'variacao_atual' => round((($precoSugerido - $precoAtual) / $precoAtual) * 100, 2),
                'margem_estimada' => round($margemCenario['margem_real'] ?? 0, 2),
                'lucro_unitario' => round($margemCenario['lucro_unitario'] ?? 0, 2),
                'impacto_ranking' => $impactoRanking['alerta'],
                'recomendado' => $this->isRecomendado($margemCenario, $impactoRanking),
                'posicao_mercado' => $this->getPosicaoMercado($precoSugerido, $stats)
            ];
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'titulo' => $item['title'],
            'preco_atual' => $precoAtual,
            'margem_atual' => round($margemAtual['margem_real'] ?? 0, 2),
            'concorrencia' => [
                'minimo' => $stats['min'] ?? 0,
                'maximo' => $stats['max'] ?? 0,
                'media' => $stats['average'] ?? 0,
                'mediana' => $stats['median'] ?? 0,
                'quantidade' => $stats['count'] ?? 0
            ],
            'estrategias' => $comparacao,
            'recomendacao' => $this->getRecomendacao($comparacao, $margemAtual)
        ];
    }

    /**
     * Calcula preço baseado em estratégia
     */
    private function calcularPrecoEstrategia(string $estrategia, array $stats, array $custos): float
    {
        $min = $stats['min'] ?? 0;
        $media = $stats['average'] ?? 0;
        $mediana = $stats['median'] ?? 0;

        // Preço mínimo baseado no custo
        $precoMinimoCusto = $this->marginService->calcularPrecoMinimo($custos, 5);
        $precoMinimo = $precoMinimoCusto['preco_minimo'] ?? 0;

        $precoBase = match ($estrategia) {
            self::ESTRATEGIA_AGRESSIVO => $min * 0.98,
            self::ESTRATEGIA_COMPETITIVO => $mediana ?: $media,
            self::ESTRATEGIA_PREMIUM => ($media ?: $mediana) * 1.10,
            self::ESTRATEGIA_VALOR => ($media ?: $mediana) * 0.95,
            self::ESTRATEGIA_LIQUIDACAO => ($media ?: $mediana) * 0.70,
            default => $mediana
        };

        // Garantir que não seja abaixo do preço mínimo viável
        return max($precoBase, $precoMinimo);
    }

    /**
     * Verifica se estratégia é recomendada
     */
    private function isRecomendado(array $margem, array $impacto): bool
    {
        $margemReal = $margem['margem_real'] ?? 0;
        $alerta = $impacto['alerta'] ?? 'verde';

        return $margemReal >= 10 && in_array($alerta, ['verde', 'amarelo']);
    }

    /**
     * Retorna posição no mercado
     */
    private function getPosicaoMercado(float $preco, array $stats): string
    {
        $min = $stats['min'] ?? 0;
        $max = $stats['max'] ?? 0;
        $media = $stats['average'] ?? 0;

        if ($preco <= $min) return 'mais_barato';
        if ($preco <= $media * 0.9) return 'abaixo_media';
        if ($preco <= $media * 1.1) return 'na_media';
        if ($preco <= $max) return 'acima_media';
        return 'mais_caro';
    }

    /**
     * Gera recomendação principal
     */
    private function getRecomendacao(array $comparacao, array $margemAtual): array
    {
        $margemAtualVal = $margemAtual['margem_real'] ?? 0;

        // Encontrar melhor estratégia
        $melhor = null;
        $melhorScore = -999;

        foreach ($comparacao as $key => $estrategia) {
            // Score: margem + (não prejudica ranking * 10)
            $score = ($estrategia['margem_estimada'] ?? 0);
            if ($estrategia['impacto_ranking'] === 'verde') $score += 10;
            if ($estrategia['impacto_ranking'] === 'amarelo') $score += 5;
            if ($estrategia['recomendado']) $score += 15;

            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $estrategia;
            }
        }

        $acao = 'manter';
        if ($melhor && $melhor['variacao_atual'] > 2) $acao = 'aumentar';
        if ($melhor && $melhor['variacao_atual'] < -2) $acao = 'reduzir';

        return [
            'estrategia_recomendada' => $melhor['estrategia'] ?? 'competitivo',
            'preco_recomendado' => $melhor['preco_sugerido'] ?? 0,
            'acao' => $acao,
            'motivo' => $this->getMotivoRecomendacao($melhor, $margemAtualVal, $acao)
        ];
    }

    /**
     * Gera motivo da recomendação
     */
    private function getMotivoRecomendacao(?array $estrategia, float $margemAtual, string $acao): string
    {
        if (!$estrategia) {
            return 'Mantenha o preço atual até obter mais dados de mercado.';
        }

        $margem = $estrategia['margem_estimada'] ?? 0;
        
        if ($acao === 'aumentar') {
            return "Preço abaixo do ideal. A estratégia {$estrategia['nome']} oferece margem de {$margem}% com baixo risco de ranking.";
        }
        
        if ($acao === 'reduzir') {
            return "Preço acima do mercado. Reduzir para {$estrategia['preco_sugerido']} melhora competitividade mantendo {$margem}% de margem.";
        }

        return "Preço atual está bem posicionado. Margem de {$margemAtual}% é adequada para o mercado.";
    }

    /**
     * Cria cenário "what-if" para análise
     * 
     * @param string $itemId ID do item
     * @param array $parametros Parâmetros do cenário
     * @return array Resultado do cenário
     */
    public function criarCenario(string $itemId, array $parametros): array
    {
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return $this->errorResponse('Item não encontrado');
        }

        $custos = $this->marginService->getCustosProduto($itemId) ?? [];

        // Aplicar modificações do cenário
        $custosModificados = array_merge($custos, $parametros['custos'] ?? []);
        $precoNovo = $parametros['preco'] ?? $item['price'];

        // Calcular margem com novos parâmetros
        $margemCenario = $this->marginService->calcularMargem($precoNovo, $custosModificados);

        // Análise de impacto
        $impacto = $this->marginService->analisarImpactoRanking($item['price'], $precoNovo);

        // Projeção de vendas (usando elasticidade simplificada)
        $elasticidade = $parametros['elasticidade'] ?? -1.5;
        $variacaoPreco = (($precoNovo - $item['price']) / $item['price']) * 100;
        $variacaoVendas = $variacaoPreco * $elasticidade;

        return [
            'success' => true,
            'item_id' => $itemId,
            'titulo' => $item['title'],
            'cenario' => [
                'preco_atual' => $item['price'],
                'preco_cenario' => round($precoNovo, 2),
                'variacao_preco' => round($variacaoPreco, 2),
                'custos_modificados' => array_keys($parametros['custos'] ?? [])
            ],
            'resultado' => [
                'margem_estimada' => round($margemCenario['margem_real'] ?? 0, 2),
                'lucro_unitario' => round($margemCenario['lucro_unitario'] ?? 0, 2),
                'impacto_ranking' => $impacto['alerta'],
                'variacao_vendas_estimada' => round($variacaoVendas, 2)
            ],
            'breakdown' => $margemCenario['breakdown'] ?? [],
            'viavel' => ($margemCenario['margem_real'] ?? 0) >= 5
        ];
    }

    /**
     * Cria regra de precificação automática
     * 
     * @param array $regra Dados da regra
     * @return array Resultado da criação
     */
    public function criarRegraAutomatica(array $regra): array
    {
        $nome = $regra['nome'] ?? null;
        $estrategia = $regra['estrategia'] ?? self::ESTRATEGIA_COMPETITIVO;

        if (!$nome) {
            return $this->errorResponse('Nome da regra é obrigatório');
        }

        if (!isset(self::ESTRATEGIAS_DESC[$estrategia])) {
            return $this->errorResponse('Estratégia inválida');
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO pricing_rules (
                    account_id, nome, descricao, aplica_categoria, aplica_marca,
                    aplica_item_ids, estrategia, margem_minima, margem_alvo,
                    desconto_maximo, aumento_maximo, limite_aumento_ranking,
                    ativo, execucao_automatica, intervalo_verificacao
                ) VALUES (
                    :account_id, :nome, :descricao, :aplica_categoria, :aplica_marca,
                    :aplica_item_ids, :estrategia, :margem_minima, :margem_alvo,
                    :desconto_maximo, :aumento_maximo, :limite_aumento_ranking,
                    :ativo, :execucao_automatica, :intervalo_verificacao
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'nome' => $nome,
                'descricao' => $regra['descricao'] ?? null,
                'aplica_categoria' => $regra['categoria'] ?? null,
                'aplica_marca' => $regra['marca'] ?? null,
                'aplica_item_ids' => isset($regra['item_ids']) ? json_encode($regra['item_ids']) : null,
                'estrategia' => $estrategia,
                'margem_minima' => $regra['margem_minima'] ?? 10,
                'margem_alvo' => $regra['margem_alvo'] ?? 20,
                'desconto_maximo' => $regra['desconto_maximo'] ?? 30,
                'aumento_maximo' => $regra['aumento_maximo'] ?? 15,
                'limite_aumento_ranking' => $regra['limite_aumento_ranking'] ?? 8,
                'ativo' => $regra['ativo'] ?? 1,
                'execucao_automatica' => $regra['execucao_automatica'] ?? 0,
                'intervalo_verificacao' => $regra['intervalo_verificacao'] ?? 24
            ]);

            $regraId = $this->db->lastInsertId();

            return [
                'success' => true,
                'regra_id' => $regraId,
                'message' => 'Regra criada com sucesso'
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse('Erro ao criar regra: ' . $e->getMessage());
        }
    }

    /**
     * Lista regras de precificação
     */
    public function listarRegras(bool $apenasAtivas = false): array
    {
        try {
            $sql = "SELECT * FROM pricing_rules WHERE account_id = :account_id";
            if ($apenasAtivas) {
                $sql .= " AND ativo = 1";
            }
            $sql .= " ORDER BY criado_em DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['account_id' => $this->accountId]);

            return [
                'success' => true,
                'regras' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse('Erro ao listar regras');
        }
    }

    /**
     * Executa regra de precificação em itens
     * 
     * @param int $regraId ID da regra
     * @param bool $aplicar Se deve aplicar ou apenas simular
     * @return array Resultado da execução
     */
    public function executarRegra(int $regraId, bool $aplicar = false): array
    {
        // Buscar regra
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_rules 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $regraId, 'account_id' => $this->accountId]);
        $regra = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$regra) {
            return $this->errorResponse('Regra não encontrada');
        }

        // Buscar itens que se aplicam à regra
        $itens = $this->buscarItensParaRegra($regra);

        $resultados = [];
        $aplicados = 0;
        $erros = 0;

        foreach ($itens as $item) {
            $itemId = $item['id'];
            $precoAtual = (float)$item['price'];

            // Calcular preço sugerido
            $custos = $this->marginService->getCustosProduto($itemId);
            if (!$custos) continue;

            $concorrencia = $this->strategyService->analyzeCompetitorPrices($item['category_id']);
            $stats = $concorrencia['price_stats'] ?? [];

            if (empty($stats['count'])) continue;

            $precoSugerido = $this->calcularPrecoEstrategia($regra['estrategia'], $stats, $custos);
            
            // Verificar limites
            $variacao = (($precoSugerido - $precoAtual) / $precoAtual) * 100;
            
            if ($variacao > $regra['aumento_maximo']) {
                $precoSugerido = $precoAtual * (1 + $regra['aumento_maximo'] / 100);
            }
            if ($variacao < -$regra['desconto_maximo']) {
                $precoSugerido = $precoAtual * (1 - $regra['desconto_maximo'] / 100);
            }

            // Verificar margem mínima
            $margem = $this->marginService->calcularMargem($precoSugerido, $custos);
            if (($margem['margem_real'] ?? 0) < $regra['margem_minima']) {
                $precoMinimo = $this->marginService->calcularPrecoMinimo($custos, $regra['margem_minima']);
                $precoSugerido = $precoMinimo['preco_minimo'] ?? $precoSugerido;
            }

            $resultado = [
                'item_id' => $itemId,
                'titulo' => $item['title'],
                'preco_atual' => $precoAtual,
                'preco_sugerido' => round($precoSugerido, 2),
                'variacao' => round((($precoSugerido - $precoAtual) / $precoAtual) * 100, 2),
                'margem_estimada' => round($margem['margem_real'] ?? 0, 2)
            ];

            // Aplicar se solicitado
            if ($aplicar && abs($precoSugerido - $precoAtual) > 0.01) {
                $response = $this->mlClient->put("/items/{$itemId}", ['price' => $precoSugerido]);
                if (!isset($response['error'])) {
                    $resultado['aplicado'] = true;
                    $aplicados++;
                    $this->marginService->registrarAlteracaoPreco($itemId, $precoAtual, $precoSugerido, [
                        'origem' => 'auto',
                        'estrategia' => $regra['estrategia'],
                        'motivo' => "Regra: {$regra['nome']}"
                    ]);
                } else {
                    $resultado['aplicado'] = false;
                    $resultado['erro'] = $response['message'] ?? 'Erro desconhecido';
                    $erros++;
                }
            } else {
                $resultado['aplicado'] = false;
            }

            $resultados[] = $resultado;
        }

        // Atualizar última execução
        $this->db->prepare("UPDATE pricing_rules SET ultima_execucao = NOW() WHERE id = :id")
            ->execute(['id' => $regraId]);

        return [
            'success' => true,
            'regra' => $regra['nome'],
            'estrategia' => $regra['estrategia'],
            'modo' => $aplicar ? 'aplicacao' : 'simulacao',
            'resumo' => [
                'total_itens' => count($resultados),
                'aplicados' => $aplicados,
                'erros' => $erros
            ],
            'resultados' => $resultados
        ];
    }

    /**
     * Busca itens que se aplicam a uma regra
     */
    private function buscarItensParaRegra(array $regra): array
    {
        // Buscar itens do vendedor
        $params = ['status' => 'active', 'limit' => 50];
        $response = $this->mlClient->getMyItems($params);

        if (!$response || isset($response['error'])) {
            return [];
        }

        $itemIds = $response['results'] ?? [];
        if (empty($itemIds)) return [];

        // Buscar detalhes
        $itemsData = $this->mlClient->get("/items", ['ids' => implode(',', array_slice($itemIds, 0, 20))]);
        
        $itens = [];
        foreach ($itemsData as $data) {
            $item = $data['body'] ?? $data;

            // Filtrar por categoria
            if ($regra['aplica_categoria'] && ($item['category_id'] ?? '') !== $regra['aplica_categoria']) {
                continue;
            }

            // Filtrar por item_ids específicos
            if ($regra['aplica_item_ids']) {
                $ids = json_decode($regra['aplica_item_ids'], true) ?? [];
                if (!empty($ids) && !in_array($item['id'], $ids)) {
                    continue;
                }
            }

            $itens[] = $item;
        }

        return $itens;
    }

    /**
     * Retorna lista de estratégias disponíveis
     */
    public function getEstrategiasDisponiveis(): array
    {
        return [
            'success' => true,
            'estrategias' => self::ESTRATEGIAS_DESC
        ];
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
