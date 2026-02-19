---
description: "Otimiza anúncio do Mercado Livre — título, descrição, keywords, ficha técnica"
agent: MercadoLivre
tools:
  - codebase
  - fetch
  - runInTerminal
  - editFiles
  - search
---

Otimize o anúncio do Mercado Livre para máxima visibilidade e conversão.

## ANTES de otimizar:
- Leia `project-status.json` para conferir status das features SEO
- Leia `claude-progress.txt` para contexto de otimizações anteriores

## Análise e Otimização:

### 1. Título (máx 60 chars)
- Formato: [Produto] + [Modelo Moto] + [Marca] + [Diferencial]
- Keywords mais buscadas no início
- Compatibilidades separadas por espaço (não vírgula)
- NUNCA: CAPS LOCK total, caracteres especiais, "PROMOÇÃO", "FRETE GRÁTIS"

### 2. Descrição
- Primeiro parágrafo: benefício principal + compatibilidade
- Bullet points com especificações técnicas
- Keywords naturais no texto (não keyword stuffing)
- Call-to-action no final
- Informar: material, dimensões, peso, acabamento

### 3. Ficha Técnica
- Preencher 100% dos atributos disponíveis na categoria
- Material, cor, marca, modelo compatível, peso, dimensões
- Quanto mais completa, melhor o ranking

### 4. Palavras-chave para pesquisa
- Listar 10-15 keywords relevantes
- Incluir variações (bagageiro, suporte de bagagem, rack traseiro)
- Incluir modelos de moto compatíveis
- Incluir sinônimos regionais

### 5. Categoria
- Verificar se está na categoria mais específica possível
- Categorias mais específicas = menos concorrência = melhor ranking

## Output OBRIGATÓRIO:

### 📝 Título Otimizado
`[título com max 60 chars]` (X chars)

### 🔑 Keywords
[lista de 10-15 palavras-chave]

### 📄 Descrição
[descrição completa formatada]

### 📋 Ficha Técnica
| Atributo | Valor |
|----------|-------|

### 📂 Categoria Sugerida
[categoria MLB mais específica]

### 🔮 Próximos Passos
1. [aplicar otimização via API do ML]
2. [monitorar posição no ranking após 24-48h]
3. [testar título alternativo com A/B testing]
4. [otimizar fotos do anúncio]
