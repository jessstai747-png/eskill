# Change: Implement SEO Hidden Fields & Search Coverage (Phase 4)

## Why
A fase 4 melhora a indexação e cobertura de buscas ao preencher campos ocultos e medir gaps de pesquisa, aumentando alcance e precisão do SEO nas categorias.

## What Changes
- Expandir `HiddenAttributesDetector` para sugerir/gerar campos KEYWORDS, MPN e LINE.
- Criar `SearchCoverageService` para analisar cobertura e sugerir melhorias.
- Criar `CompatibilityService` para listar compatibilidades por categoria e gerar textos.
- Expor endpoints de API para campos ocultos, cobertura e compatibilidade.
- Adicionar testes unitários para os novos serviços.

## Impact
- **Capability**: SEO (campos ocultos, cobertura de busca, compatibilidade)
- **Services**: `HiddenAttributesDetector`, `SearchCoverageService`, `CompatibilityService`
- **API**: endpoints de campos ocultos, cobertura e compatibilidade
- **Tests**: novos testes unitários de SEO
