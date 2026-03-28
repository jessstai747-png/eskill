# 🛍️ Análise de Qualidade - Serviços Mercado Livre (MCP)

> Análise especializada dos serviços de integração com a API do Mercado Livre usando MCP e Codacy

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura dos Serviços ML](#arquitetura-dos-serviços-ml)
3. [Análise Automatizada](#análise-automatizada)
4. [MCP Integration](#mcp-integration)
5. [Checklist de Qualidade](#checklist-de-qualidade)
6. [Troubleshooting](#troubleshooting)

---

## Visão Geral

O sistema **eskill.com.br** possui integração completa com a API do Mercado Livre através de **21 arquivos PHP** divididos em:

- **3 core services** (Client, Auth, Orchestrator)
- **14 specialized services** (Analytics, Pricing, SEO, Q&A, etc.)
- **4 DTOs e contracts**

### Stack de Integrações ML

```
app/Services/
├── MercadoLivreClient.php              # Client principal (HTTP, OAuth, retry, circuit breaker)
├── MercadoLivreAuthService.php          # Autenticação OAuth 2.0
├── MercadoLivreOrchestratorService.php  # Orquestrador de operações
└── MercadoLivre/                        # Serviços especializados
    ├── CategoriesApiService.php         # API de categorias
    ├── AdvancedPricingEngine.php        # Engine de precificação dinâmica
    ├── MLAnalyticsIntelligenceService.php # Analytics e métricas
    ├── SmartQAService.php               # Gerenciamento de perguntas
    ├── CompetitorIntelligenceService.php # Análise de concorrência
    ├── SEOMetricsCollectorService.php   # Coleta de métricas SEO
    ├── MercadoLivreAIIntegrationService.php # Integração IA
    ├── MLAdsAdvancedService.php         # Campanhas de anúncios
    ├── StockSyncService.php             # Sincronização de estoque
    └── ...
```

---

## Arquitetura dos Serviços ML

### 1. MercadoLivreClient (Core)

**Responsabilidades:**
- HTTP client com GuzzleHTTP
- OAuth 2.0 authentication & refresh token
- Rate limiting (evita banimento)
- Circuit breaker (protege contra API instável)
- Retry com exponential backoff
- Cache inteligente (público vs autenticado)

**Padrões críticos:**
```php
// ✅ BOM: Retry automático com exponential backoff
$response = $this->mlClient->get('/items/MLB123456', [], 300);

// ✅ BOM: Circuit breaker protege contra API fora do ar
if ($this->circuitBreaker->isOpen()) {
    throw new ServiceUnavailableException('ML API circuit breaker open');
}

// ❌ RUIM: Sem retry, sem cache, sem tratamento de erro
$data = file_get_contents('https://api.mercadolibre.com/items/MLB123456');
```

### 2. Specialized Services

Cada serviço especializado segue o padrão:
- **Single Responsibility** (uma funcionalidade bem definida)
- **Type-safe** (strict_types=1, type hints completos)
- **Error handling** (try/catch em todas operações de I/O)
- **Logging** (Monolog para rastreabilidade)

---

## Análise Automatizada

### Método 1: Script Shell Automatizado

```bash
# Tornar executável
chmod +x analyze-mercadolivre-services.sh

# Executar análise completa
./analyze-mercadolivre-services.sh
```

**O que faz:**
- ✅ Analisa **21 arquivos PHP** de integração ML
- ✅ Executa **phpcs** (PSR compliance)
- ✅ Executa **phpmd** (code quality)
- ✅ Executa **trivy** (security vulnerabilities)
- ✅ Gera relatórios JSON em `storage/codacy-analysis/mercadolivre/`
- ✅ Conta issues por severidade (Error, Warning, Info)
- ✅ Exit code 1 se critical issues encontrados

**Output esperado:**
```
╔════════════════════════════════════════════════════════════╗
║   🔍 Análise de Qualidade - Serviços Mercado Livre        ║
╚════════════════════════════════════════════════════════════╝

📂 Diretório raiz: /home/eskill/htdocs/eskill.com.br
🔧 Codacy CLI: /home/eskill/htdocs/eskill.com.br/.codacy/cli.sh
📊 Relatórios em: storage/codacy-analysis/mercadolivre

📋 Arquivos a serem analisados: 21

🔍 Analisando: app/Services/MercadoLivreClient.php
  [phpcs] ✅ Nenhum issue
  [phpmd] ⚠️  0 erros, 2 warnings, 0 info
  [trivy] ✅ Nenhum issue
  📊 TOTAL: 0 erros, 2 warnings, 0 info

...

╔════════════════════════════════════════════════════════════╗
║                    📊 RESUMO DA ANÁLISE                    ║
╚════════════════════════════════════════════════════════════╝

📂 Arquivos analisados: 21
⚠️  Arquivos com issues: 5

❌ Total de ERROS: 0
⚠️  Total de WARNINGS: 12
ℹ️  Total de INFO: 3

✅ ANÁLISE CONCLUÍDA - CÓDIGO LIMPO!
```

### Método 2: MCP Tools (via Copilot/Claude)

**⚠️ IMPORTANTE:** Ferramentas MCP podem estar bloqueadas por erro WSL. Se disponíveis, use:

```typescript
// Analisar MercadoLivreClient.php
mcp_codacy_codacy_codacy_cli_analyze({
  rootPath: "/home/eskill/htdocs/eskill.com.br",
  file: "app/Services/MercadoLivreClient.php",
  tool: "phpcs" // ou "phpmd", ou "" para todos
});

// Analisar service específico
mcp_codacy_codacy_codacy_cli_analyze({
  rootPath: "/home/eskill/htdocs/eskill.com.br",
  file: "app/Services/MercadoLivre/AdvancedPricingEngine.php",
  tool: ""
});
```

### Método 3: Análise Manual com CLI

```bash
# Analisar um arquivo específico
./.codacy/cli.sh analyze \
  --tool phpcs \
  --file app/Services/MercadoLivreClient.php \
  --format json

# Analisar toda a pasta MercadoLivre/
./.codacy/cli.sh analyze \
  --tool phpmd \
  --directory app/Services/MercadoLivre \
  --format json
```

---

## MCP Integration

### Configuração Automática

O projeto já possui configuração MCP em `.github/instructions/codacy.instructions.md`:

```markdown
## CRITICAL: After ANY successful `edit_file` operation in MercadoLivre services
- YOU MUST IMMEDIATELY run `codacy_cli_analyze` tool
- If any issues found, propose and apply fixes
```

### Ferramentas MCP Disponíveis

| Ferramenta | Descrição | Uso |
|------------|-----------|-----|
| `mcp_codacy_codacy_codacy_cli_analyze` | Analisa arquivo específico | Após editar `MercadoLivreClient.php` |
| `mcp_codacy_codacy_codacy_get_pattern` | Detalhes de pattern específico | Entender issue específico |
| `mcp_codacy_codacy_codacy_list_tools` | Lista engines disponíveis | Ver ferramentas suportadas |

### Troubleshooting MCP

**Erro:** `MPC 4294967295: Command failed: wsl --status`

**Solução:** Use fallback manual:
```bash
# Usar script automatizado
./analyze-mercadolivre-services.sh

# OU CLI direto
./.codacy/cli.sh analyze --file app/Services/MercadoLivreClient.php
```

Consulte [QUALITY_ANALYSIS.md](../QUALITY_ANALYSIS.md) para troubleshooting completo de MCP.

---

## Checklist de Qualidade

### ✅ Code Quality (PHPCS + PHPMD)

- [ ] **PSR-12 compliance** — Código segue PSR-12?
- [ ] **Type hints completos** — Todos parâmetros e retornos tipados?
- [ ] **Strict types** — `declare(strict_types=1)` presente?
- [ ] **Docblocks** — Classes e métodos públicos documentados?
- [ ] **Naming conventions** — PascalCase para classes, camelCase para métodos?
- [ ] **Complexity** — Cyclomatic complexity < 10?
- [ ] **DRY** — Sem código duplicado?

### 🔒 Security (Trivy + Manual)

- [ ] **Sem secrets hardcoded** — Tokens/senhas vem de .env?
- [ ] **Validação de input** — Parâmetros validados antes de uso?
- [ ] **SQL injection** — Queries usam prepared statements?
- [ ] **XSS protection** — Output sanitizado?
- [ ] **CSRF protection** — Endpoints POST protegidos?
- [ ] **Rate limiting** — API calls têm rate limit?
- [ ] **Dependencies** — Vulnerabilidades em composer.json?

### 🚀 Performance

- [ ] **N+1 queries** — Evitado com eager loading?
- [ ] **Cache** — Operações caras têm cache?
- [ ] **Lazy loading** — Recursos carregados sob demanda?
- [ ] **Database queries** — Índices otimizados?
- [ ] **HTTP calls** — Timeout configurado?
- [ ] **Memory usage** — Grandes datasets paginados?

### 🛠️ Best Practices ML API

- [ ] **OAuth refresh** — Refresh token implementado?
- [ ] **Circuit breaker** — Proteção contra API instável?
- [ ] **Retry logic** — Exponential backoff implementado?
- [ ] **Error handling** — Try/catch em todas chamadas HTTP?
- [ ] **Logging** — Monolog registrando requests/responses?
- [ ] **Webhook validation** — Assinatura verificada?
- [ ] **Pagination** — Endpoints paginados corretamente?

---

## Troubleshooting

### Problema: "PHPCS não encontrado"

**Solução:**
```bash
# Instalar dependências
composer install

# Verificar instalação
vendor/bin/phpcs --version
```

### Problema: "Codacy CLI não encontrado"

**Solução:**
```bash
# Instalar via script
./install-codacy-cli.sh

# OU verificar wrapper
ls -la .codacy/cli.sh
```

### Problema: "Muitos warnings PHPMD"

**Contexto:** Warnings de complexidade ciclomática são comuns em services grandes.

**Soluções:**
1. **Refatorar:** Quebrar métodos grandes em métodos menores
2. **Extrair:** Criar private methods para lógica repetida
3. **Patterns:** Usar Strategy/Command patterns para reduzir if/else

### Problema: "Trivy encontrou vulnerabilidades"

**Como resolver:**
```bash
# Ver detalhes
cat storage/codacy-analysis/mercadolivre/trivy-security-scan*.json | jq

# Atualizar dependências
composer update

# Re-analisar
./analyze-mercadolivre-services.sh
```

---

## Próximos Passos

1. **Executar análise inicial:**
   ```bash
   ./analyze-mercadolivre-services.sh
   ```

2. **Revisar relatórios:**
   ```bash
   cd storage/codacy-analysis/mercadolivre
   ls -lh
   cat MercadoLivreClient_phpmd_*.json | jq
   ```

3. **Corrigir issues críticos:**
   - Priorizar: Security > Errors > Warnings > Info
   - Focar em: Secrets, SQL injection, Type safety

4. **Integrar em CI/CD:**
   - Adicionar `./analyze-mercadolivre-services.sh` em pipeline
   - Bloquear merge se critical issues

5. **Automatizar via MCP:**
   - Quando WSL for corrigido, habilitar análise automática
   - Configurar pre-commit hook

---

## 📚 Referências

- [Codacy CLI Docs](https://docs.codacy.com/v4.0/codacy-cli/)
- [PHP PSR-12](https://www.php-fig.org/psr/psr-12/)
- [PHPMD Rules](https://phpmd.org/rules/index.html)
- [Mercado Livre API Docs](https://developers.mercadolibre.com.ar/)
- [Circuit Breaker Pattern](https://martinfowler.com/bliki/CircuitBreaker.html)

---

**Última atualização:** 2026-03-24
**Autor:** GitHub Copilot
**Projeto:** eskill.com.br — SEO Optimizer para Mercado Livre
