# Copilot Instructions — Projeto eskill.com.br (SEO Optimizer)

## Sobre o Desenvolvedor
- Nome: Jess
- Empresa: AWA Motos — distribuidora de peças para motos (Araraquara, SP, Brasil)
- Projeto: eskill.com.br — Sistema avançado de otimização SEO para Mercado Livre
- Foco: Automação de e-commerce, otimização de anúncios, integrações de API

## Stack Principal
- **Linguagem:** PHP 8.0+
- **Arquitetura:** Custom MVC (Router → Controller → Service → Model/View)
- **Banco:** MySQL via PDO
- **HTTP Client:** Guzzle 7
- **Logging:** Monolog 3
- **PDF:** DomPDF 3
- **Email:** PHPMailer 7
- **Push:** minishlink/web-push 10
- **Env:** vlucas/phpdotenv 5
- **Cache:** Redis (ext-redis)
- **Testes:** PHPUnit 9 + Faker
- **Autoloading:** PSR-4 (App\ → app/)
- **Versionamento:** Git com conventional commits

## Regras de Código — OBRIGATÓRIO

### Harness para Long-Running Agents
- **`project-status.json`** — Feature list com pass/fail. Atualize `passes` ao completar features. NUNCA remova features.
- **`claude-progress.txt`** — Log de progresso. Adicione entradas NO TOPO ao final de cada sessão.
- **`bin/init.sh`** — Smoke tests. Rode no início de cada sessão para verificar ambiente.
- Trabalhe em **UMA feature por vez**. Não tente implementar tudo de uma vez.
- Faça **git commit** ao final de cada sessão com mensagem descritiva.

### PHP
- Usar type hints em TODOS os parâmetros e retornos de função
- Usar `declare(strict_types=1)` em todo arquivo PHP
- NUNCA usar `mixed` sem necessidade real — use types específicos ou union types
- Preferir readonly properties quando possível (PHP 8.1+)
- Usar named arguments para clareza quando há muitos parâmetros
- Usar match() ao invés de switch quando retornando valores
- Usar null coalescing `??` e nullsafe `?->` ao invés de isset/ternário

### Classes e Métodos
- Uma classe por arquivo, PSR-4 autoloading
- Controllers: lógica mínima, delegar para Services
- Services: toda lógica de negócio aqui
- Models: acesso a dados via PDO
- Naming: PascalCase para classes, camelCase para métodos e variáveis
- Constantes: UPPER_SNAKE_CASE

### Naming
- Classes: PascalCase (`SEOKillerController`, `CatalogCloneService`)
- Métodos/funções: camelCase (`getItemById`, `optimizeTitle`)
- Variáveis: camelCase (`$itemCount`, `$accessToken`)
- Constantes: UPPER_SNAKE_CASE (`MAX_RETRIES`, `API_BASE_URL`)
- Arquivos de classe: PascalCase.php (`SEOController.php`)
- Arquivos utilitários: camelCase.php ou snake_case.php

### Estrutura de Pastas
```
app/
├── Controllers/     # Controllers HTTP (BaseController como base)
├── Services/        # Lógica de negócio, integrações
│   ├── AI/          # Services de IA (Claude, GPT)
│   ├── SEO/         # Services de SEO
│   ├── MercadoLivre/ # Integrações Mercado Livre
│   └── Shipping/    # Services de frete
├── Models/          # Modelos de dados / acesso DB
├── Views/           # Templates PHP (dashboard, etc.)
├── Middleware/       # Middlewares HTTP
├── Helpers/         # Funções auxiliares
├── Routes/          # Definições de rotas
├── Jobs/            # Background jobs / workers
├── Traits/          # PHP traits reutilizáveis
├── Core/            # Classes core do framework
└── Database/        # Migrations e seeds
bin/                 # Scripts CLI (workers, crons, utilities)
config/              # Configurações (database, app, etc.)
tests/               # PHPUnit tests
public/              # Assets públicos (CSS, JS, imagens)
storage/             # Logs, cache, uploads
```

### Error Handling
- Sempre usar try/catch em operações de I/O (DB, API, filesystem)
- NUNCA silenciar erros com catch vazio
- Log com Monolog — contexto estruturado (`$logger->error('msg', ['context' => $data])`)
- Retornar mensagens de erro amigáveis para o usuário
- Implementar retry com exponential backoff para chamadas de API externas
- Usar CircuitBreaker para APIs instáveis

### API & Integrações
- Usar Guzzle com configuração dedicada para cada API externa
- Implementar rate limiting (especialmente para API do Mercado Livre)
- Tratar todos os status HTTP adequadamente
- Separar configuração de API em config/ ou constantes
- Refresh token automático para OAuth (Mercado Livre)

### Testes
- PHPUnit para testes unitários e de integração
- Nomenclatura: `*Test.php` em `tests/`
- `@covers` annotation para rastreabilidade
- Testar comportamento, não implementação
- Rodar: `php vendor/bin/phpunit`

## Regras de Conduta do Agent

### SEMPRE faça:
1. Leia os arquivos existentes ANTES de criar novos
2. Verifique a estrutura do projeto antes de propor mudanças
3. Rode `php -l arquivo.php` após edições para verificar sintaxe
4. Rode `php vendor/bin/phpunit` se houver testes afetados
5. Use o autoloader PSR-4 (`App\` → `app/`)
6. Mantenha consistência com o código existente no projeto
7. Implemente tratamento de erro real em toda integração

### NUNCA faça:
1. ❌ Não gere código mock, placeholder, ou "TODO: implementar"
2. ❌ Não use `mixed` sem justificativa real
3. ❌ Não crie arquivos duplicados ou redundantes
4. ❌ Não instale dependências sem necessidade comprovada
5. ❌ Não altere .env, .gitignore, composer.json sem avisar
6. ❌ Não assuma a estrutura — sempre leia os arquivos primeiro
7. ❌ Não deixe `var_dump`/`print_r`/`echo` em produção (use Monolog)
8. ❌ Não faça commit de secrets, tokens, ou senhas
9. ❌ Não ignore erros de PHP — corrija-os

## Comandos do Projeto
- **Lint:** `php -l arquivo.php`
- **Test:** `php vendor/bin/phpunit`
- **Test Unit:** `php vendor/bin/phpunit --testsuite=Unit`
- **Test Filter:** `php vendor/bin/phpunit --filter NomeDoTest`
- **System Status:** `./bin/ai.sh status`
- **Worker:** `./bin/ai.sh worker start`
- **Migrations:** `php bin/apply-migrations.php`

## Contexto de Negócio (Mercado Livre)
- Produtos: bagageiros, baús, retrovisores, acessórios para motos
- Motos foco: CG 160, Titan, Fan, Bros, XRE, CB, Fazer, Factor
- SEO de marketplace é diferente de SEO web — foco em keywords no título
- Mercado Livre tem API própria (api.mercadolibre.com)
- Otimização de anúncios envolve: título, descrição, fotos, ficha técnica
- Sistema de clonagem de catálogo para múltiplas contas
- Pricing dinâmico baseado em concorrência
- Análise de competidores automatizada
- Integração com IA (Claude/GPT) para geração de conteúdo SEO
