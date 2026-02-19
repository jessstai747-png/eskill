# Quality Check System - Sistema de Verificação de Qualidade

Sistema completo de verificação, pontuação e validação de qualidade para anúncios no Mercado Livre.

## 📋 Visão Geral

O **Quality Check System** é composto por 3 serviços principais que trabalham em conjunto para garantir a qualidade máxima dos anúncios:

### 1. **HealthCheckService** - Verificação de Saúde
Analisa a saúde geral do anúncio identificando problemas e oportunidades de melhoria.

### 2. **QualityScoreService** - Pontuação de Qualidade
Calcula um score de qualidade de 0-100 baseado nas melhores práticas do ML.

### 3. **ValidationService** - Validação Pré-Publicação
Valida anúncios antes de publicar, evitando erros e pausas.

---

## 🚀 Instalação

Os serviços já estão integrados ao sistema. Basta acessar via API ou integrar nos seus workflows.

---

## 📡 API Endpoints

### Health Check

#### `GET /api/quality/health/{itemId}`
Verifica a saúde de um anúncio específico.

**Response:**
```json
{
  "success": true,
  "item_id": "MLB123456",
  "title": "Produto XYZ",
  "status": "active",
  "health": {
    "status": "warning",
    "score": 75.5,
    "api_health": {
      "available": false
    }
  },
  "issues": [
    {
      "category": "images",
      "severity": "high",
      "title": "Apenas 3 imagens (mínimo recomendado: 6)",
      "description": "Anúncios com mais imagens têm melhor conversão",
      "impact": "Menor taxa de conversão"
    }
  ],
  "recommendations": [
    {
      "category": "images",
      "priority": "high",
      "action": "Adicionar mais imagens",
      "description": "Adicione pelo menos 3 imagens de qualidade"
    }
  ],
  "opportunities": [
    {
      "category": "shipping",
      "title": "Ativar frete grátis",
      "description": "Frete grátis aumenta visibilidade e conversão em até 40%",
      "potential_impact": "+40% conversão"
    }
  ]
}
```

#### `POST /api/quality/health/batch`
Verifica múltiplos anúncios em lote.

**Request:**
```json
{
  "item_ids": ["MLB123", "MLB456", "MLB789"]
}
```

#### `GET /api/quality/health/{itemId}/recommendations`
Obtém apenas as recomendações priorizadas.

---

### Quality Score

#### `GET /api/quality/score/{itemId}`
Calcula o quality score detalhado do anúncio.

**Response:**
```json
{
  "success": true,
  "item_id": "MLB123456",
  "title": "Produto XYZ",
  "quality_score": {
    "total": 78.5,
    "rating": {
      "key": "very_good",
      "label": "Muito Bom",
      "color": "info",
      "range": [75, 100]
    },
    "components": {
      "content": {
        "score": 85,
        "percentage": 85.0,
        "details": [
          "✓ Título com tamanho ideal (45-60 caracteres)",
          "✓ Título contém palavras-chave de confiança",
          "✓ Descrição completa (500+ caracteres)"
        ]
      },
      "completeness": {
        "score": 90,
        "percentage": 90.0,
        "details": [
          "✓ Todos os atributos obrigatórios preenchidos",
          "✓ Marca (BRAND) informada",
          "✓ GTIN (código de barras) informado"
        ]
      },
      "experience": {
        "score": 75,
        "percentage": 75.0,
        "details": [
          "✓ Frete grátis ativado",
          "✓ Mercado Envios Full (melhor ranking)",
          "~ Anúncio Grátis (visibilidade limitada)"
        ]
      },
      "performance": {
        "score": 65,
        "percentage": 65.0,
        "details": [
          "✓ Vendas comprovadas (10+)",
          "✓ Vinculado ao catálogo (melhor performance)"
        ]
      },
      "compliance": {
        "score": 100,
        "percentage": 100.0,
        "details": [
          "✓ Anúncio ativo",
          "✓ Sem problemas de conformidade"
        ]
      }
    }
  },
  "strengths": [
    "Título com tamanho ideal (45-60 caracteres)",
    "Todos os atributos obrigatórios preenchidos",
    "Frete grátis ativado"
  ],
  "weaknesses": [
    "Anúncio Grátis (visibilidade limitada)",
    "Considere usar tipo de anúncio Premium"
  ],
  "improvement_potential": {
    "current_score": 78.5,
    "max_possible_score": 92.3,
    "potential_gain": "+13.8 pontos",
    "top_improvements": [
      {
        "action": "Anúncio Grátis (visibilidade limitada)",
        "impact": "high",
        "potential_gain": "+4.6 pontos"
      }
    ]
  }
}
```

---

### Validation

#### `POST /api/quality/validate`
Valida dados de um anúncio antes de publicar.

**Request:**
```json
{
  "title": "Produto XYZ Original Novo Lacrado",
  "category_id": "MLB1234",
  "price": 199.90,
  "currency_id": "BRL",
  "available_quantity": 10,
  "buying_mode": "buy_it_now",
  "condition": "new",
  "listing_type_id": "gold_special",
  "pictures": [
    {"source": "https://..."}
  ],
  "attributes": [
    {"id": "BRAND", "value_name": "Samsung"}
  ]
}
```

**Response:**
```json
{
  "success": true,
  "can_publish": true,
  "local_validation": {
    "passed": true,
    "errors": [],
    "warnings": [
      {
        "field": "pictures",
        "category": "images",
        "severity": "warning",
        "message": "Apenas 1 imagens (recomendado: 6+ imagens)"
      }
    ]
  },
  "api_validation": {
    "passed": true,
    "api_available": false,
    "warnings": [
      {
        "category": "api_validation",
        "severity": "warning",
        "message": "Não foi possível validar com a API do ML: timeout"
      }
    ]
  },
  "category_validation": {
    "passed": true,
    "errors": [],
    "warnings": []
  },
  "attributes_validation": {
    "passed": false,
    "errors": [
      {
        "field": "attributes[MODEL]",
        "category": "attributes",
        "severity": "error",
        "message": "Atributo obrigatório 'Modelo' está ausente"
      }
    ]
  },
  "errors": [
    {
      "field": "attributes[MODEL]",
      "category": "attributes",
      "severity": "error",
      "message": "Atributo obrigatório 'Modelo' está ausente"
    }
  ],
  "warnings": [],
  "summary": {
    "can_publish": false,
    "total_errors": 1,
    "total_warnings": 1
  }
}
```

#### `POST /api/quality/validate/batch`
Valida múltiplos anúncios em lote.

#### `POST /api/quality/autofix`
Corrige automaticamente erros simples.

**Request:**
```json
{
  "title": "Produto  XYZ   ",
  "price": 199.90,
  "available_quantity": 5
}
```

**Response:**
```json
{
  "success": true,
  "result": {
    "original": {...},
    "fixed": {
      "title": "Produto XYZ",
      "price": 199.90,
      "available_quantity": 5,
      "currency_id": "BRL",
      "buying_mode": "buy_it_now",
      "condition": "new",
      "listing_type_id": "gold_special"
    },
    "changes": [
      "currency_id definido como 'BRL'",
      "buying_mode definido como 'buy_it_now'",
      "condition definido como 'new'",
      "Título limpo (espaços extras removidos)"
    ],
    "changed": true
  }
}
```

---

### Complete Report

#### `GET /api/quality/report/{itemId}`
Relatório completo combinando Health + Score + Action Plan.

**Response:**
```json
{
  "success": true,
  "item_id": "MLB123456",
  "title": "Produto XYZ",
  "quality_score": {
    "total": 78.5,
    "rating": {...}
  },
  "health_check": {
    "status": "warning",
    "score": 75.5,
    "issues": [...],
    "recommendations": [...],
    "opportunities": [...]
  },
  "summary": {
    "overall_quality": {...},
    "health_status": "warning",
    "total_issues": 3,
    "critical_issues": 0,
    "strengths": [...],
    "weaknesses": [...]
  },
  "action_plan": [
    {
      "priority": 1,
      "category": "attributes",
      "action": "Atributo obrigatório 'Modelo' está ausente",
      "description": "...",
      "impact": "Anúncio pode ser pausado",
      "urgency": "critical"
    }
  ]
}
```

---

## 🔧 Uso nos Serviços

### Integração com SeoAnalyzerService

O SeoAnalyzerService já está integrado automaticamente:

```php
$analyzer = new SeoAnalyzerService($accountId);

// Análise com quality check incluído
$result = $analyzer->analyzeItem('MLB123456', true);

// Análise sem quality check
$result = $analyzer->analyzeItem('MLB123456', false);
```

### Uso Direto dos Serviços

```php
use App\Services\Quality\HealthCheckService;
use App\Services\Quality\QualityScoreService;
use App\Services\Quality\ValidationService;

// Health Check
$health = new HealthCheckService($accountId);
$result = $health->checkItemHealth('MLB123456');

// Quality Score
$quality = new QualityScoreService($accountId);
$score = $quality->calculateQualityScore('MLB123456');

// Validation
$validator = new ValidationService($accountId);
$validation = $validator->validateListing($itemData);

// Auto-fix
$fixed = $validator->autoFix($itemData);
```

---

## 📊 Classificação de Scores

| Score   | Rating       | Cor     | Descrição                        |
|---------|--------------|---------|----------------------------------|
| 90-100  | Excelente    | success | Anúncio otimizado ao máximo      |
| 75-89   | Muito Bom    | info    | Anúncio com alta qualidade       |
| 60-74   | Bom          | primary | Anúncio adequado, pode melhorar  |
| 40-59   | Regular      | warning | Necessita melhorias importantes  |
| 0-39    | Ruim         | danger  | Requer otimização urgente        |

---

## 🎯 Categorias de Análise

### Health Check
- **Catálogo**: Vinculação com catálogo do ML
- **Atributos**: Atributos obrigatórios e recomendados
- **Imagens**: Quantidade e qualidade
- **Descrição**: Completude e estruturação
- **Preço**: Validação e competitividade
- **Shipping**: Modalidades e frete grátis
- **Status**: Situação do anúncio (ativo, pausado, etc.)

### Quality Score
- **Content (30%)**: Qualidade do título, descrição e imagens
- **Completeness (25%)**: Atributos obrigatórios e recomendados preenchidos
- **Experience (20%)**: Frete, tipo de anúncio, disponibilidade
- **Performance (15%)**: Vendas, conversão, catálogo
- **Compliance (10%)**: Status, moderações, conformidade

---

## 🚦 Níveis de Severidade

### Issues
- **critical**: Bloqueia publicação ou causa pausa
- **high**: Impacta significativamente visibilidade
- **medium**: Oportunidade importante de melhoria
- **low**: Melhoria opcional

### Recommendations
- **critical**: Ação imediata necessária
- **high**: Alta prioridade
- **medium**: Prioridade média
- **low**: Pode ser feito depois

---

## 💡 Casos de Uso

### 1. **Pré-Publicação**
```php
// Validar antes de publicar
$validator = new ValidationService($accountId);
$validation = $validator->validateListing($itemData);

if (!$validation['can_publish']) {
    // Mostrar erros e bloquear publicação
    return $validation['errors'];
}

// Auto-corrigir problemas simples
$fixed = $validator->autoFix($itemData);
$itemData = $fixed['fixed'];

// Publicar
```

### 2. **Auditoria de Anúncios**
```php
// Verificar saúde de todos os anúncios
$health = new HealthCheckService($accountId);
$items = ['MLB123', 'MLB456', 'MLB789'];
$results = $health->checkMultipleItems($items);

// Filtrar anúncios com problemas críticos
$critical = array_filter($results, function($r) {
    return $r['health']['status'] === 'critical';
});
```

### 3. **Otimização Contínua**
```php
// Calcular score e identificar melhorias
$quality = new QualityScoreService($accountId);
$score = $quality->calculateQualityScore('MLB123456');

// Se score < 75, aplicar melhorias
if ($score['quality_score']['total'] < 75) {
    $improvements = $score['improvement_potential']['top_improvements'];
    // Aplicar melhorias automaticamente ou sugerir ao usuário
}
```

### 4. **Relatório Executivo**
```php
// Relatório completo para gestão
$controller = new QualityController();
$report = $controller->getCompleteReport('MLB123456');

// Export para PDF ou email
```

---

## 🔗 Integração com SEO Killer

O Quality Check está integrado ao **SEO Killer** e **AutoPilot**:

```php
// SEO Killer já usa Quality Check internamente
$seoKiller->optimizeItem('MLB123456'); // Inclui quality validation

// AutoPilot considera quality score
$autopilot->run(); // Prioriza itens com baixo quality score
```

---

## 📈 Próximas Melhorias

- [ ] Dashboard visual de qualidade por conta
- [ ] Alertas automáticos para queda de qualidade
- [ ] Comparação com concorrentes
- [ ] Sugestões de melhoria com IA
- [ ] Agendamento de auditorias periódicas
- [ ] Export de relatórios em PDF/Excel

---

## 🐛 Troubleshooting

### API do ML não disponível
O sistema funciona mesmo se a API `/items/validate` do ML não estiver disponível. A validação local é suficiente para a maioria dos casos.

### Timeout em análises
Para análises de muitos itens, use as rotas de batch que são otimizadas.

### Falsos positivos
Se encontrar falsos positivos nas validações, reporte para ajustarmos as regras.

---

## 📞 Suporte

Para dúvidas ou problemas:
- Documente o item_id e o erro
- Verifique os logs em `storage/logs/`
- Contate o suporte técnico

---

**Desenvolvido como parte do Mercado Livre Manager**  
Versão: 1.0.0  
Data: 06/02/2026
