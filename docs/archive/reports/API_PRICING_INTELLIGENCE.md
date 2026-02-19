# Módulo de Precificação Inteligente - API Documentation

## Visão Geral

O módulo de Precificação Inteligente permite gerenciar preços e margens de anúncios do Mercado Livre com cálculos em tempo real, simulações de promoções e alertas de ranqueamento.

## Base URL

```
/api/pricing-intelligence/{accountId}
```

---

## Endpoints de Margem

### Calcular Margem

**POST** `/margin/calculate`

Calcula a margem real de um produto considerando todos os custos.

**Request Body:**
```json
{
  "preco_venda": 199.90,
  "custo_producao": 80.00,
  "custo_embalagem": 5.00,
  "custo_frete_gratis": 15.00,
  "taxa_comissao_ml": 16,
  "taxa_imposto": 9,
  "acos_medio": 5
}
```

**Response:**
```json
{
  "success": true,
  "preco_venda": 199.90,
  "lucro_unitario": 40.99,
  "margem_real": 20.51,
  "margem_sobre_custo": 40.99,
  "indicador": "verde",
  "breakdown": {
    "custos_fixos": {
      "producao": 80.00,
      "embalagem": 5.00,
      "frete_gratis": 15.00,
      "total_fixo": 100.00
    },
    "custos_variaveis": {
      "comissao_ml": 31.98,
      "imposto": 17.99,
      "ads": 9.99,
      "total_variavel": 59.96
    },
    "custo_total": 159.96
  }
}
```

### Calcular Preço Mínimo

**POST** `/margin/minimum`

Calcula o preço mínimo para atingir uma margem alvo.

**Request Body:**
```json
{
  "custo_producao": 80.00,
  "custo_embalagem": 5.00,
  "taxa_comissao_ml": 16,
  "taxa_imposto": 9,
  "margem_alvo": 15
}
```

---

## Endpoints de Promoção

### Simular Promoção

**POST** `/promotion/simulate/{itemId}`

Simula uma promoção para um item específico.

**Request Body:**
```json
{
  "desconto": 15,
  "custos": {
    "custo_producao": 80.00,
    "taxa_comissao_ml": 16
  },
  "salvar": false
}
```

**Response:**
```json
{
  "success": true,
  "item_id": "MLB123456789",
  "titulo": "Produto Exemplo",
  "preco_original": 199.90,
  "desconto_percentual": 15,
  "preco_promocional": 169.92,
  "economia_cliente": 29.98,
  "margem": {
    "original": 20.51,
    "promocao": 12.35,
    "diferenca": 8.16
  },
  "lucro": {
    "original": 40.99,
    "promocao": 20.98,
    "diferenca": 20.01
  },
  "desconto_maximo_seguro": 22.5,
  "viavel": true,
  "alerta": null,
  "projecoes": {
    "vendas_base_semanal": 5,
    "fator_conversao": 1.40,
    "vendas_estimadas_semanal": 7,
    "aumento_percentual": 40,
    "receita_projetada_semanal": 1189.44,
    "lucro_projetado_semanal": 146.86
  },
  "cenarios": [...]
}
```

### Gerar Cenários de Desconto

**POST** `/promotion/scenarios/{itemId}`

Gera múltiplos cenários de desconto (5%, 10%, 15%, 20%, 25%, 30%, 40%, 50%).

**Response:**
```json
{
  "success": true,
  "item_id": "MLB123456789",
  "preco_original": 199.90,
  "cenarios": [
    {
      "desconto": 5,
      "preco": 189.91,
      "economia": 9.99,
      "margem": 18.45,
      "lucro": 35.06,
      "viavel": true,
      "indicador": "🟢",
      "aumento_vendas_estimado": "+10% vendas"
    },
    ...
  ]
}
```

### Aplicar Promoção

**POST** `/promotion/apply/{itemId}`

Aplica uma promoção no Mercado Livre.

**Request Body:**
```json
{
  "preco_promocional": 169.90,
  "motivo": "Promoção de verão"
}
```

### Simular Central de Ofertas

**POST** `/promotion/central-ofertas/{itemId}`

Simula participação na Central de Ofertas do ML.

**Request Body:**
```json
{
  "tipo_oferta": "deal_of_day"
}
```

**Tipos disponíveis:**
- `deal_of_day` - Oferta do Dia (mínimo 30% desconto)
- `lightning_deal` - Oferta Relâmpago (mínimo 20% desconto)
- `best_seller` - Mais Vendidos (mínimo 10% desconto)

---

## Endpoints de Cenários/Estratégias

### Comparar Estratégias

**GET** `/scenarios/strategies/{itemId}`

Compara diferentes estratégias de precificação para um item.

**Response:**
```json
{
  "success": true,
  "item_id": "MLB123456789",
  "titulo": "Produto Exemplo",
  "preco_atual": 199.90,
  "margem_atual": 20.51,
  "concorrencia": {
    "minimo": 159.90,
    "maximo": 299.90,
    "media": 205.50,
    "mediana": 195.00,
    "quantidade": 25
  },
  "estrategias": {
    "agressivo": {
      "nome": "Agressivo",
      "descricao": "Preço ligeiramente abaixo do menor concorrente",
      "preco_sugerido": 156.70,
      "variacao_atual": -21.61,
      "margem_estimada": 8.5,
      "impacto_ranking": "amarelo",
      "recomendado": false
    },
    "competitivo": {
      "nome": "Competitivo",
      "descricao": "Preço alinhado com a mediana do mercado",
      "preco_sugerido": 195.00,
      "variacao_atual": -2.45,
      "margem_estimada": 19.5,
      "impacto_ranking": "verde",
      "recomendado": true
    },
    "premium": {...},
    "valor": {...},
    "liquidacao": {...}
  },
  "recomendacao": {
    "estrategia_recomendada": "competitivo",
    "preco_recomendado": 195.00,
    "acao": "manter",
    "motivo": "Preço atual está bem posicionado. Margem de 20.51% é adequada."
  }
}
```

### Criar Cenário What-If

**POST** `/scenarios/what-if/{itemId}`

Cria cenário personalizado para análise.

**Request Body:**
```json
{
  "preco": 189.90,
  "custos": {
    "custo_producao": 90.00,
    "taxa_imposto": 12
  },
  "elasticidade": -1.5
}
```

### Listar Estratégias Disponíveis

**GET** `/scenarios/strategies`

Retorna lista de estratégias disponíveis com descrições.

---

## Endpoints de Regras Automáticas

### Criar Regra de Precificação

**POST** `/rules`

Cria uma regra de precificação automática.

**Request Body:**
```json
{
  "nome": "Competitivo Eletrônicos",
  "descricao": "Mantém preços competitivos em eletrônicos",
  "estrategia": "competitivo",
  "categoria": "MLB1648",
  "margem_minima": 10,
  "margem_alvo": 20,
  "desconto_maximo": 30,
  "aumento_maximo": 15,
  "limite_aumento_ranking": 8,
  "execucao_automatica": false,
  "intervalo_verificacao": 24
}
```

**Estratégias disponíveis:**
- `agressivo` - Abaixo do menor concorrente
- `competitivo` - Alinhado com mediana
- `premium` - Acima da média (+10%)
- `valor` - Pequeno desconto
- `liquidacao` - Desconto agressivo

### Listar Regras

**GET** `/rules`

**Query params:**
- `ativas` - Filtrar apenas regras ativas

### Executar Regra

**POST** `/rules/{ruleId}/execute`

Executa uma regra (simular ou aplicar).

**Request Body:**
```json
{
  "aplicar": false
}
```

### Ativar/Desativar Regra

**PUT** `/rules/{ruleId}/toggle`

### Excluir Regra

**DELETE** `/rules/{ruleId}`

---

## Endpoints de Alertas

### Listar Alertas

**GET** `/alerts`

**Query params:**
- `nivel` - verde, amarelo, vermelho
- `nao_lidos` - Filtrar não lidos
- `limit` - Limite de resultados

### Analisar Ranking de Item

**GET** `/alerts/analyze/{itemId}`

Analisa posição de preço de um item no mercado.

### Alertas Não Resolvidos

**GET** `/alerts/unresolved`

### Marcar Alertas como Lidos

**POST** `/alerts/mark-read`

**Request Body:**
```json
{
  "alert_ids": [1, 2, 3]
}
```

### Resolver Alerta

**POST** `/alerts/{alertId}/resolve`

**Request Body:**
```json
{
  "resolution": "Preço ajustado para R$ 189,90"
}
```

---

## Endpoints de Custos

### Buscar Custos

**GET** `/costs/{itemId}`

### Salvar Custos

**POST** `/costs/{itemId}`

**Request Body:**
```json
{
  "sku": "ABC123",
  "custo_producao": 80.00,
  "custo_embalagem": 5.00,
  "custo_frete_gratis": 15.00,
  "taxa_comissao_ml": 16,
  "taxa_imposto": 9,
  "acos_medio": 5,
  "margem_minima": 10,
  "margem_alvo": 20
}
```

### Importar Custos em Lote

**POST** `/bulk-costs`

**Request Body:**
```json
{
  "items": [
    {"item_id": "MLB123", "sku": "ABC", "custo_producao": 80},
    {"item_id": "MLB456", "sku": "DEF", "custo_producao": 120}
  ]
}
```

---

## Outros Endpoints

### Dashboard

**GET** `/dashboard`

Retorna dados resumidos do dashboard.

### Histórico de Preços

**GET** `/history/{itemId}`

**Query params:**
- `dias` - Período em dias (default: 30)

### Aplicar Preço

**POST** `/apply/{itemId}`

Aplica novo preço diretamente no ML.

**Request Body:**
```json
{
  "novo_preco": 189.90,
  "motivo": "Ajuste competitivo",
  "estrategia": "competitive",
  "force": false
}
```

### Listar Itens

**GET** `/items`

**Query params:**
- `page` - Página
- `limit` - Itens por página (max 100)
- `status` - active, paused, closed
- `margem_min` - Filtro margem mínima
- `margem_max` - Filtro margem máxima
- `categoria` - Filtro categoria
- `q` - Busca por SKU/título

---

## Indicadores de Saúde

| Indicador | Margem | Cor |
|-----------|--------|-----|
| 🟢 verde | ≥ 20% | Excelente |
| 🟡 amarelo | 10-20% | Boa |
| 🟠 laranja | 5-10% | Atenção |
| 🔴 vermelho | < 5% | Crítica |

## Limites de Impacto no Ranking

| Variação | Alerta | Recomendação |
|----------|--------|--------------|
| ≤ 8% | 🟢 Seguro | Alteração sem impacto |
| 8-12% | 🟡 Moderado | Monitorar vendas |
| 12-15% | 🔴 Alto | Limitar a 10% por vez |
| > 15% | 🔴 Severo | Aumentar gradualmente |
