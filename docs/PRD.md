# PRD — eskill.com.br: SEO Optimizer para Mercado Livre

> **Produto:** eskill.com.br
> **Empresa:** AWA Motos — Distribuidora de peças para motos (Araraquara, SP, Brasil)
> **Responsável:** Jess
> **Versão:** 1.0
> **Data:** 2026-04-06
> **Stack:** PHP 8.0+ | MySQL/PDO | Redis | Guzzle 7 | Monolog 3 | PHPUnit 9

---

## Índice

1. [Visão Geral do Produto](#1-visão-geral-do-produto)
2. [Objetivos de Negócio](#2-objetivos-de-negócio)
3. [Usuários e Stakeholders](#3-usuários-e-stakeholders)
4. [Arquitetura e Infraestrutura](#4-arquitetura-e-infraestrutura)
5. [Módulo 01 — Autenticação e Acesso](#módulo-01--autenticação-e-acesso)
6. [Módulo 02 — Dashboard](#módulo-02--dashboard)
7. [Módulo 03 — SEO e Otimização de Anúncios](#módulo-03--seo-e-otimização-de-anúncios)
8. [Módulo 04 — Clonagem de Catálogo](#módulo-04--clonagem-de-catálogo)
9. [Módulo 05 — Precificação Dinâmica](#módulo-05--precificação-dinâmica)
10. [Módulo 06 — Inteligência Artificial](#módulo-06--inteligência-artificial)
11. [Módulo 07 — Análise de Concorrentes](#módulo-07--análise-de-concorrentes)
12. [Módulo 08 — Gestão de Anúncios e Itens](#módulo-08--gestão-de-anúncios-e-itens)
13. [Módulo 09 — Gestão de Estoque](#módulo-09--gestão-de-estoque)
14. [Módulo 10 — Pedidos e Pós-Venda](#módulo-10--pedidos-e-pós-venda)
15. [Módulo 11 — Frete e Logística](#módulo-11--frete-e-logística)
16. [Módulo 12 — Relatórios e Exportação](#módulo-12--relatórios-e-exportação)
17. [Módulo 13 — Notificações e Alertas](#módulo-13--notificações-e-alertas)
18. [Módulo 14 — Monitoramento e Saúde da Conta](#módulo-14--monitoramento-e-saúde-da-conta)
19. [Módulo 15 — Raio X da Conta (X-Ray)](#módulo-15--raio-x-da-conta-x-ray)
20. [Módulo 16 — Governança de Conta](#módulo-16--governança-de-conta)
21. [Módulo 17 — Segurança](#módulo-17--segurança)
22. [Módulo 18 — Webhooks e Integrações Externas](#módulo-18--webhooks-e-integrações-externas)
23. [Módulo 19 — Publicidade (Ads)](#módulo-19--publicidade-ads)
24. [Módulo 20 — Marca e Posicionamento](#módulo-20--marca-e-posicionamento)
25. [Módulo 21 — Multi-Conta](#módulo-21--multi-conta)
26. [Módulo 22 — Finanças e Faturamento](#módulo-22--finanças-e-faturamento)
27. [Módulo 23 — Marketplaces Alternativos](#módulo-23--marketplaces-alternativos)
28. [Módulo 24 — Infraestrutura e Core](#módulo-24--infraestrutura-e-core)
29. [Módulo 25 — Workers e Jobs em Background](#módulo-25--workers-e-jobs-em-background)
30. [Módulo 26 — Testes e Qualidade](#módulo-26--testes-e-qualidade)
31. [Métricas de Sucesso](#31-métricas-de-sucesso)
32. [Restrições e Riscos](#32-restrições-e-riscos)

---

## 1. Visão Geral do Produto

O **eskill.com.br** é uma plataforma SaaS de automação e otimização SEO para vendedores do Mercado Livre. Desenvolvida para a AWA Motos, a plataforma automatiza e potencializa todas as etapas do ciclo de vida de um anúncio: criação, otimização de conteúdo, precificação competitiva, gestão de estoque, análise de concorrentes, integrações com IA e relatórios financeiros.

O produto consolida, em uma única interface, operações que normalmente exigem múltiplas ferramentas, equipes e processos manuais, reduzindo o custo operacional e aumentando a performance de vendas no marketplace.

### Escopo do Produto

- **Canal principal:** Mercado Livre (API: api.mercadolibre.com)
- **Canais secundários:** Shopee, Amazon (em expansão)
- **Público-alvo:** Vendedores profissionais e distribuidoras com operação ativa no Mercado Livre
- **Modelo de uso:** Plataforma web + workers em background + crons automatizados

---

## 2. Objetivos de Negócio

| Objetivo | Indicador de Sucesso |
|----------|---------------------|
| Aumentar visibilidade dos anúncios AWA no ML | Subida de posição nas buscas por keywords-alvo |
| Reduzir tempo de gestão operacional de anúncios | Automatizar 80%+ das edições manuais |
| Manter preços competitivos em tempo real | Diferença de preço ≤ 2% vs. melhor concorrente |
| Maximizar cobertura de catálogo em múltiplas contas | Clonagem automatizada com SEO aplicado |
| Aumentar taxa de conversão via otimização de conteúdo | CTR e taxa de conversão por anúncio com tendência positiva |
| Reduzir reclamações e problemas pós-venda | Volume de claims e devoluções abaixo da média do segmento |
| Consolidar dados financeiros da operação ML | Relatórios de margem, comissão e PnL gerados automaticamente |

---

## 3. Usuários e Stakeholders

| Perfil | Papel | Necessidades Primárias |
|--------|-------|----------------------|
| **Operador de e-commerce (AWA Motos)** | Usuário principal | Otimização rápida de títulos, clonagem de catálogo, resposta a perguntas |
| **Financeiro** | Usuário secundário | Relatórios de comissão, margem, PnL, conciliação |
| **Gestor de marketing** | Usuário secundário | Analytics de performance, posicionamento de marca, pricing |
| **TI / DevOps** | Admin | Saúde dos workers, tokens, logs, segurança |
| **APIs externas** | Integrações | Mercado Livre, Anthropic (Claude), OpenAI, Brevo, WhatsApp |

---

## 4. Arquitetura e Infraestrutura

### Stack Tecnológica

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.0+ com strict_types, PSR-4 |
| HTTP Client | Guzzle 7 |
| Banco de dados | MySQL via PDO |
| Cache | Redis (ext-redis) |
| Logging | Monolog 3 |
| PDF | DomPDF 3 |
| Email | PHPMailer 7 |
| Push | minishlink/web-push 10 |
| Env | vlucas/phpdotenv 5 |
| Testes | PHPUnit 9 + Faker |
| E2E | Playwright + TypeScript |
| Containerização | Docker + Docker Compose |
| NLP | Microserviço Python (`ml-nlp-service/`) |

### Padrão Arquitetural

```
HTTP Request
    │
    ▼
Router (app/Router.php)
    │
    ▼
Middleware Pipeline
(Auth, CSRF, RateLimit, Security, Cache, Performance)
    │
    ▼
Controller (app/Controllers/)
    │
    ▼
Service (app/Services/)
    │
    ├── Model (app/Models/) → MySQL/PDO
    └── External API (Guzzle) → Mercado Livre API
```

### Rotas

| Arquivo | Nº de Rotas | Conteúdo |
|---------|------------|---------|
| `app/Routes/api.php` | 1224 | REST APIs completas |
| `app/Routes/web.php` | 323 | Dashboard e views |
| `app/Routes/auth.php` | 29 | Autenticação e OAuth |
| `app/Routes/webhooks.php` | 3 | Webhooks ML |
| `app/Routes/fase8_routes.php` | 13 | Rotas adicionais |
| **Total** | **1592** | |

### Banco de Dados

- **99 arquivos de migration** em `database/migrations/`
- Instalação base via `000_install_all.sql`
- Migrations individuais: `php bin/migrate.php`

---

## Módulo 01 — Autenticação e Acesso

### Descrição

Gerencia toda a autenticação de usuários do sistema (login/senha, 2FA, tokens JWT) e a autenticação OAuth 2.0 com o Mercado Livre, incluindo refresh automático de tokens e monitoramento de falhas.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| AUTH-001 | Login com email/senha e manutenção de sessão PHP segura | Alta |
| AUTH-002 | OAuth 2.0 com Mercado Livre — obtenção de `access_token` e `refresh_token` | Alta |
| AUTH-003 | Auto refresh de `access_token` em background antes da expiração | Alta |
| AUTH-004 | Autenticação em dois fatores (2FA) via TOTP | Média |
| AUTH-005 | Diagnóstico e hardening da conexão OAuth ML | Média |
| AUTH-006 | Monitor de falhas de autenticação com alertas | Média |
| AUTH-007 | Password reset seguro com tokens de uso único com expiração | Alta |
| AUTH-008 | JWT para autenticação de APIs externas | Média |
| AUTH-009 | Geração e gestão de tokens de acesso para integrações externas | Média |

### Requisitos Não Funcionais

- Tokens OAuth armazenados de forma criptografada no banco
- Refresh automático executado a cada 5 minutos via worker
- Sessão PHP com regeneração de ID após login (prevenção de session fixation)
- Rate limiting no endpoint de login: máximo 10 tentativas por IP/minuto

### Arquivos Principais

`app/Controllers/AuthController.php`, `app/Services/AuthService.php`, `app/Services/MercadoLivreAuthService.php`, `app/Services/RefreshTokenService.php`, `app/Services/UnifiedTokenRefreshService.php`, `app/Services/TwoFactorService.php`, `app/Services/JwtService.php`, `bin/auto-token-refresh-worker.php`

---

## Módulo 02 — Dashboard

### Descrição

Painel centralizado com visão completa da operação: métricas de vendas, saúde da conta, status de tokens OAuth, analytics de performance e estatísticas gerais do sistema em tempo real.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| DASH-001 | Dashboard principal com KPIs de vendas, anúncios ativos e saúde da conta | Alta |
| DASH-002 | Token Dashboard — status e expiração de todos os tokens OAuth | Alta |
| DASH-003 | API do dashboard para atualização de dados em tempo real (polling/SSE) | Alta |
| DASH-004 | Tela de estatísticas gerais: anúncios processados pelo SEO, workers ativos | Média |
| DASH-005 | Analytics de vendas: receita, volume, ticket médio, tendências | Média |

### Requisitos Não Funcionais

- Dashboard principal deve carregar em menos de 2 segundos
- Dados críticos (vendas, saúde) atualizados a cada 60 segundos no máximo
- Interface responsiva, compatível com desktop e tablet

### Arquivos Principais

`app/Controllers/DashboardController.php`, `app/Services/DashboardService.php`, `app/Controllers/DashboardApiController.php`, `app/Controllers/TokenDashboardController.php`, `app/Views/dashboard/index.php`

---

## Módulo 03 — SEO e Otimização de Anúncios

### Descrição

Motor principal de otimização SEO para o Mercado Livre. Diferentemente do SEO web tradicional, o foco é maximizar a relevância de títulos, descrições e atributos para o algoritmo de busca do ML. Inclui pesquisa de keywords, análise de gaps, otimização com IA, operações em lote e monitoramento de performance.

### Requisitos Funcionais

#### 3.1 SEO Killer (Motor Principal)

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| SEO-001 | Otimização de títulos com keywords relevantes dentro do limite de 60 caracteres do ML | Alta |
| SEO-002 | Geração de títulos otimizados usando IA (Claude/GPT) com aprendizado de contexto do produto | Alta |
| SEO-003 | Otimização de descrições de anúncios com IA, mantendo conformidade com políticas do ML | Alta |
| SEO-004 | Pesquisa e mineração de keywords: volume de busca, competitividade, long-tail | Alta |
| SEO-005 | Análise de cobertura SEO — identificação de gaps e oportunidades por categoria | Alta |
| SEO-006 | Expansão automática de sinônimos para enriquecimento semântico de títulos | Média |
| SEO-007 | Bulk SEO — aplicação de otimizações em lotes de até centenas de anúncios simultaneamente | Alta |
| SEO-008 | SEO Performance tracking — monitoramento de posicionamento e rankings por keyword | Alta |
| SEO-009 | API REST para otimização SEO programática via endpoints externos | Média |
| SEO-010 | Estratégias SEO avançadas — templates por categoria, regras de negócio personalizadas | Média |

#### 3.2 Ficha Técnica (Atributos)

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| TS-001 | Auto-otimizador de ficha técnica — preenchimento inteligente de atributos faltantes | Alta |
| TS-002 | Batch optimizer — otimização em lote de fichas técnicas | Alta |
| TS-003 | Smart gap filler — preenchimento automático de campos obrigatórios e recomendados | Alta |
| TS-004 | Relatórios diários de fichas técnicas por email | Média |
| TS-005 | Scheduler de otimizações de ficha técnica | Média |
| TS-006 | Analytics de fichas: % de completude, impacto no ranking | Média |
| TS-007 | Integração SEO-Ficha: preenchimento de atributos com keywords relevantes | Alta |
| TS-008 | Export de fichas técnicas em CSV/Excel | Baixa |
| TS-009 | Benchmark de fichas vs. concorrentes | Baixa |
| TS-010 | Gráficos de evolução da completude de fichas ao longo do tempo | Baixa |
| TS-011 | Alertas de ficha técnica com dados críticos faltantes | Média |

### Requisitos Não Funcionais

- Títulos gerados respeitam o limite de 60 caracteres do ML
- Otimização bulk processada assincronamente (sem timeout HTTP)
- Cache de keywords por categoria para reduzir chamadas à API ML
- Análise semântica via NLP (microserviço Python integrado)

### Arquivos Principais

`app/Controllers/SEOKillerController.php`, `app/Services/SEO/`, `app/Controllers/TitleGeneratorController.php`, `app/Services/BulkSEOService.php`, `app/Services/KeywordResearchService.php`, `app/Services/GapHunterService.php`, `bin/bulk-seo-worker.php`

---

## Módulo 04 — Clonagem de Catálogo

### Descrição

Permite clonar anúncios de um vendedor do ML para outra conta, com personalização de conteúdo, aplicação de SEO pós-clone, templates por categoria, sincronização contínua e análise de ROI dos clones. Central para estratégias de expansão de catálogo.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| CLONE-001 | Clonagem de catálogo completo entre contas ML | Alta |
| CLONE-002 | Clone avançado com templates de conteúdo e customização por categoria | Alta |
| CLONE-003 | Clone A/B Testing — teste estatístico de variações de anúncios clonados | Média |
| CLONE-004 | Clone automação — agendamento e triggers para clonagem automática | Alta |
| CLONE-005 | Clone ROI — análise de retorno por anúncio clonado (receita, margem, performance) | Média |
| CLONE-006 | Clone analytics — dashboard de performance dos clones | Média |
| CLONE-007 | Clone health monitor — verificação de saúde e status dos itens clonados | Alta |
| CLONE-008 | Clone sync — sincronização contínua de preço, estoque e atributos entre contas | Alta |
| CLONE-009 | Wizard de clonagem em 4 passos: busca de vendedor → seleção de anúncios → configuração → execução com polling de progresso | Alta |
| CLONE-010 | Clonagem por seller-job: aceita seller ID, nickname ou URL e resolve para ML numeric ID | Alta |

### Fluxo do Wizard de Clonagem (CLONE-009)

```
Passo 1: Busca de vendedor (por ID, nickname ou URL do ML)
    │
    ▼
Passo 2: Browser de anúncios com filtros por categoria, facets, select-all
    │
    ▼
Passo 3: Configuração — normalização de opções, guardrails de compliance
    │
    ▼
Passo 4: Validação pré-execução → execução assíncrona → polling de progresso
```

### Requisitos Não Funcionais

- Clonagem executada em background via job queue (nunca bloqueia o browser)
- Detecção automática de duplicatas antes de criar novo anúncio
- Compliance com políticas do ML (proibições de cópia exata de descrições)
- Retry com backoff exponencial em falhas de API ML

### Arquivos Principais

`app/Services/CatalogCloneService.php`, `app/Controllers/CatalogCloneController.php`, `app/Services/CloneTemplateService.php`, `app/Services/CloneSyncService.php`, `app/Services/CloneHealthMonitorService.php`, `bin/catalog-clone-worker.php`, `app/Views/dashboard/catalog_clone_wizard.php`

---

## Módulo 05 — Precificação Dinâmica

### Descrição

Motor de precificação competitiva que monitora preços de concorrentes em tempo real e aplica regras de negócio automáticas para manter ou conquistar posições de mercado, com análise de margem, A/B testing e previsibilidade.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| PRICE-001 | Pricing dinâmico baseado em monitoramento de concorrência ativa | Alta |
| PRICE-002 | Auto pricing optimizer — ajuste automático de preços segundo regras pré-definidas | Alta |
| PRICE-003 | Rules engine — definição de regras de precificação (ex: "sempre 2% abaixo do menor") | Alta |
| PRICE-004 | Price history e analytics — histórico de variações e tendências de preço | Alta |
| PRICE-005 | Scheduled pricing — agendamento de mudanças de preço em datas/horários específicos | Média |
| PRICE-006 | Pricing intelligence — dashboard de monitoramento de concorrentes | Alta |
| PRICE-007 | Price A/B testing — comparar impacto de diferentes faixas de preço nas conversões | Média |
| PRICE-008 | Price notifications — alertas quando concorrente muda preço significativamente | Média |
| PRICE-009 | Cenários de precificação — simulação de impacto de diferentes estratégias | Baixa |
| PRICE-010 | Estratégias de precificação — configuração de postura (agressiva, conservadora, premium) | Média |
| PRICE-011 | Engine de precificação avançada com ML — predição de preço ótimo | Baixa |
| PRICE-012 | Calculadora de margem — análise de lucratividade considerando comissão ML + frete | Alta |
| PRICE-013 | Simulador de promoções — impacto de descontos na margem e competitividade | Média |
| PRICE-014 | Bulk price editor — edição em massa de preços com regras | Alta |

### Requisitos Não Funcionais

- Atualizações de preço em lote executadas assincronamente
- Cálculo de margem considera: preço de venda, comissão ML, frete, custo do produto
- Histórico de preços retido por mínimo 12 meses
- Regras do ML respeitadas (ex: sem preços abaixo do custo declarado)

### Arquivos Principais

`app/Controllers/DynamicPricingController.php`, `app/Services/DynamicPricingService.php`, `app/Services/PriceRulesEngineService.php`, `app/Services/AutoPricingOptimizerService.php`, `app/Services/PricingCompetitorMonitorService.php`, `app/Services/MarginCalculatorService.php`, `bin/pricing-worker.php`

---

## Módulo 06 — Inteligência Artificial

### Descrição

Infraestrutura de IA que integra múltiplos provedores (Claude, GPT-4, Gemini) com gestão de prompts, circuit breaker, cache, batch processing e aprendizado contínuo. Potencializa SEO, precificação, análise de imagens, geração de respostas e predições de mercado.

### Requisitos Funcionais

#### 6.1 Motor de IA e Provedores

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| AI-001 | Integração com Claude (Anthropic) para geração de conteúdo e análise | Alta |
| AI-001b | Integração com OpenAI GPT-4 como provedor alternativo | Alta |
| AI-001c | Integração com Google Gemini | Média |
| AI-002 | AI Center — dashboard centralizado de uso, custos e status dos provedores | Média |
| AI-003 | AI Predictions — previsão de vendas, demanda e tendências de mercado | Média |
| AI-004 | AI Queue — fila de processamento assíncrono com workers dedicados | Alta |
| AI-005 | AI Image Analyzer — análise de imagens de produtos para sugestão de melhorias | Média |
| AI-006 | Deep Research — pesquisa profunda de mercado com IA (concorrentes, categorias, tendências) | Média |
| AI-007 | Chatbot AI — assistente conversacional para suporte ao operador | Baixa |
| AI-008 | ML-AI Integration Pipeline — integração com circuit breaker e processamento em batches | Alta |
| AI-009 | ML-AI Version Management — comparação de resultados entre versões de prompts | Média |

#### 6.2 Respostas Automáticas com IA

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| AI-QA-001 | Geração automática de respostas a perguntas de compradores | Alta |
| AI-QA-002 | Análise de intenção da pergunta antes de gerar resposta | Alta |
| AI-QA-003 | Smart Q&A via LLM — respostas contextualizadas ao produto específico | Alta |

#### 6.3 Machine Learning (ML nativo)

| Componente | Função | Prioridade |
|-----------|--------|-----------|
| `CategoryLearningService` | Aprendizado de categorias e atributos do ML | Média |
| `DeepDemandPredictor` | Predição profunda de demanda por produto | Média |
| `KeywordClassifierService` | Classificação automática de keywords por relevância | Alta |
| `MarketTrendPredictor` | Predição de tendências de mercado | Média |
| `NLPIntegrationService` | Integração com microserviço Python NLP | Alta |
| `SynonymGenerator` | Geração de sinônimos por aprendizado | Média |

### Requisitos Não Funcionais

- Circuit breaker ativo para todos os provedores de IA (evitar falhas em cascata)
- Cache de respostas IA por hash de prompt (TTL configurável)
- Rate limiting respeitando limites de cada provedor
- Fallback automático entre provedores em caso de indisponibilidade
- Log de custo de tokens por operação

### Arquivos Principais

`app/Services/ClaudeClient.php`, `app/Services/AI/Providers/`, `app/Services/AI/Core/CircuitBreakerService.php`, `app/Services/AI/Core/AIProviderManager.php`, `app/Services/MercadoLivre/SmartQAService.php`, `app/Services/AI/ML/`, `bin/ai-worker.php`

---

## Módulo 07 — Análise de Concorrentes

### Descrição

Monitoramento contínuo de concorrentes no Mercado Livre: preços, posicionamento, pontos fortes e fracos, detecção de oportunidades e alertas de mudança de ranking.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| COMP-001 | Análise profunda de concorrentes — preços, posições, tendências, histórico | Alta |
| COMP-002 | Monitor contínuo de concorrentes com atualização periódica | Alta |
| COMP-003 | Competitor Intelligence com ML — identificação de padrões e oportunidades | Média |
| COMP-004 | Opportunity Detector — detecção automática de gaps de mercado e nichos | Média |
| COMP-005 | Ranking Alert — notificação imediata em caso de queda ou subida de posição própria | Alta |

### Requisitos Não Funcionais

- Dados de concorrentes atualizados no máximo a cada 1 hora
- Histórico de preços concorrentes retido por 90 dias
- Análise por categoria, não apenas por produto individual

### Arquivos Principais

`app/Controllers/CompetitorAnalysisController.php`, `app/Services/CompetitorAnalysisService.php`, `app/Services/MercadoLivre/CompetitorIntelligenceService.php`, `app/Services/OpportunityDetectorService.php`, `bin/competitor-monitor-worker.php`

---

## Módulo 08 — Gestão de Anúncios e Itens

### Descrição

CRUD completo de anúncios do ML com recursos avançados: edição em massa, criação guiada, gestão de EAN/GTIN, compatibilidade de peças automotivas, rastreamento de métricas por item e sincronização automática.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| ITEMS-001 | CRUD de anúncios: listar, buscar, editar, pausar, ativar, deletar | Alta |
| ITEMS-002 | Bulk editor — edição em lote de título, preço, estoque, descrição, atributos | Alta |
| ITEMS-003 | Listing Builder — wizard guiado para criação de novos anúncios otimizados | Alta |
| ITEMS-004 | Otimização de ficha técnica (atributos técnicos do produto) | Alta |
| ITEMS-005 | EAN/GTIN — gerenciamento de códigos de barras, validação e associação a itens | Alta |
| ITEMS-006 | Bulk Compatibility — cadastro em massa de compatibilidade de peças por modelo de moto | Alta |
| ITEMS-007 | Inventory Manager automático — gestão de estoque com regras de reposição | Alta |
| ITEMS-008 | Item Metrics — métricas individuais por anúncio (visitas, cliques, conversões) | Média |
| ITEMS-009 | Item Sync — sincronização automática com mudanças no catálogo ML | Alta |
| ITEMS-010 | Auto Answer — respostas automáticas a perguntas de compradores via IA | Alta |
| ITEMS-011 | Attribute Suggestion — sugestão inteligente de atributos faltantes | Média |
| ITEMS-012 | Hidden Attributes Detector — identifica atributos ocultos que impactam SEO | Média |
| ITEMS-013 | Listing Auto Creator — criação automática de anúncios a partir de dados estruturados | Média |

### Requisitos Não Funcionais

- Edições bulk aplicam-se via API ML com controle de rate limit
- EAN validado contra base GTIN antes de enviar ao ML
- Compatibilidade de peças segue padrão de dados do ML por modelo/ano de moto

### Arquivos Principais

`app/Controllers/ItemController.php`, `app/Services/ItemService.php`, `app/Controllers/BulkEditorController.php`, `app/Controllers/ListingBuilderController.php`, `app/Controllers/EanController.php`, `app/Services/EanService.php`, `app/Controllers/BulkCompatibilityController.php`

---

## Módulo 09 — Gestão de Estoque

### Descrição

Sincronização de estoques entre o sistema e o Mercado Livre, incluindo envios (Mercado Envios), inventário por EAN e integração com fluxo de pedidos.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| STOCK-001 | Stock Sync — sincronização bidirecional de estoque entre sistema e ML | Alta |
| STOCK-002 | Inventory Service — gestão centralizada de inventário | Alta |
| STOCK-003 | Shipment Sync — sincronização de status de envios | Alta |
| STOCK-004 | EAN Inventory — controle de estoque por código EAN | Média |

### Requisitos Não Funcionais

- Estoque sincronizado a cada 30 minutos via worker
- Alerta quando estoque atingir nível mínimo configurado
- Não é permitido vender produto com estoque zero no ML (bloqueio automático)

### Arquivos Principais

`app/Controllers/StockSyncController.php`, `app/Services/InventoryService.php`, `app/Services/ShipmentSyncService.php`, `app/Models/EanInventory.php`, `bin/stock-sync-worker.php`, `bin/shipments-sync-worker.php`

---

## Módulo 10 — Pedidos e Pós-Venda

### Descrição

Gerenciamento completo do ciclo pós-venda: pedidos, mensagens com compradores, perguntas e respostas, reclamações, devoluções, conciliação financeira e negociação.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| ORDERS-001 | Gerenciamento de pedidos — lista, detalhes, status, histórico | Alta |
| ORDERS-002 | Perguntas e respostas automáticas com IA contextualizada ao produto | Alta |
| ORDERS-003 | Mensagens com compradores — inbox centralizado com histórico | Alta |
| ORDERS-004 | Claims — gestão de reclamações e mediações ML | Alta |
| ORDERS-005 | Returns — gestão de devoluções e logística reversa | Alta |
| ORDERS-006 | Settlement — conciliação financeira de pedidos (crédito ML vs. pedido real) | Alta |
| ORDERS-007 | Negociação — interface para negociar preço/condições com compradores | Média |
| ORDERS-008 | Order Audit ML — auditoria de pedidos com detecção de anomalias | Média |

### Requisitos Não Funcionais

- Pedidos sincronizados a cada 15 minutos
- Perguntas respondidas automaticamente em até 30 minutos quando ação não é requerida manualmente
- SLA de resposta a claims dentro dos prazos do ML (máximo 48h)

### Arquivos Principais

`app/Controllers/OrderController.php`, `app/Services/OrderService.php`, `app/Controllers/QuestionController.php`, `app/Services/QuestionService.php`, `app/Controllers/MessagingController.php`, `app/Controllers/ClaimsController.php`, `app/Services/ClaimsService.php`, `app/Controllers/ReturnsController.php`, `bin/orders-sync-worker.php`

---

## Módulo 11 — Frete e Logística

### Descrição

Cálculo, simulação e otimização de frete utilizando o Mercado Envios e o Mercado Envios Flex, com calculadora de dimensões e análise do custo logístico na margem.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| SHIP-001 | Cálculo e gestão de frete via Mercado Envios | Alta |
| SHIP-002 | Simulador de frete — simula custo por CEP/produto antes de publicar | Alta |
| SHIP-003 | Otimizador de frete — sugere embalagem e dimensões para menor custo | Média |
| SHIP-004 | Calculadora de dimensões — calcula custo cúbico vs. real | Média |
| SHIP-005 | Custo de frete no P&L — integração do custo de frete nos relatórios financeiros | Alta |
| SHIP-006 | Flex — gestão de entregas próprias via Mercado Envios Flex | Média |

### Arquivos Principais

`app/Controllers/ShippingController.php`, `app/Services/ShippingService.php`, `app/Services/Shipping/ShippingSimulatorService.php`, `app/Services/Shipping/ShippingOptimizerService.php`, `app/Controllers/FlexController.php`

---

## Módulo 12 — Relatórios e Exportação

### Descrição

Geração de relatórios completos de performance, financeiro, SEO e operacional com exportação em PDF, CSV e Excel, além de envio automático por email e notificações programadas.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| REPORT-001 | Relatórios avançados — vendas, performance de anúncios, ROI por categoria | Alta |
| REPORT-002 | Relatórios financeiros — comissões ML, taxas, margem bruta e líquida | Alta |
| REPORT-003 | Export de dados — PDF (DomPDF), CSV, Excel | Alta |
| REPORT-004 | Relatórios automáticos enviados por email/push em periodicidade configurável | Média |
| REPORT-005 | X-Ray PDF Export — relatório completo de diagnóstico da conta | Média |
| REPORT-006 | Settlement Report — relatório de liquidação financeira ML | Alta |
| REPORT-007 | PnL Report — demonstrativo de resultado (receita, custos, margem) | Alta |
| REPORT-008 | Financial Forecast — previsão de receita e margem para os próximos períodos | Média |
| REPORT-009 | Relatório de custos de IA — consumo de tokens por operação e custo em R$ | Baixa |

### Requisitos Não Funcionais

- PDFs gerados de forma assíncrona para relatórios grandes
- Relatórios automáticos via worker com agendamento configurável
- Dados exportados sem informações sensíveis de autenticação

### Arquivos Principais

`app/Controllers/AdvancedReportController.php`, `app/Services/ReportService.php`, `app/Controllers/FinancialReportController.php`, `app/Services/FinancialService.php`, `app/Controllers/ExportController.php`, `app/Services/PdfService.php`, `app/Services/Financial/PnlReportService.php`, `bin/automated-reports-worker.php`

---

## Módulo 13 — Notificações e Alertas

### Descrição

Sistema multicanal de notificações e alertas: push web, email, WhatsApp, Telegram, Slack/Discord e SSE em tempo real, para manter o operador informado sobre eventos críticos da operação.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| NOTIF-001 | Push notifications web — alertas em tempo real no browser (Web Push API) | Alta |
| NOTIF-002 | Real-time notifications via SSE/polling — sem necessidade de reload | Alta |
| NOTIF-003 | Email — notificações transacionais e relatórios via PHPMailer + Brevo | Alta |
| NOTIF-004 | WhatsApp alerts — alertas críticos via WhatsApp Business API | Média |
| NOTIF-005 | Telegram alerts — notificações via bot Telegram | Média |
| NOTIF-006 | Brevo (Sendinblue) integration — templates de email avançados | Média |
| NOTIF-007 | Monitoring alert notifications — alertas de saúde do sistema | Alta |
| NOTIF-008 | EAN notifications — alertas sobre EAN com problemas ou expirados | Média |
| NOTIF-009 | Slack/Discord notifications para eventos de clonagem | Baixa |

### Requisitos Não Funcionais

- Notificações críticas (token expirado, pedido com problema) entregues em até 5 minutos
- Sistema de notificações tolerante a falhas — não deve impactar o core business se falhar
- Opt-in por tipo de notificação e canal

### Arquivos Principais

`app/Controllers/PushController.php`, `app/Services/PushNotificationService.php`, `app/Controllers/RealTimeNotificationController.php`, `app/Services/EmailService.php`, `app/Services/WhatsAppService.php`, `app/Services/TelegramService.php`, `app/Services/Integrations/Brevo/`

---

## Módulo 14 — Monitoramento e Saúde da Conta

### Descrição

Monitoramento contínuo da saúde da conta no Mercado Livre (reputação, termômetro, indicadores), saúde do sistema (errors, performance), observabilidade da integração e alertas proativos.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| HEALTH-001 | Account Health — monitoramento de reputação, termômetro e indicadores da conta ML | Alta |
| HEALTH-002 | Error Monitoring — rastreamento centralizado de erros com alertas | Alta |
| HEALTH-003 | Health endpoint `/health` — status do servidor para load balancers e uptime monitors | Alta |
| MON-001 | ML Observability — observabilidade da integração com a API ML (latência, erros, rate limit) | Alta |
| MON-002 | Performance metrics — tempo de resposta, uso de memória, throughput | Média |
| MON-003 | Advanced Monitoring — dashboards avançados de monitoramento | Média |
| MON-004 | Token health monitor — verificação de validade de todos os tokens OAuth | Alta |
| MON-005 | ML health check CLI — diagnóstico completo da integração ML via linha de comando | Média |
| MON-006 | AwaSellerAlerts — alertas específicos de análise de vendedores AWA | Média |

### Arquivos Principais

`app/Controllers/AccountHealthController.php`, `app/Services/AccountHealthService.php`, `app/Controllers/ErrorMonitoringController.php`, `app/Controllers/HealthController.php`, `app/Controllers/MlObservabilityController.php`, `bin/token-health-monitor.php`, `bin/ml-health-check.php`

---

## Módulo 15 — Raio X da Conta (X-Ray)

### Descrição

Diagnóstico completo e automatizado da conta ML: análise de saúde, score de performance, identificação de problemas, plano de recuperação com ações concretas e acompanhamento de melhoria ao longo do tempo.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| XRAY-001 | Análise X-Ray completa — score de saúde da conta, problemas identificados e plano de ação | Alta |
| XRAY-002 | MercadoPago Account Service — análise de saúde financeira e reputação no MP | Alta |
| XRAY-003 | API REST de X-Ray — endpoints para acionar diagnóstico e obter resultados | Alta |
| XRAY-004 | Dashboard Raio X — interface visual completa com gráficos e pontuações | Alta |
| XRAY-005 | Migration de banco para armazenamento de relatórios X-Ray | Alta |
| XRAY-006 | AccountRecoveryApplierService — aplica automaticamente ações do plano de recuperação | Média |
| XRAY-007 | X-Ray Background Worker — execução assíncrona do diagnóstico | Alta |
| XRAY-008 | X-Ray Scheduler — agendamento diário automático do diagnóstico (2h AM) | Alta |
| XRAY-009 | Apply Recovery Plan API — endpoint `POST /api/xray/apply/{id}` | Média |
| XRAY-010 | Async Job Queue para diagnósticos sob demanda | Alta |
| XRAY-011 | Apply Recovery Plan UI — modal de aplicação de plano de recuperação no dashboard | Média |
| XRAY-012 | X-Ray PDF Export — relatório completo para download e compartilhamento | Média |

### Arquivos Principais

`app/Services/AccountXRayService.php`, `app/Controllers/AccountXRayController.php`, `app/Services/MercadoPagoAccountService.php`, `app/Services/AccountRecoveryApplierService.php`, `app/Views/dashboard/account-xray.php`, `bin/xray-worker.php`, `bin/xray-scheduler.php`

---

## Módulo 16 — Governança de Conta

### Descrição

Motor de governança que analisa a conta ML de forma holística, detecta riscos de suspensão ou rebaixamento de reputação e aplica ações corretivas proativas.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| GOV-001 | AccountGovernanceService — motor principal de governança e plano de recuperação | Alta |
| GOV-002 | AccountGovernanceIntegrationService — coleta de dados reais via API ML | Alta |
| GOV-003 | Governance Diagnostic Worker — execução de diagnósticos periódicos | Alta |

### Arquivos Principais

`app/Services/AccountGovernanceService.php`, `app/Controllers/AccountGovernanceController.php`, `app/Services/MercadoLivre/AccountGovernanceIntegrationService.php`, `bin/governance-diagnostic-worker.php`

---

## Módulo 17 — Segurança

### Descrição

Implementação completa de controles de segurança: validação de inputs, rate limiting, criptografia, CSRF, headers de segurança, auditoria, gestão de IPs e conformidade com OWASP Top 10.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| SEC-001 | Validação e sanitização de inputs em todos os endpoints | Crítica |
| SEC-002 | Rate limiting global — proteção contra abuso de API | Crítica |
| SEC-003 | Encryption service — criptografia de dados sensíveis (tokens, credenciais) | Crítica |
| SEC-004 | Audit log — registro imutável de todas as ações sensíveis por usuário | Alta |
| SEC-005 | CSRF protection — tokens CSRF em todos os formulários e requests mutantes | Crítica |
| SEC-006 | Security headers — CSP, HSTS, X-Frame-Options, etc. | Alta |
| SEC-007 | Security Middleware — validação centralizada de segurança em todas as rotas | Crítica |
| SEC-008 | GeoIP service — geolocalização por IP para detecção de anomalias | Média |
| SEC-009 | Secure token service — geração criptograficamente segura de tokens | Alta |
| SEC-010 | Security helpers — funções de sanitização, escape e validação reutilizáveis | Alta |
| SEC-011 | Gestão de IPs bloqueados — exportação e administração de blocklist | Média |

### Conformidade

- OWASP Top 10 — auditoria documentada em `docs/reports/SECURITY_AUDIT_2026-03-29.md`
- ISO 27001 — princípios de segurança da informação
- LGPD — dados pessoais de compradores (nome, CPF, endereço) tratados com proteção

### Arquivos Principais

`app/Middleware/SecurityMiddleware.php`, `app/Middleware/CsrfMiddleware.php`, `app/Middleware/RateLimitMiddleware.php`, `app/Middleware/SecurityHeadersMiddleware.php`, `app/Services/EncryptionService.php`, `app/Services/AuditLogService.php`, `app/Helpers/SecurityHelper.php`

---

## Módulo 18 — Webhooks e Integrações Externas

### Descrição

Recebimento e processamento de eventos do Mercado Livre (pedidos, perguntas, pagamentos, etc.) via webhooks, integração com sistemas externos via API Bearer e MCP protocol.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| WEBHOOK-001 | Webhooks ML — recebimento e processamento de eventos (pedidos, perguntas, pagamentos) | Alta |
| WEBHOOK-002 | CLAWDBOT webhook — integração HMAC assinada com sistema externo | Média |
| WEBHOOK-003 | Webhook Inbox — fila de processamento assíncrono de webhooks genéricos | Alta |
| WEBHOOK-004 | Webhook replay — reprocessamento de eventos com falha | Média |
| ASSIST-001 | Assistant Connector — API Bearer multi-conta para integração com agentes externos | Média |
| OPENCLAW | OpenClaw Connector — integração com sistema OpenClaw externo | Baixa |
| MCP | MCP (Model Context Protocol) — bridge para integração de contexto ML com agentes IA | Média |

### Requisitos Não Funcionais

- Webhooks ML validados via assinatura antes do processamento
- Processamento assíncrono — webhook recebido retorna 200 imediatamente, processamento em background
- Dead Letter Queue para webhooks que falharam 3 vezes

### Arquivos Principais

`app/Controllers/MercadoLivreWebhookController.php`, `app/Services/MercadoLivreWebhookService.php`, `app/Services/WebhookInboxService.php`, `app/Services/MercadoLivreWebhookReplayService.php`, `app/Controllers/AssistantConnectorController.php`, `bin/webhook-processor-worker.php`, `bin/mcp-ml-start.sh`

---

## Módulo 19 — Publicidade (Ads)

### Descrição

Gestão de Product Ads e promoções no Mercado Livre, com wizard de criação, recursos avançados e simulação de impacto de campanhas.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| ADS-001 | Gestão de Product Ads — criar, editar, pausar, analisar campanhas no ML | Alta |
| ADS-002 | ML Ads Advanced — recursos avançados (lance automático, budget inteligente) | Média |
| ADS-003 | Ads Wizard — wizard guiado para criação de campanhas otimizadas | Média |
| ADS-004 | Promotions — gestão de promoções e descontos por anúncio ou categoria | Alta |

### Arquivos Principais

`app/Controllers/AdsController.php`, `app/Services/AdsService.php`, `app/Services/MercadoLivre/MLAdsAdvancedService.php`, `app/Services/AdsWizardService.php`, `app/Controllers/PromotionController.php`, `app/Services/PromotionService.php`

---

## Módulo 20 — Marca e Posicionamento

### Descrição

Gestão e análise do posicionamento da marca AWA Motos no Mercado Livre, monitoramento de anúncios por marca e análise comparativa de market share.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| BRAND-001 | Brand Central — gestão da presença de marca oficial no ML | Média |
| BRAND-002 | Brand Analyzer — análise de posicionamento, share of voice e reputação da marca AWA | Alta |
| BRAND-003 | Brand Search — busca de todos os anúncios de uma marca no ML para análise competitiva | Alta |

### Arquivos Principais

`app/Controllers/BrandCentralController.php`, `app/Services/BrandCentralService.php`, `app/Controllers/BrandAnalyzerController.php`, `app/Services/BrandAnalyzerService.php`, `app/Controllers/BrandSearchController.php`, `app/Services/MercadoLivre/BrandSearchService.php`

---

## Módulo 21 — Multi-Conta

### Descrição

Gerenciamento simultâneo de múltiplas contas do Mercado Livre, com contexto de conta isolado por request e sincronização entre contas.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| MULTI-001 | Gerenciamento de múltiplas contas ML — adicionar, remover, alternar contas | Alta |
| MULTI-002 | Account Sync Service — sincronização de dados entre contas | Alta |
| MULTI-003 | Account Context Middleware — isolamento de contexto por conta em cada request | Alta |

### Arquivos Principais

`app/Controllers/MultiAccountController.php`, `app/Services/AccountSyncService.php`, `app/Middleware/AccountContextMiddleware.php`

---

## Módulo 22 — Finanças e Faturamento

### Descrição

Módulo financeiro completo: cálculo de comissões e taxas do ML, análise de lucratividade, conciliação de pagamentos, gestão de reembolsos e disputas, previsão financeira e gestão de assinaturas do sistema.

### Requisitos Funcionais

| Serviço | Função | Prioridade |
|---------|--------|-----------|
| FeeCommissionService | Cálculo preciso de comissões ML por categoria e tipo de anúncio | Alta |
| ProductProfitabilityService | Análise de rentabilidade por produto (preço - comissão - frete - custo) | Alta |
| PaymentRefundService | Gestão de reembolsos e estornos | Alta |
| ClaimDisputeService | Disputas financeiras com compradores via ML | Alta |
| CustomerPaymentMethodService | Análise dos meios de pagamento usados pelos compradores | Média |
| FinancialForecastService | Previsão de receita e margem para os próximos 30/90 dias | Média |
| OrderFinancialService | Detalhamento financeiro por pedido (crédito ML timeline) | Alta |
| SellerReputationService | Impacto financeiro da reputação (comissão premium para vendedores MercadoLíder) | Média |
| SubscriptionService | Gestão de planos e assinaturas do sistema eskill.com.br | Alta |
| SettlementReportService | Relatório de liquidação — créditos e débitos ML por período | Alta |
| MercadoPagoService | Integração direta com MP para consulta de saldos e movimentações | Alta |

### Arquivos Principais

`app/Services/Financial/`, `app/Services/MercadoPagoService.php`, `app/Controllers/FinancialReportController.php`, `app/Controllers/SettlementController.php`

---

## Módulo 23 — Marketplaces Alternativos

### Descrição

Expansão para outros marketplaces além do Mercado Livre, com abstração via Factory Pattern para suporte uniforme a múltiplos canais de venda.

### Requisitos Funcionais

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| SHOPEE-001 | Integração com Shopee — sincronização de anúncios e pedidos | Média |
| AMZ-001 | Integração com Amazon — publicação e gestão de anúncios | Baixa |
| MKT-001 | Marketplace Factory — abstração para suporte uniforme a múltiplos canais | Média |
| TRENDS | Análise cruzada de tendências entre marketplaces | Média |
| MARKET-DATA | Dados de mercado em tempo real (preços médios, líderes por categoria) | Média |

### Arquivos Principais

`app/Controllers/ShopeeController.php`, `app/Services/ShopeeService.php`, `app/Services/Marketplace/Amazon/`, `app/Services/Marketplace/MarketplaceFactory.php`, `app/Controllers/TrendsController.php`, `app/Services/RealMarketDataService.php`

---

## Módulo 24 — Infraestrutura e Core

### Descrição

Base técnica do sistema: framework MVC customizado, container de injeção de dependência, middleware pipeline, cache Redis, logging estruturado, feature flags e utilitários core.

### Middleware Pipeline

| Middleware | Função | Prioridade |
|-----------|--------|-----------|
| `AuthMiddleware` | Proteção de rotas autenticadas — redireciona para login se não autenticado | Crítica |
| `ApiAuthMiddleware` | Autenticação de endpoints API via JWT ou API key | Crítica |
| `AccountContextMiddleware` | Injeção do contexto da conta ML ativa no request | Alta |
| `CsrfMiddleware` | Validação de token CSRF em todos os requests mutantes | Crítica |
| `RateLimitMiddleware` | Rate limiting global por IP e por usuário | Crítica |
| `SecurityHeadersMiddleware` | Adição de headers de segurança em todas as respostas | Alta |
| `SecurityMiddleware` | Validação de segurança geral (injection, XSS, etc.) | Crítica |
| `CacheMiddleware` | Cache de respostas HTTP para endpoints read-only | Média |
| `PerformanceMiddleware` | Coleta de métricas de tempo de resposta e uso de recursos | Média |

### Classes Core

| Classe | Função |
|--------|--------|
| `Config.php` | Gestão centralizada de configurações com suporte a `.env` |
| `Container.php` | Container de injeção de dependência (IoC) |
| `ErrorHandler.php` | Handler global de erros não capturados |
| `EventBus.php` | Sistema de eventos pub/sub interno |
| `QueryBuilder.php` | Query Builder fluente sobre PDO |
| `Request.php` | Abstração de request HTTP |
| `Validator.php` | Validação declarativa de dados de entrada |
| `Pipeline.php` | Execução de middleware pipeline |
| `Paginator.php` | Paginação de resultados de consultas |

### Cache e Performance

| ID | Funcionalidade | Prioridade |
|----|---------------|-----------|
| CACHE-001 | Cache Redis — invalidação por tag, warmup e gestão de TTL | Alta |
| CACHE-002 | Advanced Redis Cache — cache distribuído com fallback | Alta |
| CACHE-003 | Cache Manager — gestão centralizada de estratégias de cache | Alta |
| CACHE-004 | Query Optimizer — cache de queries frequentes com invalidação inteligente | Média |
| CACHE-005 | Lazy Load Service — carregamento sob demanda de dados pesados | Média |
| CACHE-006 | Feature Flags — ativação/desativação de features sem redeploy | Média |

### Logging

| Serviço | Função |
|---------|--------|
| `CentralizedLogService` | Log centralizado com múltiplos handlers |
| `StructuredLogService` | Log em formato JSON estruturado |
| `AuditLogService` | Log de ações de usuário com contexto (quem, o quê, quando) |
| `LoggerService` | Wrapper de Monolog com contexto automático de request |

---

## Módulo 25 — Workers e Jobs em Background

### Descrição

Infraestrutura de processamento assíncrono com workers cron/daemon para operações de longa duração: sincronizações, otimizações, relatórios, monitoramento e IA.

### Workers Principais

| Worker | Frequência | Função |
|--------|-----------|--------|
| `auto-token-refresh-worker.php` | A cada 5 min | Refresh automático de tokens OAuth ML |
| `orders-sync-worker.php` | A cada 15 min | Sincronização de pedidos |
| `questions-sync-worker.php` | A cada 10 min | Sincronização de perguntas |
| `pricing-worker.php` | A cada 30 min | Atualização de preços dinâmicos |
| `stock-sync-worker.php` | A cada 30 min | Sincronização de estoque |
| `shipments-sync-worker.php` | A cada 30 min | Sincronização de envios |
| `clone-sync-worker.php` | A cada 30 min | Sincronização de itens clonados |
| `competitor-monitor-worker.php` | A cada hora | Monitoramento de concorrentes |
| `clone-health-monitor.php` | A cada hora | Saúde dos itens clonados |
| `performance-monitor.php` | A cada hora | Métricas de performance do sistema |
| `token-health-monitor.php` | A cada hora | Saúde dos tokens OAuth |
| `rules-engine-worker.php` | A cada 15 min | Execução de regras de precificação |
| `error-monitor.php` | A cada 5 min | Monitor de erros críticos |
| `scheduled-price-worker.php` | A cada 5 min | Aplicação de preços agendados |
| `seo-worker.php` | Diário | Worker SEO geral |
| `auto-pricing-optimizer.php` | Diário | Otimização automática de preços |
| `xray-scheduler.php` | Diário 02h | Diagnóstico X-Ray automático |
| `governance-diagnostic-worker.php` | Diário | Diagnóstico de governança |
| `tech-sheet-daily-report.php` | Diário | Relatório de fichas técnicas |
| `ml-health-check.php` | Diário 06h | Health check da integração ML |
| `ai-worker.php` | Contínuo | Worker de fila de IA |
| `webhook-processor-worker.php` | Contínuo | Processamento de webhooks |
| `automated-reports-worker.php` | Semanal | Relatórios automáticos por email |
| `catalog-clone-worker.php` | Sob demanda | Execução de clonagens |
| `bulk-seo-worker.php` | Sob demanda | Otimização SEO em lote |

### Jobs Agendados (app/Jobs/)

| Job | Função |
|-----|--------|
| `AIOptimizationWorker` | Processamento de fila de otimizações IA |
| `AgentJob` | Execução de tarefas de agente autônomo |
| `AutoAnswerJob` | Resposta automática a perguntas de compradores |
| `SEOMonitoringJob` | Monitoramento contínuo de rankings SEO |
| `TokenRefreshJob` | Job de refresh de token OAuth |

### Requisitos Não Funcionais

- Workers monitorados por processo supervisor (systemd ou supervisor)
- Dead Letter Queue para jobs que falharam 3+ vezes
- Logs de cada execução em `storage/logs/`
- Alertas de falha de worker crítico enviados em até 5 minutos

---

## Módulo 26 — Testes e Qualidade

### Descrição

Estratégia completa de qualidade: testes unitários com PHPUnit, testes E2E com Playwright, análise de código com Codacy, lint PHP e verificações de segurança com Trivy.

### Cobertura Atual

| Categoria | Nº de Testes | Status |
|-----------|-------------|--------|
| Testes unitários (PHPUnit) | 2923 | Passing |
| Testes E2E (Playwright) | 81 | Passing |
| Test files | 128 | — |

### Domínios Cobertos por Testes

| Domínio | Detalhes |
|---------|---------|
| Autenticação | `AuthTest`, `AuthControllerTest`, `AuthServiceTest`, `MercadoLivreAuthServiceTest` |
| SEO | 39 testes unitários (área mais coberta) |
| Clone | 7 testes: `CatalogCloneServiceTest`, `CloneMetricsServiceTest`, `CloneSyncServiceTest` + outros |
| Pricing | 5 testes: `DynamicPricingServiceTest`, `PriceRulesEngineServiceTest`, `AdvancedPricingEngineTest` |
| Financeiro | `FeeCommissionServiceTest` (28 testes), `EanServiceTest` (40 testes) |
| Core/Infra | 24 testes: `ContainerTest`, `RouterTest`, `RequestTest`, `SecurityTests`, `CacheServiceTest` |
| IA | `AIPredictiveAnalyticsServiceTest`, `SmartQAServiceTest` |

### Comandos

```bash
# Todos os testes
php vendor/bin/phpunit

# Suite unitária
php vendor/bin/phpunit --testsuite=Unit

# Filtro por teste
php vendor/bin/phpunit --filter NomeDoTest

# Testes E2E
./run-prod-validation.sh <email> <password>

# Lint PHP
php -l app/Services/SeuArquivo.php

# Análise de qualidade (Codacy CLI)
codacy analyze --file app/Services/SeuArquivo.php

# Security scan (Trivy)
codacy analyze --tool trivy
```

### Requisitos de Qualidade

- Todo arquivo PHP com `declare(strict_types=1)`
- Type hints completos em parâmetros e retornos
- Nenhum `var_dump`, `print_r` ou `echo` em código de produção
- Nenhum erro silenciado com `catch (\Exception $e) {}`
- Code style: PSR-12 via `phpcs.xml`

---

## 31. Métricas de Sucesso

| Métrica | Meta |
|---------|------|
| Uptime do sistema | ≥ 99.5% |
| Tempo de resposta dashboard | < 2 segundos |
| Taxa de sucesso de refresh de tokens | 100% |
| Cobertura de testes unitários | ≥ 80% dos services críticos |
| Workers sem falha consecutiva | 0 falhas por semana |
| Tempo para aplicar otimização SEO bulk | < 5 minutos para lotes de 100 anúncios |
| Taxa de respostas automáticas bem-sucedidas | ≥ 90% |
| Precisão de cálculo de margem | Erro < 0.1% vs. valor real ML |

---

## 32. Restrições e Riscos

### Restrições Técnicas

| Restrição | Impacto |
|-----------|---------|
| API ML com rate limiting (ex: 3000 req/hora) | Workers devem respeitar limites e implementar backoff |
| Limite de 60 caracteres no título do ML | SEO deve priorizar keywords dentro desse limite |
| OAuth ML com expiração de 6 horas | Workers de refresh críticos devem ter 100% de uptime |
| PHP 8.0+ requerido | Sem suporte a PHP 7.x |
| Sem código mock em produção | Toda integração deve ser real e funcional |

### Riscos

| Risco | Probabilidade | Mitigação |
|-------|--------------|-----------|
| Mudança na API do ML sem aviso | Média | Monitoramento contínuo de respostas, alertas de schema inesperado |
| Suspensão de conta ML | Baixa | Módulo de governança e X-Ray proativo |
| Falha de provedor IA (Claude/GPT) | Média | Circuit breaker + fallback automático entre provedores |
| Custo de IA fora do orçamento | Média | Cache de respostas, rate limiting por operação, relatório de custos |
| Vazamento de tokens OAuth | Baixa | Criptografia em banco, logs sem tokens, auditoria |
| Workers parando silenciosamente | Média | Monitoramento de processos + alertas + health check cron |

---

*PRD gerado em 2026-04-06 — eskill.com.br v1.0*
