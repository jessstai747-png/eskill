# Proposta de Estabilização e Profissionalização (Production Readiness)

## 0. Diagnóstico Brutal: O Sistema está Pronto?
**NÃO.**
O sistema, em seu estado atual, é um **protótipo avançado**. Ele possui funcionalidades de IA impressionantes e muita lógica de negócio implementada, mas a base técnica é **frágil**, insegura e não escalável.
Se lançado em produção hoje com usuários reais pagando:
1.  **Vazamento de Dados**: Riscos críticos de SQL Injection e exposição de tokens.
2.  **Colapso de Performance**: O sistema de logs trava o servidor com arquivos grandes; queries N+1 e falta de índices vão derrubar o banco.
3.  **Manutenção Impossível**: Classes "God Object" de 50KB+ sem testes reais tornam qualquer correção um risco de quebrar o sistema todo.
4.  **Credibilidade Zero**: Erros de infraestrutura (DDL em runtime) e falta de transações atômicas vão corromper dados de clientes.

## 1. Análise Arquitetural
-   **Framework Customizado**: O projeto roda em um framework homebrew (App\Core) em vez de usar Laravel/Symfony/CodeIgniter padrão. Isso aumenta drasticamente o custo de manutenção e onboarding.
-   **Acoplamento**: Controladores (ex: `SEOKillerController`) conhecem detalhes demais (SQL, criação de tabelas, lógica de framework).
-   **Camada de Dados**: Ausência de ORM ou Query Builder seguro. Uso extensivo de SQL cru com concatenação de strings em alguns pontos.
-   **Concorrência**: Criação de tabelas em tempo de execução (`CREATE TABLE IF NOT EXISTS` dentro de métodos GET) causará Deadlocks em escala.

## 2. Análise de Módulos

### A. SEO Intelligence / SEO Killer
-   **Promessa**: Otimização completa com IA.
-   **Realidade**: Lógica funcional mas monolítica. `AISEOOptimizerService` é gigantesco.
-   **Riscos**:
    -   Confusão de nomes (`SEOController` vs `SeoController`).
    -   Hardcoded Prompts dentro do código PHP (difícil de iterar/testar).
    -   Dependência de `json_decode` em saídas de LLM sem validação robusta (schema validation).
-   **Status**: 50% (Funcional, mas não sustentável).

### B. Competitor Monitor
-   **Promessa**: Monitoramento e alertas.
-   **Realidade**: Implementação procedural.
-   **Riscos**:
    -   **SQL Injection Potential**: Interpolação de variáveis em queries manuais.
    -   Loop de verificação síncrono ou mal orquestrado pode estourar timeouts PHP.
-   **Status**: 40% (Protótipo funcional, risco de segurança).

### C. Questions Bot
-   **Promessa**: Chatbot Autônomo.
-   **Realidade**: Proxy simples para serviço de IA.
-   **Riscos**:
    -   **Auth Bypass**: Lógica de `X-Account-Id` no controller é perigosa se não autenticada estritamente.
-   **Status**: 30% (Básico).

### D. Core & Infra (Auth, Logs, HTTP)
-   **Log Service**: Código limpo, mas método `search()` lê arquivos ao contrário na memória RAM. Inviável para logs de GBs.
-   **HTTP Client**: `MercadoLivreClient` usa `curl` puro apesar de ter Guzzle instalado. Reinvenção da roda, tratamento de erros inconsistente.
-   **Database**: Tabelas criadas "on the fly". Sem migrations versionadas.

## 3. Plano de Ação (Roadmap de Estabilização)

A prioridade é **SEGURANÇA** e **ESTABILIDADE**, não novas features.

### Fase 1: Fundação e Segurança (BLOQUEANTE)
1.  **Sanitize SQL**: Revisar TODAS as queries manuais. Implementar Prepared Statements estritos em 100% dos casos.
2.  **Fix Runtime DDL**: Mover todos os `CREATE TABLE` dos Controllers/Services para um sistema de Migrations real.
3.  **Secure Auth**: Remover lógica de `X-Account-Id` insegura. Validar sessão estritamente.
4.  **Hardening**: Habilitar CSP, limpar headers de debug, configurar `httponly` cookies corretamente (já parcialmente feito).

### Fase 2: Refatoração Técnica (ALTO)
1.  **Guzzle Migration**: Reescrever `MercadoLivreClient` para usar Guzzle (padrão da indústria, melhor retry/middleware).
2.  **Log Scalability**: Remover `search()` em memória. Implementar Log Rotation agressivo ou enviar para serviço externo (CloudWatch/Datadog) ou SQLite local para busca.
3.  **Prompts Extraction**: Mover prompts de IA para arquivos de configuração/views separados da lógica PHP.

### Fase 3: Qualidade e Testes (MÉDIO)
1.  **Testing Strategy**: Criar testes de integração reais para `SEOKiller` e `Competitor` usando mocks de HTTP (para não gastar API ML/OpenAI).
2.  **Dead Code Cleanup**: Remover arquivos duplicados (`SeoController` vs `SEOController`).

## 4. O Que Quebra no Primeiro Dia?
-   **Log Search**: Vai estourar memória assim que o log passar de 100MB.
-   **Concorrência**: Dois usuários acessando a mesma função de configuração ao mesmo tempo causarão erro de criação de tabela.
-   **API Limits**: Sem um sistema de fila robusto (ex: Redis/Horizon), o `BulkOptimizer` vai bater no Rate Limit do Mercado Livre e falhar silenciosamente ou travar o PHP-FPM.

---
**Recomendação**: Parar desenvolvimento de novas features. Focar 100% na **Fase 1** e **Fase 2** imediatamente.
