# 📋 Ficha Técnica - Guia Rápido

**Status:** ✅ Implementado e Testado  
**Testes:** 47 testes unitários (100% aprovação)  
**Versão:** 1.0.0

## O que faz?

Analisa anúncios do Mercado Livre identificando atributos faltantes e gera sugestões inteligentes usando:

- 🎯 **Título** (60-75% confiança) - Regex + dicionários
- 📊 **Concorrentes** (70-95% confiança) - Benchmark de valores mais usados
- 🤖 **IA** (50-85% confiança) - Com 7 guardrails de segurança

## Acesso Rápido

### Interface Web
```
Dashboard → SEO → 📋 Ficha Técnica
URL: /dashboard/seo/ficha-tecnica
```

### API Principal

```bash
# Analisar completude
GET /api/tech-sheet/analyze/{itemId}

# Gerar sugestões
POST /api/tech-sheet/suggestions/{itemId}
{
  "use_title": true,
  "use_benchmark": true,
  "use_ai": false,
  "min_confidence": 60
}

# Aprovar sugestões
POST /api/tech-sheet/approve
{
  "suggestion_ids": [123, 124],
  "approved": true
}

# Aplicar em lote (background job)
POST /api/tech-sheet/apply-batch
{
  "item_ids": ["MLB123", "MLB456"]
}
```

## Feature Flags

```php
// config/app.php
'tech_sheet' => [
    'enabled' => true,
    'ai_enabled' => true,
    'benchmark_enabled' => true,
    'auto_apply' => false,  // Não aplicar sem aprovação
    'min_confidence_auto' => 80,
]
```

## Fluxo Básico

```
1. ANÁLISE → Identifica gaps (obrigatórios > filtros > recomendados)
2. SUGESTÕES → Gera opções com scoring de confiança
3. APROVAÇÃO → Humano decide (manual ou API)
4. APLICAÇÃO → Job aplica via API ML (batch até 50)
```

## Segurança da IA

✅ Apenas atributos com valores permitidos  
✅ Feature flag obrigatório  
✅ NUNCA sugere: BRAND, GTIN, MPN  
✅ Limita prompt a 20 valores  
✅ Rejeita "NAO_IDENTIFICADO"  
✅ Validação estrita  
✅ Logs de anomalias  

## Testes

```bash
# Executar todos os testes
./bin/test --filter=TechSheet

# 47 testes, 152 asserções
# TechSheetService: 15 testes
# TechSheetBenchmarkService: 22 testes
# TitleAttributeExtractor: 9 testes
```

## Banco de Dados

```bash
# Executar migração
php database/migrations/20260101_create_tech_sheet_tables.php
```

Cria 3 tabelas:
- `tech_sheet_item_summary` - Completude por item
- `tech_sheet_suggestions` - Sugestões (pending/approved/rejected/applied)
- `tech_sheet_execution_log` - Histórico de aplicações

## Troubleshooting

### Sem sugestões?
```bash
# Verificar feature flags
grep "'tech_sheet'" config/app.php

# Ver logs
tail -f storage/logs/mercado-livre-*.log | grep tech_sheet
```

### IA não funciona?
```bash
# Verificar: ai_enabled = true
# IA só funciona para: COLOR, SIZE, MATERIAL, etc.
# IA NUNCA funciona para: BRAND, GTIN, MPN
```

## Documentação Completa

- **[IMPLEMENTACAO_FICHA_TECNICA_POR_FASES.md](IMPLEMENTACAO_FICHA_TECNICA_POR_FASES.md)** - Especificação técnica
- **[TESTING_GUIDE.md](../TESTING_GUIDE.md)** - Como testar
- **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** - API completa
- **[LOGGING_GUIDE.md](../LOGGING_GUIDE.md)** - Logs

## Arquivos Principais

```
app/Services/TechSheetService.php              # Service principal
app/Services/TechSheetBenchmarkService.php     # Análise concorrentes
app/Services/TitleAttributeExtractorService.php # Extração título
app/Services/AI/SEO/AttributeKiller.php        # IA + guardrails
app/Controllers/TechnicalSheetController.php   # API
app/Views/dashboard/tech-sheet/index.php       # UI
database/migrations/20260101_create_tech_sheet_tables.php
config/app.php                                 # Feature flags
```

## Métricas

```php
// Logs estruturados disponíveis
tech_sheet.analyze.started
tech_sheet.suggestions.generated  
tech_sheet.approve.manual
tech_sheet.apply.success
tech_sheet.apply.failed
```

---

**Status:** ✅ Pronto para uso  
**Desenvolvido:** AI Development Team  
**Data:** 2026-01-01
