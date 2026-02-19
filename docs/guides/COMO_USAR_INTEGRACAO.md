# 🎯 Como Usar a Integração Tech Sheet + SEO Strategies

**Data:** 23 de Janeiro de 2026
**Status:** Sistema Operacional
**Nível:** Guia Prático

---

## 🚀 INÍCIO RÁPIDO

### Passo 1: Fazer Login
```
https://eskill.com.br/login
```

### Passo 2: Acessar Ficha Técnica
```
https://eskill.com.br/dashboard/seo/ficha-tecnica
```

### Passo 3: Analisar um Produto
1. Selecione um produto da lista
2. Clique em "Analisar SEO" (novo botão!)
3. Veja o score e recomendações
4. Aplique as otimizações sugeridas

---

## 📊 EXEMPLO DE USO COMPLETO

### Cenário: Otimizar um Bauleto

#### 1. **Estado Inicial**

```
Produto: MLB123456789
Título: "Bauleto 41L"
Completude: 45%

Campos faltando:
- 3 campos obrigatórios
- 5 campos de filtro
- 7 campos ocultos (SEO)
```

#### 2. **Análise SEO** (NOVO!)

**Clicar em "Analisar SEO"**

O sistema irá:
- ✅ Expandir sinônimos: bauleto → baú, top case, maleiro
- ✅ Detectar nível hierárquico: nivel_2 (produto)
- ✅ Calcular score semântico: 68/100
- ✅ Verificar contextos de uso: 0 detectados
- ✅ Gerar 7 variações de título

**Resultado:**
```json
{
  "overall_score": 68.5,
  "quality_level": "Boa",
  "strategy_scores": {
    "E1_SYNONYMS": 70,
    "E2_HIDDEN_FIELDS": 45,
    "E6_CONTEXTS": 30,
    "E9_SEMANTIC": 75
  },
  "recommendations": [
    "Adicionar contextos de uso (delivery, motoboy)",
    "Preencher campos ocultos para SEO",
    "Expandir título para 40-60 caracteres"
  ]
}
```

#### 3. **Aplicar Otimizações**

**Sugestões geradas automaticamente:**

1. **Título Otimizado:**
   ```
   De: "Bauleto 41L"
   Para: "Bauleto 41 Litros Delivery Motoboy Ifood Pro Tork"
   ```
   - Tamanho: 53 caracteres (ideal: 40-60)
   - Contextos: delivery, motoboy
   - Plataformas: Ifood
   - Marca: Pro Tork

2. **Campos Ocultos Preenchidos:**
   ```
   BRAND → Pro Tork
   ITEM_CONDITION → new
   CAPACITY → 41 liters
   USE_CASE → delivery, professional
   COMPATIBILITY → universal
   ```

3. **Score após otimização:**
   ```
   De: 68.5/100 (Boa)
   Para: 87.2/100 (Excelente) 🎉
   ```

---

## 🔥 CASOS DE USO

### Caso 1: Produto com Título Fraco

**Antes:**
```
Título: "Bau"
Score: 42/100 (Regular)
Problemas:
- Título muito curto (3 caracteres)
- Sem contextos
- Sem sinônimos
```

**Depois:**
```
Título: "Baú Delivery 45L Motoboy Ifood Rappi Universal"
Score: 85/100 (Excelente)
Melhorias:
- Tamanho ideal (49 caracteres)
- 3 contextos (delivery, motoboy, universal)
- 2 plataformas (Ifood, Rappi)
- Capacidade especificada (45L)
```

---

### Caso 2: Produto Bem Otimizado

**Estado atual:**
```
Título: "Top Case Universal Capacete Cabe 2 Capacetes Pro Tork"
Score: 84/100 (Excelente)

Análise:
✅ Tamanho ideal: 57 caracteres
✅ Contextos: capacete, universal
✅ Especificações: cabe 2
✅ Marca: Pro Tork
✅ Nível: nivel_3 (contexto)

Recomendações:
- Produto já está bem otimizado
- Manter monitoramento
```

---

### Caso 3: Produto para Delivery

**Estratégia específica:**

```
Título base: "Baú 45L"

Otimização para Delivery:
1. Adicionar contexto: "Delivery"
2. Mencionar plataformas: "Ifood Rappi"
3. Público-alvo: "Motoboy"
4. Especificações: "45 Litros"

Resultado:
"Baú Delivery 45L Motoboy Ifood Rappi Térmico Impermeável"
Score: 89/100 (Excelente)
```

---

## 🎯 12 ESTRATÉGIAS EXPLICADAS

### E1 - Hierarquia de Sinônimos
**O que faz:** Expande o título com sinônimos relevantes

**Exemplo:**
```
"Bauleto" →
  - Baú (nivel_1)
  - Top Case (nivel_2)
  - Maleiro (nivel_2)
  - Bagageiro (nivel_3)
```

**Quando usar:** Sempre! Aumenta cobertura de busca

---

### E2 - Campos Ocultos
**O que faz:** Detecta e preenche campos que não aparecem no anúncio mas ajudam no SEO

**Exemplo:**
```
Campos detectados:
- BRAND → Pro Tork
- CAPACITY → 41 liters
- USE_CASE → delivery
- COMPATIBILITY → universal
```

**Quando usar:** Produtos com ficha técnica incompleta

---

### E6 - Contextos de Uso
**O que faz:** Identifica e adiciona contextos relevantes

**Exemplos:**
```
Profissional: delivery, motoboy, trabalho
Lazer: passeio, viagem, turismo
Carga: transporte, bagagem, capacete
Universal: todos os modelos, compatível
```

**Quando usar:** Produtos com múltiplos usos

---

### E9 - Score Semântico
**O que faz:** Calcula relevância das palavras (0-100)

**Exemplo:**
```
"delivery" → 87/100 (alta relevância)
"bauleto" → 77/100 (média relevância)
"top" → 45/100 (baixa relevância)
```

**Quando usar:** Otimização avançada de título

---

## 📈 INTERPRETANDO O SCORE

### Score Geral (0-100)

| Faixa | Qualidade | Significado |
|-------|-----------|-------------|
| 80-100 | 🟢 Excelente | Título otimizado, ranking alto |
| 60-79 | 🟡 Boa | Título bom, pode melhorar |
| 40-59 | 🟠 Regular | Título fraco, precisa otimização |
| 0-39 | 🔴 Baixa | Título muito fraco, otimizar urgente |

### Componentes do Score

**Tamanho do Título (20 pontos)**
```
40-60 caracteres = 20 pontos (ideal)
30-70 caracteres = 15 pontos (bom)
Outros           = 10 pontos (ruim)
```

**Nível Hierárquico (20 pontos)**
```
nivel_3 (contexto)  = 20 pontos (melhor)
nivel_2 (produto)   = 15 pontos (bom)
nivel_1 (genérico)  = 10 pontos (fraco)
```

**Score Semântico (30 pontos)**
```
Baseado na relevância média das palavras
Palavras com contexto ganham +20% de peso
```

**Contextos de Uso (15 pontos)**
```
5 pontos por contexto detectado
Máximo: 3 contextos = 15 pontos
```

**Expansões (15 pontos)**
```
10+ variações = 15 pontos
5-9 variações = 10 pontos
<5 variações  =  5 pontos
```

---

## 💡 DICAS PRÁTICAS

### ✅ Boas Práticas

1. **Tamanho Ideal do Título**
   - Mínimo: 40 caracteres
   - Ideal: 50 caracteres
   - Máximo: 60 caracteres

2. **Incluir Contextos**
   - Sempre adicionar contexto de uso
   - Exemplos: delivery, motoboy, profissional

3. **Especificar Detalhes**
   - Capacidade: "41 Litros"
   - Quantidade: "Cabe 2 Capacetes"
   - Tipo: "Universal"

4. **Mencionar Marca**
   - Sempre no final do título
   - Exemplo: "Pro Tork", "Givi"

5. **Usar Sinônimos**
   - Variar termos: bauleto, baú, top case
   - Aumenta cobertura de busca

### ❌ Evitar

1. **Títulos muito curtos**
   - "Bau" → apenas 3 caracteres
   - Perde muitas oportunidades de SEO

2. **Títulos sem contexto**
   - "Bauleto 41L" → para quem? para quê?
   - Adicionar: delivery, motoboy, etc.

3. **Repetições desnecessárias**
   - "Baú Baú Baú" → não melhora SEO
   - Usar sinônimos diferentes

4. **Excesso de palavras irrelevantes**
   - "Super Mega Top Baú" → palavras vazias
   - Focar em especificações reais

5. **Omitir informações importantes**
   - Capacidade, marca, compatibilidade
   - São essenciais para o comprador

---

## 🔗 INTEGRAÇÃO COM API

### Endpoint Principal

```bash
GET /api/seo-killer/strategies/analyze/{itemId}
```

**Resposta:**
```json
{
  "item_id": "MLB123456789",
  "title": "Bauleto 41 Litros Delivery Motoboy",
  "overall_score": 82.5,
  "quality_level": "Excelente",
  "strategy_scores": {
    "E1_SYNONYMS": 85,
    "E2_HIDDEN_FIELDS": 78,
    "E3_INJECTION": 90,
    "E4_COVERAGE": 82,
    "E5_FIELD_WEIGHT": 88,
    "E6_CONTEXTS": 91,
    "E7_LONG_TAIL": 79,
    "E8_DENSITY": 86,
    "E9_SEMANTIC": 89,
    "E10_COMPATIBILITY": 75,
    "E11_FAQ": 80,
    "E12_MONITORING": 95
  },
  "recommendations": [
    "Produto bem otimizado",
    "Manter monitoramento ativo"
  ],
  "expansions": [
    "Bauleto 41 Litros Delivery Motoboy Ifood",
    "Baú 41L Delivery Motoboy Rappi",
    "Top Case 41 Litros Delivery Profissional"
  ]
}
```

---

## 📞 SUPORTE

### Documentação
- [Guia de Acesso](GUIA_ACESSO_SISTEMA.md)
- [Sistema Completo](SISTEMA_COMPLETO_FINAL.md)
- [Validação](VALIDACAO_SISTEMA_COMPLETA.md)

### Testes
```bash
# Testar análise de produto
php test_ml_api_products.php

# Testar integração completa
php test_complete_integration.php
```

---

**Última atualização:** 23 de Janeiro de 2026
**Versão:** 1.0.0
**Status:** 🟢 Operacional
